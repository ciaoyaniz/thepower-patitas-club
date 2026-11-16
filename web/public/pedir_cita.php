<?php
// Formulario para pedir cita: valida los datos en el servidor y guarda cliente, perro y cita
declare(strict_types=1);

require_once __DIR__ . '/../includes/funciones.php';

$db = conectar();

// Solo se aceptan los días que enseña el calendario
$diasValidos = [];
foreach (dias_reserva() as $d) {
    $diasValidos[$d->format('Y-m-d')] = $d;
}

$fecha = $_POST['fecha'] ?? $_GET['fecha'] ?? '';
if (!isset($diasValidos[$fecha])) {
    header('Location: /');
    exit;
}
$dia = $diasValidos[$fecha];

$servicios = $db->query('SELECT id, nombre FROM servicios WHERE activo = TRUE ORDER BY id')
                ->fetch_all(MYSQLI_ASSOC);
$idsServicio = array_column($servicios, 'id');

$campos = ['nombre', 'apellidos', 'telefono', 'email', 'perro', 'raza', 'tamano', 'servicio', 'hora'];
$datos = array_fill_keys($campos, '');
$errores = [];
$guardada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $c) {
        $datos[$c] = trim((string) ($_POST[$c] ?? ''));
    }
    $libres = huecos_libres($db, $dia);

    // Validación de cada campo
    if (mb_strlen($datos['nombre']) < 2 || mb_strlen($datos['nombre']) > 60) {
        $errores[] = 'Escribe tu nombre (de 2 a 60 caracteres).';
    }
    if (mb_strlen($datos['apellidos']) < 2 || mb_strlen($datos['apellidos']) > 100) {
        $errores[] = 'Escribe tus apellidos (de 2 a 100 caracteres).';
    }
    if (!preg_match('/^\+?[0-9 ]{9,15}$/', $datos['telefono'])) {
        $errores[] = 'El teléfono tiene que tener al menos 9 cifras.';
    }
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['email']) > 120) {
        $errores[] = 'El email no es válido.';
    }
    if (mb_strlen($datos['perro']) < 1 || mb_strlen($datos['perro']) > 40) {
        $errores[] = 'Escribe el nombre de tu perro (hasta 40 caracteres).';
    }
    if (mb_strlen($datos['raza']) > 60) {
        $errores[] = 'La raza puede tener como mucho 60 caracteres.';
    }
    if (!in_array($datos['tamano'], TAMANOS, true)) {
        $errores[] = 'Elige el tamaño del perro.';
    }
    if (!in_array((int) $datos['servicio'], array_map('intval', $idsServicio), true)) {
        $errores[] = 'Elige un servicio.';
    }
    if (!isset($libres[$datos['hora']]) || $libres[$datos['hora']] === 0) {
        $errores[] = 'Elige una hora libre.';
    }
    if (!isset($_POST['rgpd'])) {
        $errores[] = 'Tienes que aceptar la política de privacidad para pedir cita.';
    }

    if ($errores === []) {
        $db->begin_transaction();
        try {
            // Vuelvo a contar la franja bloqueando sus filas hasta el final de la transacción
            $st = $db->prepare(
                "SELECT COUNT(*) FROM citas
                  WHERE fecha = ? AND hora = ? AND estado <> 'cancelada' FOR UPDATE"
            );
            $st->bind_param('ss', $fecha, $datos['hora']);
            $st->execute();
            if ((int) $st->get_result()->fetch_row()[0] >= PUESTOS) {
                throw new RuntimeException('Esa hora se acaba de llenar. Elige otra.');
            }

            // Cliente: si el email ya existe lo reutilizo y actualizo su teléfono
            $st = $db->prepare('SELECT id FROM clientes WHERE email = ?');
            $st->bind_param('s', $datos['email']);
            $st->execute();
            $clienteId = $st->get_result()->fetch_row()[0] ?? null;

            if ($clienteId === null) {
                $st = $db->prepare(
                    'INSERT INTO clientes (nombre, apellidos, telefono, email, consentimiento_rgpd)
                     VALUES (?, ?, ?, ?, TRUE)'
                );
                $st->bind_param('ssss', $datos['nombre'], $datos['apellidos'], $datos['telefono'], $datos['email']);
                $st->execute();
                $clienteId = $db->insert_id;
            } else {
                $st = $db->prepare('UPDATE clientes SET telefono = ?, consentimiento_rgpd = TRUE WHERE id = ?');
                $st->bind_param('si', $datos['telefono'], $clienteId);
                $st->execute();
            }

            // Perro: si el cliente ya tiene un perro con ese nombre lo reutilizo
            $st = $db->prepare('SELECT id FROM perros WHERE cliente_id = ? AND nombre = ?');
            $st->bind_param('is', $clienteId, $datos['perro']);
            $st->execute();
            $perroId = $st->get_result()->fetch_row()[0] ?? null;

            if ($perroId === null) {
                $st = $db->prepare('INSERT INTO perros (cliente_id, nombre, raza, tamano) VALUES (?, ?, ?, ?)');
                $st->bind_param('isss', $clienteId, $datos['perro'], $datos['raza'], $datos['tamano']);
                $st->execute();
                $perroId = $db->insert_id;
            }

            $servicioId = (int) $datos['servicio'];
            $st = $db->prepare('INSERT INTO citas (perro_id, servicio_id, fecha, hora) VALUES (?, ?, ?, ?)');
            $st->bind_param('iiss', $perroId, $servicioId, $fecha, $datos['hora']);
            $st->execute();

            $db->commit();
            $guardada = true;
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            if ($e->getCode() !== 1062) {   // 1062 = clave duplicada
                throw $e;
            }
            $errores[] = 'Este perro ya tiene una cita ese día.';
        } catch (RuntimeException $e) {
            $db->rollback();
            $errores[] = $e->getMessage();
        }
    }
}

$libres = huecos_libres($db, $dia);
cabecera('Pedir cita');
?>
<h1>Pedir cita · <?= h(fecha_corta($dia)) ?></h1>

<?php if ($guardada): ?>
    <p class="aviso ok">
        Hemos recibido tu cita para <strong><?= h($datos['perro']) ?></strong> el
        <?= h(fecha_corta($dia)) ?> a las <?= h($datos['hora']) ?>.
        Te llamaremos para confirmarla.
    </p>
    <p><a href="/">Volver al calendario</a></p>
<?php else: ?>
    <?php if ($errores !== []): ?>
        <ul class="aviso error">
        <?php foreach ($errores as $e): ?>
            <li><?= h($e) ?></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="formulario" novalidate>
        <input type="hidden" name="fecha" value="<?= h($fecha) ?>">

        <fieldset>
            <legend>Día y hora</legend>
            <label>Hora
                <select name="hora" required>
                <?php foreach ($libres as $hora => $n): ?>
                    <option value="<?= h($hora) ?>" <?= $n === 0 ? 'disabled' : '' ?> <?= $hora === $datos['hora'] ? 'selected' : '' ?>>
                        <?= h($hora) ?> <?= $n === 0 ? '(ocupado)' : '' ?>
                    </option>
                <?php endforeach; ?>
                </select>
            </label>
            <label>Servicio
                <select name="servicio" required>
                <?php foreach ($servicios as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (string) $s['id'] === $datos['servicio'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option>
                <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <fieldset>
            <legend>Tus datos</legend>
            <label>Nombre <input name="nombre" value="<?= h($datos['nombre']) ?>" maxlength="60" required></label>
            <label>Apellidos <input name="apellidos" value="<?= h($datos['apellidos']) ?>" maxlength="100" required></label>
            <label>Teléfono <input name="telefono" type="tel" value="<?= h($datos['telefono']) ?>" maxlength="15" required></label>
            <label>Email <input name="email" type="email" value="<?= h($datos['email']) ?>" maxlength="120" required></label>
        </fieldset>

        <fieldset>
            <legend>Tu perro</legend>
            <label>Nombre <input name="perro" value="<?= h($datos['perro']) ?>" maxlength="40" required></label>
            <label>Raza <input name="raza" value="<?= h($datos['raza']) ?>" maxlength="60"></label>
            <label>Tamaño
                <select name="tamano">
                <?php foreach (TAMANOS as $t): ?>
                    <option <?= $t === ($datos['tamano'] ?: 'mediano') ? 'selected' : '' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <label class="casilla">
            <input type="checkbox" name="rgpd" value="1">
            Acepto que Patitas Club guarde mis datos y los de mi perro solo para gestionar las citas.
        </label>

        <button type="submit">Pedir cita</button>
    </form>
<?php endif; ?>
<?php
pie();
