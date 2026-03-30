-- ============================================================
--  FitSync — Schema do Banco de Dados
--  Execute: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS fitsync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fitsync;
-- Depois execute todo o conteúdo do schema.sql

-- ── Usuários ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_id     VARCHAR(100)  NULL UNIQUE,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(180)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NULL,
  avatar_url    VARCHAR(500)  NULL,
  provider      ENUM('email','google') NOT NULL DEFAULT 'email',
  daily_cal     SMALLINT UNSIGNED NOT NULL DEFAULT 2000,
  daily_prot    SMALLINT UNSIGNED NOT NULL DEFAULT 150,
  daily_carb    SMALLINT UNSIGNED NOT NULL DEFAULT 250,
  daily_fat     SMALLINT UNSIGNED NOT NULL DEFAULT 65,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Alimentos (cache da API USDA + customizados) ──────────────
CREATE TABLE IF NOT EXISTS foods (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fdcId         INT UNSIGNED NULL UNIQUE COMMENT 'ID na API USDA FoodData Central',
  name          VARCHAR(200) NOT NULL,
  brand         VARCHAR(100) NULL,
  cal_per_100g  DECIMAL(8,2) NOT NULL DEFAULT 0,
  prot_per_100g DECIMAL(8,2) NOT NULL DEFAULT 0,
  carb_per_100g DECIMAL(8,2) NOT NULL DEFAULT 0,
  fat_per_100g  DECIMAL(8,2) NOT NULL DEFAULT 0,
  fiber_per_100g DECIMAL(8,2) NULL,
  sugar_per_100g DECIMAL(8,2) NULL,
  sodium_per_100g DECIMAL(8,2) NULL,
  sat_fat_per_100g DECIMAL(8,2) NULL,
  source        ENUM('usda','ai','manual') NOT NULL DEFAULT 'usda',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  FULLTEXT idx_search (name, brand)
) ENGINE=InnoDB;

-- ── Registro diário de alimentos consumidos ───────────────────
CREATE TABLE IF NOT EXISTS food_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  food_id    INT UNSIGNED NULL COMMENT 'NULL se vier da IA sem salvar no cache',
  food_name  VARCHAR(200) NOT NULL COMMENT 'cópia do nome no momento do registro',
  qty_g      DECIMAL(8,2) NOT NULL,
  unit       VARCHAR(10)  NOT NULL DEFAULT 'g',
  cal        DECIMAL(8,2) NOT NULL,
  prot       DECIMAL(8,2) NOT NULL DEFAULT 0,
  carb       DECIMAL(8,2) NOT NULL DEFAULT 0,
  fat        DECIMAL(8,2) NOT NULL DEFAULT 0,
  fiber      DECIMAL(8,2) NULL,
  sugar      DECIMAL(8,2) NULL,
  sodium     DECIMAL(8,2) NULL,
  sat_fat    DECIMAL(8,2) NULL,
  meal_type  ENUM('breakfast','lunch','dinner','snack') NOT NULL DEFAULT 'snack',
  logged_at  DATE         NOT NULL DEFAULT (CURDATE()),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (food_id) REFERENCES foods(id) ON DELETE SET NULL,
  INDEX idx_user_date (user_id, logged_at)
) ENGINE=InnoDB;

-- ── Metas e histórico de peso ─────────────────────────────────
CREATE TABLE IF NOT EXISTS weight_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  weight_kg  DECIMAL(5,2) NOT NULL,
  logged_at  DATE NOT NULL DEFAULT (CURDATE()),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_date (user_id, logged_at)
) ENGINE=InnoDB;

-- ── Sessões PHP (alternativa a session files) ─────────────────
CREATE TABLE IF NOT EXISTS sessions (
  session_id   VARCHAR(128) PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  ip_address   VARCHAR(45) NULL,
  user_agent   VARCHAR(300) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at   TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ── Dados de exemplo (alimentos básicos brasileiros) ──────────
INSERT IGNORE INTO foods (name, cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g, fiber_per_100g, sodium_per_100g, source) VALUES
('Arroz branco cozido',      130, 2.7,  28.1, 0.3, 0.2, 1.0,  'manual'),
('Feijão carioca cozido',    77,  4.8,  13.6, 0.5, 8.4, 2.0,  'manual'),
('Frango grelhado (peito)',  165, 31.0, 0.0,  3.6, 0.0, 74.0, 'manual'),
('Ovo cozido inteiro',       155, 13.0, 1.1,  11.0,0.0, 124.0,'manual'),
('Banana prata',             89,  1.1,  23.0, 0.3, 2.6, 1.0,  'manual'),
('Maçã fuji',                52,  0.3,  14.0, 0.2, 2.4, 1.0,  'manual'),
('Leite integral',           61,  3.2,  4.7,  3.3, 0.0, 44.0, 'manual'),
('Pão francês',              300, 8.0,  57.0, 3.5, 2.3, 540.0,'manual'),
('Batata doce cozida',       86,  1.6,  20.1, 0.1, 3.0, 27.0, 'manual'),
('Aveia em flocos',          389, 16.9, 66.3, 6.9, 10.6,2.0,  'manual'),
('Iogurte natural integral', 61,  3.5,  4.7,  3.3, 0.0, 46.0, 'manual'),
('Whey Protein (pó)',        400, 80.0, 8.0,  4.0, 0.0, 200.0,'manual'),
('Azeite de oliva',          884, 0.0,  0.0,  100.0,0.0,2.0,  'manual'),
('Salmão grelhado',          208, 20.4, 0.0,  13.4, 0.0,59.0, 'manual'),
('Alface',                   15,  1.4,  2.9,  0.2, 1.3, 10.0, 'manual'),
('Tomate',                   18,  0.9,  3.9,  0.2, 1.2, 5.0,  'manual'),
('Queijo minas frescal',     264, 17.4, 2.4,  20.2, 0.0,388.0,'manual'),
('Macarrão cozido',          158, 5.8,  30.9, 0.9, 1.8, 1.0,  'manual'),
('Café sem açúcar',          2,   0.3,  0.0,  0.0, 0.0, 5.0,  'manual'),
('Suco de laranja natural',  45,  0.7,  10.4, 0.2, 0.2, 1.0,  'manual');
