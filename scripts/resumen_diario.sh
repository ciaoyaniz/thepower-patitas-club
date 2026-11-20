#!/usr/bin/env bash
# Saca de MariaDB la agenda de hoy y los perros que llevan más de 6 semanas sin venir
# y llama a resumen_diario.py para que escriba el informe en la carpeta compartida
# La lanza cron de lunes a sábado a las 8:30
set -euo pipefail

CNF=/etc/patitas/informe.cnf
SALIDA=/srv/samba/oficina/informes
LOG=/var/log/patitas_resumen.log
TMP=$(mktemp -d)

trap 'rm -rf "$TMP"' EXIT
trap 'echo "$(date "+%F %T") ERROR en la línea $LINENO" >> "$LOG"' ERR

# Agenda de hoy desde la vista. --batch separa las columnas con tabuladores
mariadb --defaults-extra-file="$CNF" --batch patitas -e "
SELECT hora, perro, raza, servicio, alergias, comportamiento, cliente, telefono
  FROM v_citas_hoy" > "$TMP/agenda.tsv"

# Perros con la última visita hace más de 42 días y sin ninguna cita pendiente
mariadb --defaults-extra-file="$CNF" --batch patitas -e "
SELECT p.nombre AS perro,
       CONCAT(cl.nombre, ' ', cl.apellidos) AS cliente,
       cl.telefono,
       DATE_FORMAT(MAX(c.fecha), '%d/%m/%Y') AS ultima_visita,
       DATEDIFF(CURDATE(), MAX(c.fecha)) AS dias
  FROM perros p
  JOIN clientes cl ON cl.id = p.cliente_id
  JOIN citas c ON c.perro_id = p.id AND c.estado = 'realizada'
 WHERE NOT EXISTS (SELECT 1 FROM citas f
                    WHERE f.perro_id = p.id
                      AND f.fecha >= CURDATE()
                      AND f.estado IN ('pendiente', 'confirmada'))
 GROUP BY p.id, p.nombre, cl.nombre, cl.apellidos, cl.telefono
HAVING dias > 42
 ORDER BY dias DESC" > "$TMP/recordatorios.tsv"

# El informe tiene que poder abrirlo cualquiera del grupo oficina
umask 002
mkdir -p "$SALIDA"
INFORME=$(python3 /usr/local/sbin/resumen_diario.py "$TMP/agenda.tsv" "$TMP/recordatorios.tsv" "$SALIDA")
chgrp oficina "$INFORME"

echo "$(date '+%F %T') OK $INFORME" >> "$LOG"
