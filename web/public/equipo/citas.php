<?php
// Zona del equipo: citas de un día con los botones para confirmar, marcar como hecha o cancelar
declare(strict_types=1);

require_once __DIR__ . '/../../includes/funciones.php';

$db = conectar();

// Acción sobre una cita: cambia el estado y vuelve al listado del mismo día
$acciones = ['confirmar' => 'confirmada', 'realizada' => 'realizada', 'cancelar' => 'cancelada'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';
    $volver = $_POST['fecha'] ?? '';

    if ($id && isset($acciones[$accion])) {
        $st = $db->prepare('UPDATE citas SET estado = ? WHERE id = ?');
        $st->bind_param('si', $acciones[$accion], $id);
        $st->execute();
    }
    header('Location: citas.php?fecha=' . urlencode($volver));
    exit;
}

// Día que se enseña: el del filtro o hoy
$fecha = $_GET['fecha'] ?? '';
$dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) ?: new DateTimeImmutable('today');
$fecha = $dia->format('Y-m-d');

$st = $db->prepare(
    "SELECT c.id, TIME_FORMAT(c.hora, '%H:%i') AS hora, c.estado,
            p.nombre AS perro, s.nombre AS servicio,
            CONCAT(cl.nombre, ' ', cl.apellidos) AS cliente, cl.telefono
       FROM citas c
       JOIN perros p    ON p.id = c.perro_id
       JOIN clientes cl ON cl.id = p.cliente_id
       JOIN servicios s ON s.id = c.servicio_id
      WHERE c.fecha = ?
      ORDER BY c.hora, c.id"
);
$st->bind_param('s', $fecha);
$st->execute();
$citas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

cabecera('Citas', true);
?>
<h1>Citas del <?= h(fecha_corta($dia)) ?></h1>

<form method="get" class="filtro">
    <label>Día <input type="date" name="fecha" value="<?= h($fecha) ?>"></label>
    <button type="submit">Ver</button>
</form>

<?php if ($citas === []): ?>
    <p>No hay citas este día.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Hora</th><th>Perro</th><th>Servicio</th><th>Dueño</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($citas as $c): ?>
            <tr>
                <td><?= h($c['hora']) ?></td>
                <td><?= h($c['perro']) ?></td>
                <td><?= h($c['servicio']) ?></td>
                <td><?= h($c['cliente']) ?><br><small><?= h($c['telefono']) ?></small></td>
                <td><span class="estado <?= h($c['estado']) ?>"><?= h($c['estado']) ?></span></td>
                <td>
                    <form method="post" class="acciones">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
                        <?php if ($c['estado'] === 'pendiente'): ?>
                            <button name="accion" value="confirmar">Confirmar</button>
                        <?php endif; ?>
                        <?php if ($c['estado'] === 'confirmada'): ?>
                            <button name="accion" value="realizada">Hecha</button>
                        <?php endif; ?>
                        <?php if (in_array($c['estado'], ['pendiente', 'confirmada'], true)): ?>
                            <button name="accion" value="cancelar" class="secundario">Cancelar</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
pie();
