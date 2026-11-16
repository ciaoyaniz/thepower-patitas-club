<?php
// Página pública: servicios y calendario de huecos libres de los próximos días
declare(strict_types=1);

require_once __DIR__ . '/../includes/funciones.php';

$db = conectar();
$servicios = $db->query(
    'SELECT nombre, duracion_min, precio_desde FROM servicios WHERE activo = TRUE ORDER BY id'
)->fetch_all(MYSQLI_ASSOC);

cabecera('Pide tu cita');
?>
<h1>Pide cita para tu perro</h1>
<p>Elige un día con huecos libres. Cada cita dura una hora y atendemos a dos perros a la vez.</p>

<h2>Servicios</h2>
<ul class="servicios">
<?php foreach ($servicios as $s): ?>
    <li>
        <strong><?= h($s['nombre']) ?></strong>
        <span><?= (int) $s['duracion_min'] ?> min · desde <?= number_format((float) $s['precio_desde'], 2, ',', '.') ?> €</span>
    </li>
<?php endforeach; ?>
</ul>

<h2>Próximos días</h2>
<div class="calendario">
<?php foreach (dias_reserva() as $dia):
    $libres = array_sum(huecos_libres($db, $dia));
    $estado = $libres === 0 ? 'completo' : ($libres <= 4 ? 'pocos' : 'libre');
?>
    <div class="dia <?= $estado ?>">
        <span class="fecha"><?= h(fecha_corta($dia)) ?></span>
        <?php if ($libres === 0): ?>
            <span class="huecos">Completo</span>
        <?php else: ?>
            <a class="huecos" href="pedir_cita.php?fecha=<?= $dia->format('Y-m-d') ?>"><?= $libres ?> huecos</a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php
pie();
