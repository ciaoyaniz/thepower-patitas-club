# Patitas Club

Infraestructura de servidor y web de citas para una peluquería canina pequeña. Es el Proyecto Intermodular del CFGS de Administración de Sistemas Informáticos en Red (ASIR) de Yanina Zanella.

Un solo servidor Ubuntu Server 24.04 (`srv-patitas`, `192.168.50.10`) da DNS, DHCP, la web de citas con HTTPS, la base de datos, la carpeta compartida, el cortafuegos, las copias de seguridad y un informe diario.

## Qué hay en el repositorio

| Carpeta o archivo | Qué contiene |
|---|---|
| `config/10-patitas.conf` | SSH solo con clave y sin root |
| `config/netplan-srv-patitas.yaml` | Red del servidor: NAT por DHCP e IP fija `192.168.50.10` con el DNS propio |
| `config/named.conf.options`, `config/named.conf.local` | Opciones de Bind9 y las dos zonas |
| `config/db.patitas.lan`, `config/db.192.168.50` | Zona directa e inversa de `patitas.lan` |
| `config/isc-dhcp-server`, `config/dhcpd.conf` | Servidor DHCP de las dos VLAN y reserva del portátil |
| `config/patitas.conf`, `config/patitas-ssl.conf` | VirtualHost de Apache: redirección a HTTPS y web con la zona del equipo |
| `config/smb-oficina.conf` | Recurso compartido `oficina` de Samba |
| `config/jail.local` | Jaulas de fail2ban para SSH y para la zona del equipo |
| `config/crontab-root` | Copia de seguridad a las 2:00 y resumen diario a las 8:30 |
| `sql/patitas.sql` | Base de datos: tablas, índice, vista y datos de prueba |
| `web/` | Aplicación web en PHP. `public/` es lo único que publica Apache |
| `scripts/crear_usuarios_bd.sh` | Usuarios de MariaDB con contraseñas aleatorias guardadas en `/etc/patitas` |
| `scripts/desplegar_web.sh` | Copia la web a `/var/www/patitas` con sus permisos |
| `scripts/alta_empleado.sh` | Alta de un empleado en Linux, Samba y la web |
| `scripts/backup_patitas.sh`, `scripts/restaurar_bd.sh` | Copia de seguridad y restauración |
| `scripts/resumen_diario.sh`, `scripts/resumen_diario.py` | Informe diario con la agenda y los clientes para llamar |
| `scripts/comprobacion_final.sh` | Repasa todos los servicios y dice OK o FALLO |
| `memoria/` | Memoria del proyecto, diagramas y diario de trabajo |

## Instalación rápida

La instalación completa, con el porqué de cada paso, está en la memoria (apartado 7). Este es el orden sobre un Ubuntu Server 24.04 con la IP `192.168.50.10` en `ens34`, desde la raíz del repositorio:

1. SSH: copiar `config/10-patitas.conf` a `/etc/ssh/sshd_config.d/` y reiniciar `ssh`.
2. DNS: `apt install bind9 bind9-dnsutils`, copiar los cuatro archivos de Bind9 a `/etc/bind/` y la red de `config/netplan-srv-patitas.yaml` a `/etc/netplan/50-cloud-init.yaml`.
3. DHCP: `apt install isc-dhcp-server`, copiar `config/isc-dhcp-server` a `/etc/default/` y `config/dhcpd.conf` a `/etc/dhcp/` con la MAC del portátil.
4. Base de datos: `apt install mariadb-server`, `sudo mariadb < sql/patitas.sql` y `sudo ./scripts/crear_usuarios_bd.sh`.
5. Web: `apt install apache2 php libapache2-mod-php php-mysql php-mbstring rsync`, generar el certificado, copiar los dos VirtualHost, `a2enmod ssl headers`, `a2ensite patitas patitas-ssl`, `a2dissite 000-default` y `sudo ./scripts/desplegar_web.sh`.
6. Samba: `apt install samba`, crear el grupo y la carpeta `/srv/samba/oficina`, añadir `config/smb-oficina.conf` al final de `/etc/samba/smb.conf` y dar de alta a cada empleado con `sudo ./scripts/alta_empleado.sh <usuario>`.
7. Seguridad: reglas de UFW y `apt install fail2ban` con `config/jail.local`.
8. Copias e informe: instalar los scripts en `/usr/local/sbin/`, montar el disco de copias en `/mnt/usb-copias` y cargar `config/crontab-root` con `sudo crontab`.
9. Comprobar con `sudo ./scripts/comprobacion_final.sh`.

## Lo que no está en el repositorio

Las contraseñas, el certificado y su clave privada y las copias de seguridad no se suben nunca. Están en el servidor en `/etc/patitas/`, `/etc/apache2/.htpasswd`, `/etc/ssl/private/` y `/backup/patitas/`.
