<?php
// Zona del equipo: listado de perros con búsqueda por nombre del perro o del dueño
declare(strict_types=1);

require_once __DIR__ . '/../../includes/funciones.php';

$db = conectar();

$buscar = trim((string) ($_GET['q'] ?? ''));
$patron = '%' . $buscar . '%';

$st = $db->prepare(
    "SELECT p.id, p.nombre, p.raza, p.tamano, p.alergias, p.comportamiento,
            CONCAT(cl.nombre, ' ', cl.apellidos) AS cliente, cl.telefono
       FROM perros p
       JOIN clientes cl ON cl.id = p.cliente_id
      WHERE p.nombre LIKE ? OR cl.nombre LIKE ? OR cl.apellidos LIKE ?
      ORDER BY p.nombre"
);
$st->bind_param('sss', $patron, $patron, $patron);
$st->execute();
$perros = $st->get_result()->fetch_all(MYSQLI_ASSOC);

cabecera('Perros', true);
?>
<h1>Perros</h1>

<?php if (isset($_GET['ok'])): ?>
    <p class="aviso ok">Ficha guardada.</p>
<?php endif; ?>

<form method="get" class="filtro">
    <label>Buscar <input name="q" value="<?= h($buscar) ?>" placeholder="Perro o dueño"></label>
    <button type="submit">Buscar</button>
</form>

<table class="tabla">
    <thead>
        <tr><th>Perro</th><th>Dueño</th><th>Alergias</th><th>Comportamiento</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($perros as $p): ?>
        <tr>
            <td><strong><?= h($p['nombre']) ?></strong><br><small><?= h($p['raza']) ?> · <?= h($p['tamano']) ?></small></td>
            <td><?= h($p['cliente']) ?><br><small><?= h($p['telefono']) ?></small></td>
            <td class="<?= $p['alergias'] ? 'alerta' : '' ?>"><?= h($p['alergias']) ?></td>
            <td class="<?= $p['comportamiento'] ? 'alerta' : '' ?>"><?= h($p['comportamiento']) ?></td>
            <td><a href="perro_editar.php?id=<?= (int) $p['id'] ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
pie();
