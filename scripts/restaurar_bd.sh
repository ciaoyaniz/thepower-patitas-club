#!/usr/bin/env bash
# Restaura la base de datos patitas desde una copia de seguridad
# Uso: sudo ./scripts/restaurar_bd.sh /backup/patitas/patitas_AAAA-MM-DD_HHMM.tar.gz
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Ejecútalo con sudo" >&2; exit 1; }
[[ $# -eq 1 && -f $1 ]] || { echo "Uso: sudo $0 <copia.tar.gz>" >&2; exit 1; }

COPIA=$1
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

# Solo saco el volcado de la base de datos
tar -xzf "$COPIA" -C "$TMP" patitas.sql

echo "Voy a sustituir la base de datos patitas por la de $(basename "$COPIA")"
read -rp "Escribe SI para seguir: " RESPUESTA
[[ $RESPUESTA == "SI" ]] || { echo "Cancelado"; exit 1; }

mariadb -e "DROP DATABASE IF EXISTS patitas"
mariadb < "$TMP/patitas.sql"

echo "Base de datos restaurada. Citas guardadas: $(mariadb -N -e 'SELECT COUNT(*) FROM patitas.citas')"
