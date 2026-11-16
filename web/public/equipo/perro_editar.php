<?php
// Zona del equipo: editar la ficha de un perro
declare(strict_types=1);

require_once __DIR__ . '/../../includes/funciones.php';

$db = conectar();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: perros.php');
    exit;
}

$campos = ['raza', 'tamano', 'fecha_nacimiento', 'corte_habitual', 'alergias', 'comportamiento', 'observaciones'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [];
    foreach ($campos as $c) {
        $d[$c] = trim((string) ($_POST[$c] ?? ''));
    }

    if (!in_array($d['tamano'], TAMANOS, true)) {
        $errores[] = 'Elige el tamaño del perro.';
    }
    if ($d['fecha_nacimiento'] !== '' && !DateTimeImmutable::createFromFormat('!Y-m-d', $d['fecha_nacimiento'])) {
        $errores[] = 'La fecha de nacimiento no es válida.';
    }
    foreach (['raza' => 60, 'corte_habitual' => 255, 'alergias' => 255, 'comportamiento' => 255] as $c => $max) {
        if (mb_strlen($d[$c]) > $max) {
            $errores[] = "El campo $c puede tener como mucho $max caracteres.";
        }
    }

    if ($errores === []) {
        // Los campos vacíos se guardan como NULL
        $v = array_map(fn ($x) => $x === '' ? null : $x, $d);
        $st = $db->prepare(
            'UPDATE perros
                SET raza = ?, tamano = ?, fecha_nacimiento = ?, corte_habitual = ?,
                    alergias = ?, comportamiento = ?, observaciones = ?
              WHERE id = ?'
        );
        $st->bind_param(
            'sssssssi',
            $v['raza'], $v['tamano'], $v['fecha_nacimiento'], $v['corte_habitual'],
            $v['alergias'], $v['comportamiento'], $v['observaciones'], $id
        );
        $st->execute();
        header('Location: perros.php?ok=1');
        exit;
    }
}

$st = $db->prepare(
    "SELECT p.*, CONCAT(cl.nombre, ' ', cl.apellidos) AS cliente
       FROM perros p JOIN clientes cl ON cl.id = p.cliente_id
      WHERE p.id = ?"
);
$st->bind_param('i', $id);
$st->execute();
$perro = $st->get_result()->fetch_assoc();
if ($perro === null) {
    header('Location: perros.php');
    exit;
}
if ($errores !== []) {
    $perro = array_merge($perro, $d);   // conservo lo que se había escrito
}

cabecera('Ficha de ' . $perro['nombre'], true);
?>
<h1>Ficha de <?= h($perro['nombre']) ?></h1>
<p>Dueño: <?= h($perro['cliente']) ?></p>

<?php if ($errores !== []): ?>
    <ul class="aviso error">
    <?php foreach ($errores as $e): ?>
        <li><?= h($e) ?></li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" class="formulario">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <label>Raza <input name="raza" value="<?= h($perro['raza']) ?>" maxlength="60"></label>
    <label>Tamaño
        <select name="tamano">
        <?php foreach (TAMANOS as $t): ?>
            <option <?= $t === $perro['tamano'] ? 'selected' : '' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
        </select>
    </label>
    <label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" value="<?= h($perro['fecha_nacimiento']) ?>"></label>
    <label>Corte habitual <input name="corte_habitual" value="<?= h($perro['corte_habitual']) ?>" maxlength="255"></label>
    <label>Alergias <input name="alergias" value="<?= h($perro['alergias']) ?>" maxlength="255"></label>
    <label>Comportamiento <input name="comportamiento" value="<?= h($perro['comportamiento']) ?>" maxlength="255"></label>
    <label>Observaciones <textarea name="observaciones" rows="4"><?= h($perro['observaciones']) ?></textarea></label>
    <button type="submit">Guardar</button>
    <a href="perros.php">Volver</a>
</form>
<?php
pie();
