-- Tabel masukan dan pengaduan.
-- Jalankan sekali di database yang sudah ada:
-- phpMyAdmin -> pilih database lemariku -> tab Import -> pilih file ini -> Import

USE lemariku;

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
