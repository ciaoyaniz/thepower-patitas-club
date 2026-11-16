#!/usr/bin/env bash
# Da de alta a un empleado en Linux, en Samba y en la zona del equipo de la web
# Uso: sudo ./scripts/alta_empleado.sh nombreusuario
set -euo pipefail

HTPASSWD=/etc/apache2/.htpasswd
GRUPO=oficina

[[ $EUID -eq 0 ]] || { echo "Ejecútalo con sudo" >&2; exit 1; }
if [[ $# -ne 1 || ! $1 =~ ^[a-z][a-z0-9_-]{2,15}$ ]]; then
    echo "Uso: sudo $0 nombreusuario (en minúsculas y de 3 a 16 caracteres)" >&2
    exit 1
fi
USUARIO=$1

read -rsp "Contraseña para $USUARIO: " CLAVE; echo
read -rsp "Repite la contraseña: " CLAVE2; echo
if [[ "$CLAVE" != "$CLAVE2" || ${#CLAVE} -lt 8 ]]; then
    echo "Las contraseñas no coinciden o tienen menos de 8 caracteres" >&2
    exit 1
fi

# 1. Usuario de Linux sin shell ni carpeta personal, dentro del grupo oficina
if id "$USUARIO" &>/dev/null; then
    usermod -aG "$GRUPO" "$USUARIO"
    echo "Linux: $USUARIO ya existía y lo he añadido al grupo $GRUPO"
else
    useradd -M -s /usr/sbin/nologin -G "$GRUPO" "$USUARIO"
    echo "Linux: usuario $USUARIO creado en el grupo $GRUPO"
fi

# 2. Usuario de Samba con la misma contraseña
printf '%s\n%s\n' "$CLAVE" "$CLAVE" | smbpasswd -a -s "$USUARIO" >/dev/null
smbpasswd -e "$USUARIO" >/dev/null
echo "Samba: usuario $USUARIO activo"

# 3. Usuario de la zona del equipo de la web, con la contraseña cifrada con bcrypt
if [[ -f $HTPASSWD ]]; then
    htpasswd -iB "$HTPASSWD" "$USUARIO" <<< "$CLAVE"
else
    htpasswd -ciB "$HTPASSWD" "$USUARIO" <<< "$CLAVE"
fi
chown root:www-data "$HTPASSWD"
chmod 640 "$HTPASSWD"
echo "Web: usuario $USUARIO en $HTPASSWD"
