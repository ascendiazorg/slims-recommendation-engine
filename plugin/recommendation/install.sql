-- ============================================================
-- Recommendation Engine Plugin - Database Setup
-- Jalankan sekali via phpMyAdmin atau MySQL CLI
-- ============================================================

-- Tabel CF Similarity
CREATE TABLE IF NOT EXISTS `rec_cf_similarity` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `biblio_id_a` INT NOT NULL,
  `biblio_id_b` INT NOT NULL,
  `similarity`  FLOAT NOT NULL DEFAULT 0,
  `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_pair`  (`biblio_id_a`, `biblio_id_b`),
  INDEX `idx_a_sim`         (`biblio_id_a`, `similarity`),
  INDEX `idx_b_sim`         (`biblio_id_b`, `similarity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel CBF Similarity
CREATE TABLE IF NOT EXISTS `rec_cbf_similarity` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `biblio_id_a` INT NOT NULL,
  `biblio_id_b` INT NOT NULL,
  `similarity`  FLOAT NOT NULL DEFAULT 0,
  `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_pair`  (`biblio_id_a`, `biblio_id_b`),
  INDEX `idx_a_sim`         (`biblio_id_a`, `similarity`),
  INDEX `idx_b_sim`         (`biblio_id_b`, `similarity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Log Mining
CREATE TABLE IF NOT EXISTS `rec_mining_log` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `algorithm`        VARCHAR(20) NOT NULL,
  `status`           VARCHAR(20) NOT NULL DEFAULT 'running',
  `records_created`  INT DEFAULT 0,
  `duration_seconds` FLOAT DEFAULT 0,
  `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_algorithm` (`algorithm`),
  INDEX `idx_log_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index tambahan untuk tabel SLIMS loan_history
-- (jalankan hanya jika belum ada — tidak merusak data apapun)
ALTER TABLE `loan_history`
  ADD INDEX IF NOT EXISTS `idx_lh_member_return` (`member_id`, `is_return`),
  ADD INDEX IF NOT EXISTS `idx_lh_biblio`        (`biblio_id`),
  ADD INDEX IF NOT EXISTS `idx_lh_return`        (`is_return`);
