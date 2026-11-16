<?php
// Zona del equipo: agenda de hoy con las alergias y el comportamiento resaltados
declare(strict_types=1);

require_once __DIR__ . '/../../includes/funciones.php';

$db = conectar();
$citas = $db->query('SELECT * FROM v_citas_hoy')->fetch_all(MYSQLI_ASSOC);

cabecera('Agenda de hoy', true);
?>
<h1>Agenda de hoy · <?= h(fecha_corta(new DateTimeImmutable('today'))) ?></h1>

<?php if ($citas === []): ?>
    <p>Hoy no hay citas confirmadas.</p>
<?php else: ?>
    <table class="tabla">
        <thead>
            <tr><th>Hora</th><th>Perro</th><th>Servicio</th><th>Corte habitual</th><th>Alergias</th><th>Comportamiento</th><th>Dueño</th></tr>
        </thead>
        <tbody>
        <?php foreach ($citas as $c): ?>
            <tr>
                <td><?= h($c['hora']) ?></td>
                <td><strong><?= h($c['perro']) ?></strong><br><small><?= h($c['raza']) ?> · <?= h($c['tamano']) ?></small></td>
                <td><?= h($c['servicio']) ?></td>
                <td><?= h($c['corte_habitual']) ?></td>
                <td class="<?= $c['alergias'] ? 'alerta' : '' ?>"><?= h($c['alergias']) ?></td>
                <td class="<?= $c['comportamiento'] ? 'alerta' : '' ?>"><?= h($c['comportamiento']) ?></td>
                <td><?= h($c['cliente']) ?><br><small><?= h($c['telefono']) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
pie();
