#!/usr/bin/env bash
# Comprobación final de srv-patitas: repasa cada servicio y dice OK o FALLO
# Uso: sudo ./scripts/comprobacion_final.sh
set -uo pipefail

[[ $EUID -eq 0 ]] || { echo "Ejecútalo con sudo" >&2; exit 1; }

FALLOS=0

# Ejecuta la comprobación que se le pasa y escribe OK o FALLO con su texto
comprobar() {
    local texto=$1
    shift
    if "$@" &>/dev/null; then
        printf 'OK     %s\n' "$texto"
    else
        printf 'FALLO  %s\n' "$texto"
        FALLOS=$((FALLOS + 1))
    fi
}

codigo_http() {
    curl -sk -o /dev/null -w '%{http_code}' "$1"
}

echo "== Servicios"
for s in ssh.socket named isc-dhcp-server mariadb apache2 smbd fail2ban cron; do
    comprobar "$s está activo" systemctl is-active --quiet "$s"
done

echo "== Red y DNS"
comprobar "ens34 tiene la 192.168.50.10" bash -c "ip -br a show ens34 | grep -q '192.168.50.10/24'"
comprobar "citas.patitas.lan resuelve" bash -c "dig +short @192.168.50.10 citas.patitas.lan | grep -q '192.168.50.10'"
comprobar "la zona inversa responde" bash -c "dig +short @192.168.50.10 -x 192.168.50.10 | grep -q 'srv-patitas'"
comprobar "el DNS reenvía a Internet" bash -c "dig +short @192.168.50.10 ubuntu.com | grep -q ."
comprobar "dhcpd.conf sin errores" dhcpd -t -cf /etc/dhcp/dhcpd.conf

echo "== Web"
comprobar "Apache sin errores de configuración" apache2ctl configtest
comprobar "HTTP redirige a HTTPS" bash -c "curl -sI http://citas.patitas.lan | grep -qi '^location: https://'"
comprobar "la web pública responde 200" test "$(codigo_http https://citas.patitas.lan/)" = 200
comprobar "la zona del equipo pide contraseña (401)" test "$(codigo_http https://citas.patitas.lan/equipo/)" = 401

echo "== Base de datos"
comprobar "MariaDB solo escucha en 127.0.0.1" bash -c "ss -ltn | grep ':3306 ' | grep -q '127.0.0.1'"
comprobar "la vista v_citas_hoy funciona" mariadb -e "SELECT COUNT(*) FROM patitas.v_citas_hoy"

echo "== Carpeta compartida"
comprobar "testparm sin errores" testparm -s
comprobar "existe el recurso oficina" bash -c "testparm -s 2>/dev/null | grep -q '^\[oficina\]'"

echo "== Seguridad"
comprobar "UFW está activo" bash -c "ufw status | grep -q 'Status: active'"
comprobar "jaula sshd de fail2ban" fail2ban-client status sshd
comprobar "jaula apache-auth de fail2ban" fail2ban-client status apache-auth
comprobar "SSH no acepta contraseñas" bash -c "sshd -T | grep -q '^passwordauthentication no'"

echo "== Copias e informe"
comprobar "las dos tareas están en el crontab de root" bash -c "crontab -l | grep -q backup_patitas && crontab -l | grep -q resumen_diario"
comprobar "hay una copia de las últimas 24 horas" bash -c "find /backup/patitas -name 'patitas_*.tar.gz' -mtime -1 | grep -q ."
comprobar "el disco de copias está montado" mountpoint -q /mnt/usb-copias
comprobar "está el informe de hoy" test -f "/srv/samba/oficina/informes/agenda_$(date +%F).txt"

echo
if (( FALLOS == 0 )); then
    echo "Todo correcto"
else
    echo "Comprobaciones con fallo: $FALLOS"
    exit 1
fi
