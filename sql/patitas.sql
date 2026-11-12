-- Base de datos de Patitas Club
-- Crea la base, las cuatro tablas, el índice, la vista y unos datos de prueba
-- Se ejecuta una sola vez con: sudo mariadb < sql/patitas.sql

CREATE DATABASE patitas CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE patitas;

CREATE TABLE clientes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(60)  NOT NULL,
    apellidos           VARCHAR(100) NOT NULL,
    telefono            VARCHAR(15)  NOT NULL,
    email               VARCHAR(120) NOT NULL,
    consentimiento_rgpd BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_alta          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_clientes_email UNIQUE (email)
) ENGINE = InnoDB;

CREATE TABLE perros (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id       INT UNSIGNED NOT NULL,
    nombre           VARCHAR(40)  NOT NULL,
    raza             VARCHAR(60),
    tamano           ENUM('pequeño', 'mediano', 'grande') NOT NULL DEFAULT 'mediano',
    fecha_nacimiento DATE,
    corte_habitual   VARCHAR(255),
    alergias         VARCHAR(255),
    comportamiento   VARCHAR(255),
    observaciones    TEXT,
    CONSTRAINT uq_perros_cliente_nombre UNIQUE (cliente_id, nombre),
    CONSTRAINT fk_perros_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE servicios (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(60)       NOT NULL,
    duracion_min SMALLINT UNSIGNED NOT NULL,
    precio_desde DECIMAL(6,2)      NOT NULL,
    activo       BOOLEAN           NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_servicios_nombre UNIQUE (nombre)
) ENGINE = InnoDB;

CREATE TABLE citas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    perro_id    INT UNSIGNED NOT NULL,
    servicio_id INT UNSIGNED NOT NULL,
    fecha       DATE         NOT NULL,
    hora        TIME         NOT NULL,
    estado      ENUM('pendiente', 'confirmada', 'cancelada', 'realizada') NOT NULL DEFAULT 'pendiente',
    creada      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_citas_perro_fecha UNIQUE (perro_id, fecha),
    CONSTRAINT fk_citas_perro FOREIGN KEY (perro_id)
        REFERENCES perros (id) ON DELETE CASCADE,
    CONSTRAINT fk_citas_servicio FOREIGN KEY (servicio_id)
        REFERENCES servicios (id)
) ENGINE = InnoDB;

-- La consulta que más se repite: contar los puestos ocupados de una franja
CREATE INDEX idx_citas_fecha_hora ON citas (fecha, hora, estado);

-- Agenda de hoy: citas confirmadas con la ficha del perro y el teléfono del dueño
CREATE VIEW v_citas_hoy AS
SELECT c.id,
       TIME_FORMAT(c.hora, '%H:%i')          AS hora,
       p.nombre                              AS perro,
       p.raza,
       p.tamano,
       s.nombre                              AS servicio,
       p.corte_habitual,
       p.alergias,
       p.comportamiento,
       CONCAT(cl.nombre, ' ', cl.apellidos)  AS cliente,
       cl.telefono
FROM citas c
JOIN perros p     ON p.id = c.perro_id
JOIN clientes cl  ON cl.id = p.cliente_id
JOIN servicios s  ON s.id = c.servicio_id
WHERE c.fecha = CURDATE()
  AND c.estado = 'confirmada'
ORDER BY c.hora;

-- Datos de prueba. Las fechas van con CURDATE() para que siempre haya agenda hoy
INSERT INTO servicios (nombre, duracion_min, precio_desde) VALUES
    ('Baño y secado',        60, 25.00),
    ('Baño y corte',         60, 35.00),
    ('Deslanado',            60, 40.00),
    ('Corte higiénico',      60, 20.00),
    ('Corte de uñas y oídos', 60, 12.00);

INSERT INTO clientes (nombre, apellidos, telefono, email, consentimiento_rgpd) VALUES
    ('Laura',  'Gómez Ruiz',     '600111222', 'laura.gomez@example.com',  TRUE),
    ('Javier', 'Martín Sanz',    '600333444', 'javier.martin@example.com', TRUE),
    ('Carmen', 'López Herrero',  '600555666', 'carmen.lopez@example.com', TRUE),
    ('Pablo',  'Navarro Gil',    '600777888', 'pablo.navarro@example.com', TRUE);

INSERT INTO perros (cliente_id, nombre, raza, tamano, corte_habitual, alergias, comportamiento) VALUES
    (1, 'Kira',  'Caniche',          'pequeño', 'Corte cachorro con la cara redonda', 'Champú con avena',  NULL),
    (2, 'Thor',  'Golden retriever', 'grande',  'Solo arreglo de pies y cola',        NULL,                'Muerde si le tocan las orejas'),
    (2, 'Luna',  'Mestiza',          'mediano', NULL,                                 NULL,                NULL),
    (3, 'Coco',  'Yorkshire',        'pequeño', 'Corte corto a máquina del 4',        NULL,                'Muy nervioso con el secador'),
    (4, 'Rocky', 'Pastor alemán',    'grande',  NULL,                                 'Perfume',           NULL);

INSERT INTO citas (perro_id, servicio_id, fecha, hora, estado) VALUES
    (1, 2, CURDATE(),                     '10:00', 'confirmada'),
    (2, 3, CURDATE(),                     '10:00', 'confirmada'),
    (3, 1, CURDATE(),                     '12:00', 'pendiente'),
    (1, 2, CURDATE() - INTERVAL 35 DAY,   '10:00', 'realizada'),
    (2, 3, CURDATE() - INTERVAL 30 DAY,   '11:00', 'realizada'),
    (3, 1, CURDATE() - INTERVAL 20 DAY,   '12:00', 'realizada'),
    (4, 4, CURDATE() - INTERVAL 50 DAY,   '09:00', 'realizada'),
    (5, 1, CURDATE() - INTERVAL 70 DAY,   '17:00', 'realizada');
