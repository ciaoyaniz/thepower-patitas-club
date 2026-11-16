#!/usr/bin/env bash
# Copia la web del repositorio a /var/www/patitas con los permisos de Apache
# Uso: sudo ./scripts/desplegar_web.sh (desde la raíz del repositorio)
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Ejecútalo con sudo" >&2; exit 1; }

ORIGEN="$(dirname "$0")/../web/"
DESTINO=/var/www/patitas/

mkdir -p "$DESTINO"
rsync -a --delete "$ORIGEN" "$DESTINO"

# root es el dueño y Apache solo puede leer
chown -R root:www-data "$DESTINO"
find "$DESTINO" -type d -exec chmod 750 {} +
find "$DESTINO" -type f -exec chmod 640 {} +

echo "Web desplegada en $DESTINO"
