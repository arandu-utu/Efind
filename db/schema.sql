-- =====================================================
--  E-Find — Esquema de Base de Datos
--  Motor: MariaDB (XAMPP)
--  Proyecto de Egreso 2026 - Equipo Arandu
--  Rev. 1.1 — 2026-08-11
-- =====================================================

DROP DATABASE IF EXISTS efind;
CREATE DATABASE efind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE efind;

-- -----------------------------------------------------
-- ROLES
-- 1 = admin | 2 = usuario | 3 = propietario
-- -----------------------------------------------------
CREATE TABLE roles (
  id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- USUARIOS
-- -----------------------------------------------------
CREATE TABLE usuarios (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(80)  NOT NULL,
  email          VARCHAR(120) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  rol_id         TINYINT UNSIGNED NOT NULL DEFAULT 2,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- TIPOS DE CONECTOR
-- -----------------------------------------------------
CREATE TABLE tipos_conector (
  id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(40) NOT NULL UNIQUE,
  carga_rapida TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- VEHICULOS del usuario
-- -----------------------------------------------------
CREATE TABLE vehiculos (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id            INT UNSIGNED NOT NULL,
  marca                 VARCHAR(60)  NOT NULL,
  modelo                VARCHAR(80)  NOT NULL,
  anio                  YEAR,
  capacidad_bateria_kwh DECIMAL(6,2),
  autonomia_km          DECIMAL(7,1),
  tipo_conector_id      TINYINT UNSIGNED,
  activo                TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (usuario_id)       REFERENCES usuarios(id)       ON DELETE CASCADE,
  FOREIGN KEY (tipo_conector_id) REFERENCES tipos_conector(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- PUNTOS DE CARGA
-- fuente: 'efind' = cargado por usuarios/admin
--         'ute'   = datos de la API UTE (solo lectura)
-- -----------------------------------------------------
CREATE TABLE puntos_carga (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre         VARCHAR(120) NOT NULL,
  descripcion    TEXT,
  direccion      VARCHAR(200) NOT NULL,
  ciudad         VARCHAR(80),
  departamento   VARCHAR(50),
  lat            DECIMAL(10,8) NOT NULL,
  lng            DECIMAL(11,8) NOT NULL,
  acceso         ENUM('publico','privado') NOT NULL DEFAULT 'publico',
  estado         ENUM('disponible','ocupado','sin_servicio') NOT NULL DEFAULT 'disponible',
  fuente         ENUM('efind','ute') NOT NULL DEFAULT 'efind',
  propietario_id INT UNSIGNED,
  verificado     TINYINT(1) NOT NULL DEFAULT 0,
  activo         TINYINT(1) NOT NULL DEFAULT 1,
  creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (propietario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_pc_geo    ON puntos_carga (lat, lng);
CREATE INDEX idx_pc_estado ON puntos_carga (estado, activo);


-- -----------------------------------------------------
-- CONECTORES (cada estacion puede tener varios)
-- -----------------------------------------------------
CREATE TABLE conectores (
  id               INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  punto_carga_id   INT UNSIGNED     NOT NULL,
  tipo_conector_id TINYINT UNSIGNED NOT NULL,
  potencia_kw      DECIMAL(6,2),
  estado           ENUM('disponible','ocupado','sin_servicio') NOT NULL DEFAULT 'disponible',
  FOREIGN KEY (punto_carga_id)   REFERENCES puntos_carga(id)   ON DELETE CASCADE,
  FOREIGN KEY (tipo_conector_id) REFERENCES tipos_conector(id)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- RESERVAS
-- -----------------------------------------------------
CREATE TABLE reservas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NOT NULL,
  conector_id   INT UNSIGNED NOT NULL,
  vehiculo_id   INT UNSIGNED,
  fecha_inicio  DATETIME NOT NULL,
  fecha_fin     DATETIME NOT NULL,
  kwh_estimados DECIMAL(6,2),
  estado        ENUM('pendiente','confirmada','en_curso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id),
  FOREIGN KEY (conector_id) REFERENCES conectores(id),
  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- PAGOS (simulados)
-- comision_efind = 10% del monto_total
-- monto_propietario = 90% del monto_total
-- El calculo lo hace el PHP antes de insertar
-- -----------------------------------------------------
CREATE TABLE pagos (
  id                INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  reserva_id        INT UNSIGNED  NOT NULL UNIQUE,
  monto_total       DECIMAL(10,2) NOT NULL,
  comision_efind    DECIMAL(10,2) NOT NULL,
  monto_propietario DECIMAL(10,2) NOT NULL,
  metodo            VARCHAR(50)   NOT NULL DEFAULT 'simulado',
  estado            ENUM('pendiente','aprobado','rechazado','reembolsado') NOT NULL DEFAULT 'pendiente',
  procesado_en      TIMESTAMP NULL,
  creado_en         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reserva_id) REFERENCES reservas(id)
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- REPORTES de usuarios sobre cargadores
-- -----------------------------------------------------
CREATE TABLE reportes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  punto_carga_id INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED,
  tipo           ENUM('fuera_servicio','vandalismo','informacion_incorrecta','otro') NOT NULL,
  descripcion    TEXT,
  resuelto       TINYINT(1) NOT NULL DEFAULT 0,
  resuelto_por   INT UNSIGNED,
  creado_en      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (punto_carga_id) REFERENCES puntos_carga(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE SET NULL,
  FOREIGN KEY (resuelto_por)   REFERENCES usuarios(id)     ON DELETE SET NULL
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- FAVORITOS
-- -----------------------------------------------------
CREATE TABLE favoritos (
  usuario_id     INT UNSIGNED NOT NULL,
  punto_carga_id INT UNSIGNED NOT NULL,
  guardado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, punto_carga_id),
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE CASCADE,
  FOREIGN KEY (punto_carga_id) REFERENCES puntos_carga(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- -----------------------------------------------------
-- VISTA: lo que necesita el mapa por estacion
-- -----------------------------------------------------
CREATE VIEW v_disponibilidad AS
SELECT
  pc.id                                                   AS punto_carga_id,
  pc.nombre,
  pc.lat,
  pc.lng,
  pc.acceso,
  pc.estado,
  pc.fuente,
  COUNT(c.id)                                             AS total_conectores,
  SUM(c.estado = 'disponible')                            AS disponibles,
  SUM(c.estado = 'ocupado')                               AS ocupados,
  SUM(c.estado = 'sin_servicio')                          AS sin_servicio,
  MAX(c.potencia_kw)                                      AS potencia_max_kw,
  GROUP_CONCAT(DISTINCT tc.nombre ORDER BY tc.nombre)     AS tipos_conector
FROM puntos_carga pc
JOIN conectores     c  ON c.punto_carga_id  = pc.id
JOIN tipos_conector tc ON tc.id             = c.tipo_conector_id
WHERE pc.activo = 1
GROUP BY pc.id;


-- -----------------------------------------------------
-- VISTA: historial de carga por usuario
-- -----------------------------------------------------
CREATE VIEW v_historial_usuario AS
SELECT
  r.usuario_id,
  u.nombre        AS usuario,
  pc.nombre       AS estacion,
  tc.nombre       AS tipo_conector,
  c.potencia_kw,
  r.fecha_inicio,
  r.fecha_fin,
  r.kwh_estimados,
  r.estado        AS estado_reserva,
  p.monto_total,
  p.estado        AS estado_pago
FROM reservas r
JOIN usuarios       u  ON u.id  = r.usuario_id
JOIN conectores     c  ON c.id  = r.conector_id
JOIN puntos_carga   pc ON pc.id = c.punto_carga_id
JOIN tipos_conector tc ON tc.id = c.tipo_conector_id
LEFT JOIN pagos     p  ON p.reserva_id = r.id;
