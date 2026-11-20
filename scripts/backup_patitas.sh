#!/usr/bin/env bash
# Copia de seguridad de Patitas Club: base de datos, web y carpeta compartida
# La lanza cron cada noche a las 2:00
set -euo pipefail

DESTINO=/backup/patitas
USB=/mnt/usb-copias
CNF=/etc/patitas/backup.cnf
LOG=/var/log/patitas_backup.log
DIAS=14
FECHA=$(date +%F_%H%M)
ARCHIVO="$DESTINO/patitas_$FECHA.tar.gz"
TMP=$(mktemp -d)

trap 'rm -rf "$TMP"' EXIT
trap 'echo "$(date "+%F %T") ERROR en la línea $LINENO" >> "$LOG"' ERR

# Las copias llevan datos personales y solo las puede leer root
umask 077
mkdir -p "$DESTINO"

# 1. Volcado de la base de datos sin bloquear la web
mariadb-dump --defaults-extra-file="$CNF" --single-transaction --databases patitas > "$TMP/patitas.sql"

# 2. Un solo archivo con el volcado, la web y la carpeta compartida
tar -czf "$ARCHIVO" -C "$TMP" patitas.sql -C / var/www/patitas srv/samba/oficina

# 3. Borro las copias de más de 14 días
find "$DESTINO" -name 'patitas_*.tar.gz' -mtime +"$DIAS" -delete

# 4. Segunda copia en el disco externo si está montado
if mountpoint -q "$USB"; then
    rsync -a --delete "$DESTINO/" "$USB/patitas/"
    COPIA_USB="sí"
else
    COPIA_USB="no, el disco no está montado"
fi

TAMANO=$(du -h "$ARCHIVO" | cut -f1)
echo "$(date '+%F %T') OK $(basename "$ARCHIVO") ($TAMANO) · copia en el disco USB: $COPIA_USB" >> "$LOG"
