-- Opcional: crea y usa la base
CREATE DATABASE IF NOT EXISTS lrdu CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE lrdu;

-- =========================
-- Tabla: users
-- =========================
CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  password          VARCHAR(255) NOT NULL,                 -- hash bcrypt
  email             VARCHAR(180) NOT NULL UNIQUE,
  role              VARCHAR(32)  NOT NULL,                 -- guarda el value del enum Role (p.ej. 'CLIENT', 'ADMIN')
  token             VARCHAR(255) DEFAULT NULL,

  first_name        VARCHAR(50)  DEFAULT NULL,
  last_name         VARCHAR(50)  DEFAULT NULL,

  connected_at      DATETIME     DEFAULT NULL,
  disconnected_at   DATETIME     DEFAULT NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_users_role (role),
  INDEX idx_users_connected_at (connected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- Tabla: categorias
-- =========================
CREATE TABLE IF NOT EXISTS categorias (
  id_cat            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- Tabla: productos
-- =========================
CREATE TABLE IF NOT EXISTS productos (
  id_producto           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                VARCHAR(150)  NOT NULL,
  descripcion           TEXT          DEFAULT NULL,

  id_categoria          INT UNSIGNED  NOT NULL,

  stock                 INT UNSIGNED  NOT NULL DEFAULT 0 CHECK (stock >= 0),
  precio                DECIMAL(12,2) NOT NULL DEFAULT 0.00 CHECK (precio >= 0),

  marca                 VARCHAR(100)  DEFAULT NULL,
  modelo                VARCHAR(100)  DEFAULT NULL,
  caracteristicas       TEXT          DEFAULT NULL,

  codigo_interno        VARCHAR(64)   DEFAULT NULL,
  imagen_principal      VARCHAR(2048)  DEFAULT NULL,

  favorito              BOOLEAN       NOT NULL DEFAULT FALSE,
  activo                BOOLEAN       NOT NULL DEFAULT TRUE,

  fecha_creacion        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT uq_productos_codigo_interno UNIQUE (codigo_interno),
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (id_categoria) REFERENCES categorias (id_cat)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

  INDEX idx_productos_nombre (nombre),
  INDEX idx_productos_categoria (id_categoria),
  INDEX idx_productos_activo_fav (activo, favorito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
