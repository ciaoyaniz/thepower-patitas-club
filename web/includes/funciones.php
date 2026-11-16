<?php
// Funciones comunes de la web de Patitas Club
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

date_default_timezone_set('Europe/Madrid');

const PUESTOS = 2;        // perros que se atienden a la vez
const DIAS_RESERVA = 14;  // días de trabajo que enseña el calendario
const ESTADOS = ['pendiente', 'confirmada', 'cancelada', 'realizada'];
const TAMANOS = ['pequeño', 'mediano', 'grande'];

// Escapa un texto antes de escribirlo en la página
function h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// Franjas de una hora de un día: de lunes a viernes mañana y tarde, el sábado solo mañana
function franjas(DateTimeImmutable $dia): array
{
    $diaSemana = (int) $dia->format('N');   // 1 = lunes ... 7 = domingo
    $mananas = ['09:00', '10:00', '11:00', '12:00', '13:00'];
    $tardes = ['16:00', '17:00', '18:00', '19:00'];

    if ($diaSemana === 7) {
        return [];
    }
    if ($diaSemana === 6) {
        return $mananas;
    }
    return array_merge($mananas, $tardes);
}

// Los próximos DIAS_RESERVA días de trabajo, empezando mañana
function dias_reserva(): array
{
    $dias = [];
    $dia = new DateTimeImmutable('tomorrow');
    while (count($dias) < DIAS_RESERVA) {
        if (franjas($dia) !== []) {
            $dias[] = $dia;
        }
        $dia = $dia->modify('+1 day');
    }
    return $dias;
}

// Puestos libres de cada franja de un día: ['09:00' => 2, '10:00' => 0, ...]
function huecos_libres(mysqli $db, DateTimeImmutable $dia): array
{
    $libres = array_fill_keys(franjas($dia), PUESTOS);

    $st = $db->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS hora, COUNT(*) AS ocupados
           FROM citas
          WHERE fecha = ? AND estado <> 'cancelada'
          GROUP BY hora"
    );
    $fecha = $dia->format('Y-m-d');
    $st->bind_param('s', $fecha);
    $st->execute();

    foreach ($st->get_result() as $fila) {
        if (isset($libres[$fila['hora']])) {
            $libres[$fila['hora']] = max(0, PUESTOS - (int) $fila['ocupados']);
        }
    }
    return $libres;
}

// "lun 16/11"
function fecha_corta(DateTimeImmutable $dia): string
{
    $nombres = [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
    return $nombres[(int) $dia->format('N')] . ' ' . $dia->format('d/m');
}

// Cabecera HTML común. En la zona del equipo añade el menú y el usuario de Apache
function cabecera(string $titulo, bool $equipo = false): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Patitas Club</title>
    <link rel="stylesheet" href="/estilos.css">
</head>
<body>
<header class="cabecera">
    <a class="marca" href="<?= $equipo ? '/equipo/' : '/' ?>">Patitas Club</a>
    <?php if ($equipo): ?>
    <nav>
        <a href="/equipo/">Agenda de hoy</a>
        <a href="/equipo/citas.php">Citas</a>
        <a href="/equipo/perros.php">Perros</a>
        <span class="usuario"><?= h($_SERVER['REMOTE_USER'] ?? '') ?></span>
    </nav>
    <?php endif; ?>
</header>
<main>
    <?php
}

function pie(): void
{
    ?>
</main>
<footer class="pie">Patitas Club · Peluquería canina</footer>
</body>
</html>
    <?php
}
