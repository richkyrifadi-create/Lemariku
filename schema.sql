CREATE DATABASE IF NOT EXISTS lemariku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lemariku;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name) VALUES
  ('Atasan'), ('Bawahan'), ('Dress'), ('Outerwear'), ('Sepatu'), ('Aksesoris');

CREATE TABLE IF NOT EXISTS items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  brand        VARCHAR(80) NULL,
  color        VARCHAR(40) NULL,
  season       VARCHAR(20) NOT NULL DEFAULT 'Semua musim',
  price        DECIMAL(12,2) NULL,
  purchased_at DATE NULL,
  image        VARCHAR(64) NULL,
  is_laundry   TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_items_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wear_logs (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id  INT UNSIGNED NOT NULL,
  worn_on  DATE NOT NULL,
  UNIQUE KEY uq_wear_item_day (item_id, worn_on),
  CONSTRAINT fk_wear_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS outfits (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(100) NOT NULL,
  notes      TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_outfits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category   VARCHAR(40) NOT NULL,
  name       VARCHAR(100) NULL,
  email      VARCHAR(150) NULL,
  subject    VARCHAR(100) NOT NULL,
  message    TEXT NOT NULL,
  image      VARCHAR(64) NULL,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_feedback_ip_time (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS outfit_items (
  outfit_id INT UNSIGNED NOT NULL,
  item_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (outfit_id, item_id),
  CONSTRAINT fk_oi_outfit FOREIGN KEY (outfit_id) REFERENCES outfits(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;
