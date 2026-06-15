-- =====================================================================
-- Sistema de Papeletas de Salida - DRE Puno
-- Esquema de base de datos
-- =====================================================================

CREATE DATABASE IF NOT EXISTS papeletas_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE papeletas_db;

-- ---------------------------------------------------------------------
-- personal : datos de los trabajadores (fuente para autocompletar)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS papeletas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS personal;

CREATE TABLE personal (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dni             CHAR(8)      NOT NULL,
  apellidos_nombres VARCHAR(200) NOT NULL,
  regimen         VARCHAR(80)  NOT NULL COMMENT 'Regimen del trabajador: D.L. 276, D.L. 728, CAS, etc.',
  dependencia     VARCHAR(150) NOT NULL,
  cargo           VARCHAR(150) NOT NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_personal_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- usuarios : credenciales de acceso al sistema
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  personal_id     INT UNSIGNED NULL,
  username        VARCHAR(50)  NOT NULL COMMENT 'Por defecto = DNI',
  password_hash   VARCHAR(255) NOT NULL,
  rol             ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  must_change     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = forzar cambio de clave',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login      TIMESTAMP    NULL,
  UNIQUE KEY uk_usuarios_username (username),
  KEY idx_usuarios_personal (personal_id),
  CONSTRAINT fk_usuarios_personal FOREIGN KEY (personal_id) REFERENCES personal(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- papeletas : log de papeletas emitidas (auditoria + correlativo)
-- ---------------------------------------------------------------------
CREATE TABLE papeletas (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero              VARCHAR(20)  NOT NULL COMMENT '0001-2026',
  anio                SMALLINT     NOT NULL,
  correlativo         INT          NOT NULL,
  usuario_id          INT UNSIGNED NOT NULL,
  personal_id         INT UNSIGNED NOT NULL COMMENT 'snapshot del solicitante',

  motivo_salida       VARCHAR(50)  NOT NULL,
  fundamentacion      TEXT         NULL,
  lugar               VARCHAR(200) NULL,
  dia                 TINYINT      NULL,
  mes                 TINYINT      NULL,
  anio_dmy            SMALLINT     NULL,
  hora_salida         TIME         NULL,
  hora_retorno        TIME         NULL,
  retorna             ENUM('SI','NO') NOT NULL DEFAULT 'NO',
  observaciones       TEXT         NULL,

  fecha_emision       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uk_papeletas_numero (numero),
  UNIQUE KEY uk_papeletas_anio_corr (anio, correlativo),
  KEY idx_papeletas_usuario (usuario_id),
  KEY idx_papeletas_personal (personal_id),
  KEY idx_papeletas_fecha (fecha_emision),
  CONSTRAINT fk_papeletas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_papeletas_personal FOREIGN KEY (personal_id) REFERENCES personal(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Para crear el primer usuario administrador ejecute desde la terminal:
--   php tools/create_admin.php admin admin123
-- ---------------------------------------------------------------------
