#!/usr/bin/env bash
# Crea los usuarios de MariaDB de Patitas Club con contraseñas aleatorias
# y guarda cada contraseña en /etc/patitas, fuera del repositorio
# Uso: sudo ./scripts/crear_usuarios_bd.sh
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "Ejecútalo con sudo" >&2; exit 1; }

DIR=/etc/patitas
install -d -m 750 -o root -g www-data "$DIR"

CLAVE_WEB=$(openssl rand -hex 16)
CLAVE_BACKUP=$(openssl rand -hex 16)
CLAVE_INFORME=$(openssl rand -hex 16)

# Cada programa entra con su propio usuario y solo con los permisos que necesita
mariadb <<SQL
CREATE USER IF NOT EXISTS 'patitas_web'@'localhost';
ALTER USER 'patitas_web'@'localhost' IDENTIFIED BY '$CLAVE_WEB';
GRANT SELECT, INSERT, UPDATE ON patitas.* TO 'patitas_web'@'localhost';

CREATE USER IF NOT EXISTS 'patitas_backup'@'localhost';
ALTER USER 'patitas_backup'@'localhost' IDENTIFIED BY '$CLAVE_BACKUP';
GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT ON patitas.* TO 'patitas_backup'@'localhost';

CREATE USER IF NOT EXISTS 'patitas_informe'@'localhost';
ALTER USER 'patitas_informe'@'localhost' IDENTIFIED BY '$CLAVE_INFORME';
GRANT SELECT ON patitas.* TO 'patitas_informe'@'localhost';
SQL

# Credenciales de la web: PHP las puede leer gracias al grupo www-data
cat > "$DIR/web.ini" <<INI
host = localhost
base = patitas
usuario = patitas_web
clave = $CLAVE_WEB
INI
chown root:www-data "$DIR/web.ini"
chmod 640 "$DIR/web.ini"

# Credenciales de los scripts de copias y del informe: solo root
cat > "$DIR/backup.cnf" <<CNF
[client]
user = patitas_backup
password = $CLAVE_BACKUP
CNF
cat > "$DIR/informe.cnf" <<CNF
[client]
user = patitas_informe
password = $CLAVE_INFORME
CNF
chmod 600 "$DIR/backup.cnf" "$DIR/informe.cnf"

echo "Usuarios de MariaDB creados. Sus contraseñas están en $DIR"
