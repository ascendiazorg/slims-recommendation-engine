-- ============================================================
-- PLUGIN RECOMMENDATION ENGINE — SLIMS
-- Jalankan file ini SEKALI di phpMyAdmin atau MySQL CLI:
--   mysql -u root slims < install.sql
-- Tidak mengubah tabel SLIMS yang sudah ada.
-- ============================================================

USE slims;   -- ← ganti dengan nama database SLIMS kamu

-- ── Tabel similarity CBF (Cosine Similarity antar buku) ─────
CREATE TABLE IF NOT EXISTS `rec_cbf_similarity` (
  `id`          int(11)   NOT NULL AUTO_INCREMENT,
  `biblio_id_a` int(11)   NOT NULL,
  `biblio_id_b` int(11)   NOT NULL,
  `similarity`  float     NOT NULL,
  `computed_at` datetime  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cbf_pair` (`biblio_id_a`, `biblio_id_b`),
  KEY `idx_cbf_a` (`biblio_id_a`),
  KEY `idx_cbf_b` (`biblio_id_b`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- ── Tabel similarity CF (Jaccard Similarity antar buku) ─────
CREATE TABLE IF NOT EXISTS `rec_cf_similarity` (
  `id`          int(11)   NOT NULL AUTO_INCREMENT,
  `biblio_id_a` int(11)   NOT NULL,
  `biblio_id_b` int(11)   NOT NULL,
  `similarity`  float     NOT NULL,
  `computed_at` datetime  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cf_pair` (`biblio_id_a`, `biblio_id_b`),
  KEY `idx_cf_a` (`biblio_id_a`),
  KEY `idx_cf_b` (`biblio_id_b`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- ── Tabel log mining ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rec_mining_log` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT,
  `algorithm`   varchar(20) NOT NULL,
  `started_at`  datetime    NOT NULL,
  `finished_at` datetime    DEFAULT NULL,
  `total_pairs` int(11)     DEFAULT 0,
  `status`      varchar(20) DEFAULT 'running',
  `notes`       text        DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- ── Tabel similarity IBCF (Item-Based CF, Jaccard Similarity) ─
CREATE TABLE IF NOT EXISTS `rec_ibcf_similarity` (
  `id`          int(11)  NOT NULL AUTO_INCREMENT,
  `biblio_id_a` int(11)  NOT NULL,
  `biblio_id_b` int(11)  NOT NULL,
  `similarity`  float    NOT NULL,
  `computed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ibcf_pair` (`biblio_id_a`, `biblio_id_b`),
  KEY `idx_ibcf_a` (`biblio_id_a`, `similarity`),
  KEY `idx_ibcf_b` (`biblio_id_b`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- ── Tabel cache URL sampul buku ──────────────────────────────
CREATE TABLE IF NOT EXISTS `rec_cover_cache` (
  `title_hash` varchar(32)  NOT NULL,
  `cover_url`  varchar(500) NOT NULL,
  `cached_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`title_hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ── Tabel log view OPAC (opsional) ──────────────────────────
CREATE TABLE IF NOT EXISTS `rec_view_log` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `member_id`   varchar(20)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `biblio_id`   int(11)      NOT NULL,
  `session_id`  varchar(64)  COLLATE utf8_unicode_ci NOT NULL,
  `ip_address`  varchar(45)  DEFAULT NULL,
  `viewed_at`   datetime     DEFAULT CURRENT_TIMESTAMP,
  `duration_ms` int(11)      DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_biblio` (`biblio_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- ── Tambah kolom rating ke tabel comment (opsional) ─────────
-- ALTER TABLE `comment`
--   ADD COLUMN IF NOT EXISTS `rating` tinyint(1) DEFAULT NULL
--   AFTER `comment`;

-- Verifikasi
SHOW TABLES LIKE 'rec_%';
