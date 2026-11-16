<?php
// Conexión a MariaDB con el usuario patitas_web.
// Las credenciales están en /etc/patitas/web.ini, fuera de la web y del repositorio.
declare(strict_types=1);

function conectar(): mysqli
{
    $cfg = parse_ini_file('/etc/patitas/web.ini');
    if ($cfg === false) {
        http_response_code(500);
        exit('No se puede leer la configuración de la base de datos.');
    }

    // Cualquier error de MariaDB se convierte en una excepción
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $db = new mysqli($cfg['host'], $cfg['usuario'], $cfg['clave'], $cfg['base']);
    $db->set_charset('utf8mb4');
    return $db;
}
