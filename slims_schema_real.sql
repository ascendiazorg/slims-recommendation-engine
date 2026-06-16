-- ============================================================
-- SLIMS 9 BULIAN — SCHEMA ASLI (dari GitHub slims/slims9_bulian)
-- + TABEL BARU untuk Plugin Recommendation Engine
-- ============================================================
-- Dibuat berdasarkan: https://github.com/slims/slims9_bulian
-- File sumber     : /install/senayan.sql
-- ============================================================

-- ┌─────────────────────────────────────────────────────────┐
-- │  PENTING — PERBEDAAN DENGAN SIMULASI DI todoSLIMS.md   │
-- │                                                         │
-- │  Simulasi          │  SLIMS Asli                       │
-- │  ─────────────     │  ───────────                      │
-- │  biblio_item       │  item  (nama tabel beda!)         │
-- │  biblio.subject    │  TIDAK ADA — via JOIN mst_topic   │
-- │  biblio.author     │  TIDAK ADA — via JOIN mst_author  │
-- │  member_id INT     │  member_id VARCHAR(20)            │
-- │  loan.member_id INT│  loan.member_id VARCHAR(20)       │
-- └─────────────────────────────────────────────────────────┘

-- ============================================================
-- BAGIAN 1: TABEL SLIMS YANG SUDAH ADA (JANGAN DIUBAH)
-- Hanya untuk referensi — sudah ada saat install SLIMS
-- ============================================================

-- Tabel utama buku/koleksi
-- biblio.classification = nomor DDC (000, 005, 519, dll)
-- biblio TIDAK punya kolom subject atau author langsung!
CREATE TABLE IF NOT EXISTS `biblio` (
  `biblio_id`       int(11)       NOT NULL AUTO_INCREMENT,
  `gmd_id`          int(3)        DEFAULT NULL,
  `title`           text          COLLATE utf8_unicode_ci NOT NULL,
  `sor`             varchar(200)  COLLATE utf8_unicode_ci DEFAULT NULL,   -- statement of responsibility
  `edition`         varchar(50)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `isbn_issn`       varchar(32)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `publisher_id`    int(11)       DEFAULT NULL,
  `publish_year`    varchar(20)   DEFAULT NULL,
  `collation`       varchar(50)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `series_title`    varchar(200)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `call_number`     varchar(50)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `language_id`     char(5)       COLLATE utf8_unicode_ci DEFAULT 'en',
  `source`          varchar(3)    COLLATE utf8_unicode_ci DEFAULT NULL,
  `publish_place_id` int(11)      DEFAULT NULL,
  `classification`  varchar(40)   COLLATE utf8_unicode_ci DEFAULT NULL,   -- DDC number!
  `notes`           text          COLLATE utf8_unicode_ci,
  `image`           varchar(100)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `file_att`        varchar(255)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `opac_hide`       smallint(1)   DEFAULT 0,
  `promoted`        smallint(1)   DEFAULT 0,
  `labels`          text          COLLATE utf8_unicode_ci,
  `frequency_id`    int(11)       NOT NULL DEFAULT 0,
  `spec_detail_info` text         COLLATE utf8_unicode_ci,
  `content_type_id` int(11)       DEFAULT NULL,
  `media_type_id`   int(11)       DEFAULT NULL,
  `carrier_type_id` int(11)       DEFAULT NULL,
  `input_date`      datetime      DEFAULT NULL,
  `last_update`     datetime      DEFAULT NULL,
  `uid`             int(11)       DEFAULT NULL,
  PRIMARY KEY (`biblio_id`),
  KEY `classification` (`classification`),
  KEY `biblio_flag_idx` (`opac_hide`, `promoted`),
  FULLTEXT KEY `title_ft_idx` (`title`, `series_title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- *** NAMA TABEL ADALAH "item" BUKAN "biblio_item" ***
CREATE TABLE IF NOT EXISTS `item` (
  `item_id`         int(11)       NOT NULL AUTO_INCREMENT,
  `biblio_id`       int(11)       DEFAULT NULL,     -- FK ke biblio.biblio_id
  `call_number`     varchar(50)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `coll_type_id`    int(3)        DEFAULT NULL,
  `item_code`       varchar(20)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `inventory_code`  varchar(200)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `received_date`   date          DEFAULT NULL,
  `supplier_id`     varchar(6)    COLLATE utf8_unicode_ci DEFAULT NULL,
  `location_id`     varchar(3)    COLLATE utf8_unicode_ci DEFAULT NULL,
  `item_status_id`  char(3)       COLLATE utf8_unicode_ci DEFAULT NULL,
  `source`          int(1)        NOT NULL DEFAULT '0',
  `price`           int(11)       DEFAULT NULL,
  `input_date`      datetime      NOT NULL,
  `last_update`     datetime      DEFAULT NULL,
  `uid`             int(11)       DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `biblio_id_idx` (`biblio_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- *** member_id adalah VARCHAR(20), BUKAN INT ***
CREATE TABLE IF NOT EXISTS `member` (
  `member_id`           varchar(20)   COLLATE utf8_unicode_ci NOT NULL,  -- VARCHAR bukan INT!
  `member_name`         varchar(100)  COLLATE utf8_unicode_ci NOT NULL,
  `gender`              int(1)        NOT NULL,
  `birth_date`          date          DEFAULT NULL,
  `member_type_id`      int(6)        DEFAULT NULL,
  `member_address`      varchar(255)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_email`        varchar(100)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `inst_name`           varchar(100)  COLLATE utf8_unicode_ci DEFAULT NULL,  -- nama jurusan/institusi
  `is_new`              int(1)        DEFAULT NULL,
  `member_image`        varchar(200)  COLLATE utf8_unicode_ci DEFAULT NULL,
  `pin`                 varchar(50)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_phone`        varchar(50)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `member_since_date`   date          DEFAULT NULL,
  `expire_date`         date          NOT NULL,
  `member_notes`        text          COLLATE utf8_unicode_ci,
  `is_pending`          smallint(1)   NOT NULL DEFAULT '0',
  `mpasswd`             varchar(64)   DEFAULT NULL,
  `last_login`          datetime      DEFAULT NULL,
  `last_login_ip`       varchar(20)   DEFAULT NULL,
  `input_date`          date          DEFAULT NULL,
  `last_update`         date          DEFAULT NULL,
  PRIMARY KEY (`member_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- *** loan.member_id juga VARCHAR(20), bukan INT ***
CREATE TABLE IF NOT EXISTS `loan` (
  `loan_id`       int(11)       NOT NULL AUTO_INCREMENT,
  `item_code`     varchar(20)   COLLATE utf8_unicode_ci DEFAULT NULL,  -- FK ke item.item_code
  `member_id`     varchar(20)   COLLATE utf8_unicode_ci DEFAULT NULL,  -- FK ke member.member_id (VARCHAR!)
  `loan_date`     date          NOT NULL,
  `due_date`      date          NOT NULL,
  `renewed`       int(11)       NOT NULL DEFAULT '0',
  `loan_rules_id` int(11)       NOT NULL DEFAULT '0',
  `is_lent`       int(11)       NOT NULL DEFAULT '0',
  `is_return`     int(11)       NOT NULL DEFAULT '0',
  `return_date`   date          DEFAULT NULL,
  `input_date`    datetime      DEFAULT NULL,
  `last_update`   datetime      DEFAULT NULL,
  `uid`           int(11)       DEFAULT NULL,
  PRIMARY KEY (`loan_id`),
  KEY `item_code` (`item_code`),
  KEY `member_id` (`member_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- Subject/topik buku disimpan terpisah di 2 tabel ini
-- biblio TIDAK punya kolom subject!
CREATE TABLE IF NOT EXISTS `mst_topic` (
  `topic_id`      int(11)       NOT NULL AUTO_INCREMENT,
  `topic`         varchar(50)   COLLATE utf8_unicode_ci NOT NULL,
  `topic_type`    enum('t','g','n','tm','gr','oc') COLLATE utf8_unicode_ci NOT NULL,
  `auth_list`     varchar(20)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `classification` varchar(50)  COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `input_date`    date          DEFAULT NULL,
  `last_update`   date          DEFAULT NULL,
  PRIMARY KEY (`topic_id`),
  UNIQUE KEY `topic` (`topic`, `topic_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `biblio_topic` (
  `biblio_id`     int(11)       NOT NULL DEFAULT '0',
  `topic_id`      int(11)       NOT NULL DEFAULT '0',
  `level`         int(1)        NOT NULL DEFAULT '1',
  PRIMARY KEY (`biblio_id`, `topic_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Pengarang juga disimpan terpisah
CREATE TABLE IF NOT EXISTS `mst_author` (
  `author_id`     int(11)       NOT NULL AUTO_INCREMENT,
  `author_name`   varchar(100)  COLLATE utf8_unicode_ci NOT NULL,
  `author_year`   varchar(30)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `auth_list`     varchar(20)   COLLATE utf8_unicode_ci DEFAULT NULL,
  `input_date`    date          DEFAULT NULL,
  `last_update`   date          DEFAULT NULL,
  PRIMARY KEY (`author_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `biblio_author` (
  `biblio_id`     int(11)       NOT NULL DEFAULT '0',
  `author_id`     int(11)       NOT NULL DEFAULT '0',
  `authority_type` char(1)      COLLATE utf8_unicode_ci NOT NULL DEFAULT 'p',
  `level`         int(1)        NOT NULL DEFAULT '1',
  PRIMARY KEY (`biblio_id`, `author_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Komentar (tidak ada kolom rating di schema asli SLIMS)
CREATE TABLE IF NOT EXISTS `comment` (
  `comment_id`    int(11)       NOT NULL AUTO_INCREMENT,
  `biblio_id`     int(11)       NOT NULL,
  `member_id`     varchar(20)   COLLATE utf8_unicode_ci NOT NULL,  -- VARCHAR!
  `comment`       text          COLLATE utf8_unicode_ci NOT NULL,
  `input_date`    datetime      DEFAULT NULL,
  `last_update`   datetime      DEFAULT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;


-- ============================================================
-- BAGIAN 2: QUERY YANG BENAR UNTUK AKSES DATA SLIMS
-- ============================================================

-- ── 2A. Ambil buku + topik + pengarang (untuk CBF) ──────────
-- Gunakan query ini di ContentBasedFilter.php (bukan SELECT subject FROM biblio)

SELECT
    b.biblio_id,
    b.title,
    b.classification,                                          -- nomor DDC
    COALESCE(
        GROUP_CONCAT(DISTINCT t.topic    ORDER BY t.topic    SEPARATOR ' '), ''
    ) AS subject,                                              -- dari mst_topic via JOIN
    COALESCE(
        GROUP_CONCAT(DISTINCT a.author_name ORDER BY a.author_name SEPARATOR ' '), ''
    ) AS author                                                -- dari mst_author via JOIN
FROM biblio b
LEFT JOIN biblio_topic  bt ON b.biblio_id  = bt.biblio_id
LEFT JOIN mst_topic      t  ON bt.topic_id  = t.topic_id
LEFT JOIN biblio_author ba  ON b.biblio_id  = ba.biblio_id
LEFT JOIN mst_author     a  ON ba.author_id = a.author_id
WHERE b.opac_hide = 0
GROUP BY b.biblio_id, b.title, b.classification
ORDER BY b.biblio_id
LIMIT 5000;


-- ── 2B. Ambil riwayat pinjaman (untuk CF) ───────────────────
-- Harus join loan → item → biblio (BUKAN langsung loan → biblio_item)

SELECT
    l.member_id,                            -- VARCHAR(20)
    i.biblio_id,
    l.loan_date,
    l.is_return
FROM loan l
JOIN item i ON l.item_code = i.item_code   -- item, BUKAN biblio_item!
WHERE l.is_return = 1                       -- hanya yang sudah dikembalikan (bukan masih dipinjam)
ORDER BY l.member_id, l.loan_date;


-- ── 2C. Cek peminjam suatu buku (Jaccard CF) ────────────────

SELECT DISTINCT l.member_id
FROM loan l
JOIN item i ON l.item_code = i.item_code
WHERE i.biblio_id = 42                      -- ganti dengan biblio_id yang dicari
  AND l.is_return = 1;


-- ── 2D. Buku yang sering dipinjam bersama (co-borrowing) ────

SELECT
    i2.biblio_id          AS book_b,
    COUNT(DISTINCT l2.member_id) AS shared_borrowers
FROM loan l1
JOIN item  i1  ON l1.item_code  = i1.item_code
JOIN loan  l2  ON l1.member_id  = l2.member_id AND l2.loan_id != l1.loan_id
JOIN item  i2  ON l2.item_code  = i2.item_code
WHERE i1.biblio_id = 42                     -- target book
  AND i2.biblio_id != 42
  AND l1.is_return = 1
  AND l2.is_return = 1
GROUP BY i2.biblio_id
HAVING shared_borrowers >= 2
ORDER BY shared_borrowers DESC
LIMIT 20;


-- ── 2E. Jaccard Similarity via SQL murni ────────────────────

SELECT
    i1.biblio_id AS book_a,
    i2.biblio_id AS book_b,
    COUNT(DISTINCT l1.member_id) AS irisan,
    (
        SELECT COUNT(DISTINCT lx.member_id)
        FROM loan lx JOIN item ix ON lx.item_code = ix.item_code
        WHERE ix.biblio_id = i1.biblio_id AND lx.is_return = 1
    ) +
    (
        SELECT COUNT(DISTINCT ly.member_id)
        FROM loan ly JOIN item iy ON ly.item_code = iy.item_code
        WHERE iy.biblio_id = i2.biblio_id AND ly.is_return = 1
    ) - COUNT(DISTINCT l1.member_id) AS gabungan,
    ROUND(
        COUNT(DISTINCT l1.member_id) /
        NULLIF((
            (SELECT COUNT(DISTINCT lx.member_id) FROM loan lx JOIN item ix ON lx.item_code = ix.item_code WHERE ix.biblio_id = i1.biblio_id AND lx.is_return = 1)
          + (SELECT COUNT(DISTINCT ly.member_id) FROM loan ly JOIN item iy ON ly.item_code = iy.item_code WHERE iy.biblio_id = i2.biblio_id AND ly.is_return = 1)
          - COUNT(DISTINCT l1.member_id)
        ), 0)
    , 4) AS jaccard_score
FROM loan l1
JOIN item i1 ON l1.item_code = i1.item_code
JOIN loan l2 ON l1.member_id = l2.member_id AND l2.loan_id != l1.loan_id
JOIN item i2 ON l2.item_code = i2.item_code
WHERE l1.is_return = 1
  AND l2.is_return = 1
  AND i1.biblio_id < i2.biblio_id         -- hindari duplikat (A,B) dan (B,A)
GROUP BY i1.biblio_id, i2.biblio_id
HAVING irisan >= 2
ORDER BY jaccard_score DESC;


-- ── 2F. Data member + jurusan (untuk Cold Start) ────────────

SELECT
    m.member_id,
    m.member_name,
    m.inst_name,                            -- nama jurusan/institusi
    COUNT(l.loan_id) AS total_pinjaman
FROM member m
LEFT JOIN loan l ON m.member_id = l.member_id
GROUP BY m.member_id, m.member_name, m.inst_name
ORDER BY total_pinjaman DESC;


-- ============================================================
-- BAGIAN 3: TABEL BARU UNTUK PLUGIN RECOMMENDATION
-- Jalankan ini SEKALI saat install plugin
-- Tidak mengubah tabel SLIMS yang sudah ada
-- ============================================================

USE slims;   -- ganti dengan nama database SLIMS kamu

-- ── 3A. Tabel similarity CBF ────────────────────────────────
CREATE TABLE IF NOT EXISTS `rec_cbf_similarity` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT,
  `biblio_id_a` int(11)     NOT NULL,        -- FK ke biblio.biblio_id (INT)
  `biblio_id_b` int(11)     NOT NULL,        -- FK ke biblio.biblio_id (INT)
  `similarity`  float       NOT NULL,        -- hasil cosine similarity (0.0–1.0)
  `computed_at` datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cbf_pair` (`biblio_id_a`, `biblio_id_b`),
  KEY `idx_cbf_a` (`biblio_id_a`),
  KEY `idx_cbf_b` (`biblio_id_b`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;


-- ── 3B. Tabel similarity CF ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `rec_cf_similarity` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT,
  `biblio_id_a` int(11)     NOT NULL,        -- FK ke biblio.biblio_id (INT)
  `biblio_id_b` int(11)     NOT NULL,        -- FK ke biblio.biblio_id (INT)
  `similarity`  float       NOT NULL,        -- hasil jaccard similarity (0.0–1.0)
  `computed_at` datetime    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cf_pair` (`biblio_id_a`, `biblio_id_b`),
  KEY `idx_cf_a` (`biblio_id_a`),
  KEY `idx_cf_b` (`biblio_id_b`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;


-- ── 3C. Tabel log mining ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rec_mining_log` (
  `id`          int(11)     NOT NULL AUTO_INCREMENT,
  `algorithm`   varchar(20) NOT NULL,        -- 'CBF', 'CF', 'HYBRID'
  `started_at`  datetime    NOT NULL,
  `finished_at` datetime    DEFAULT NULL,
  `total_pairs` int(11)     DEFAULT 0,
  `status`      varchar(20) DEFAULT 'running',  -- 'running', 'done', 'error'
  `notes`       text        DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;


-- ── 3D. Tabel mapping jurusan → DDC (Cold Start) ────────────
CREATE TABLE IF NOT EXISTS `rec_major_ddc` (
  `id`            int(11)       NOT NULL AUTO_INCREMENT,
  `major_keyword` varchar(100)  COLLATE utf8_unicode_ci NOT NULL,  -- kata kunci jurusan
  `ddc_start`     int(11)       NOT NULL,
  `ddc_end`       int(11)       NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_keyword` (`major_keyword`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;

-- Data awal mapping DDC (sesuai dengan nilai inst_name di tabel member SLIMS)
INSERT IGNORE INTO `rec_major_ddc` (`major_keyword`, `ddc_start`, `ddc_end`) VALUES
('Informatika',      0,   99),
('Komputer',         0,   99),
('Ilmu Komputer',    0,   99),
('Sistem Informasi', 0,   99),
('Teknik Elektro',  600, 699),
('Manajemen',       650, 659),
('Akuntansi',       650, 659),
('Ekonomi',         330, 339),
('Bisnis',          650, 659),
('Hukum',           340, 349),
('Kedokteran',      610, 619),
('Keperawatan',     610, 619),
('Farmasi',         615, 615),
('Psikologi',       150, 159),
('Pendidikan',      370, 379),
('Pertanian',       630, 639),
('Matematika',      510, 519),
('Statistika',      510, 519),
('Fisika',          530, 539),
('Kimia',           540, 549),
('Biologi',         570, 579),
('Teknik Sipil',    620, 629),
('Teknik Mesin',    620, 629),
('Komunikasi',      300, 309),
('Sastra',          800, 899),
('Sejarah',         900, 999);


-- ── 3E. Tabel view log (tracking halaman OPAC dibuka) ────────
-- SLIMS tidak punya ini — tabel baru sepenuhnya
CREATE TABLE IF NOT EXISTS `rec_view_log` (
  `id`          int(11)       NOT NULL AUTO_INCREMENT,
  `member_id`   varchar(20)   COLLATE utf8_unicode_ci DEFAULT NULL,  -- VARCHAR(20) sesuai SLIMS!
  `biblio_id`   int(11)       NOT NULL,
  `session_id`  varchar(64)   COLLATE utf8_unicode_ci NOT NULL,
  `ip_address`  varchar(45)   DEFAULT NULL,
  `viewed_at`   datetime      DEFAULT CURRENT_TIMESTAMP,
  `duration_ms` int(11)       DEFAULT 0,
  `source`      enum('opac_detail','opac_search','recommendation','direct')
                              DEFAULT 'direct',
  PRIMARY KEY (`id`),
  KEY `idx_member`  (`member_id`),
  KEY `idx_biblio`  (`biblio_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_viewed`  (`viewed_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;


-- ── 3F. Tambah kolom rating ke tabel comment SLIMS ───────────
-- Jalankan sekali — ALTER TABLE aman karena hanya TAMBAH kolom baru
ALTER TABLE `comment`
  ADD COLUMN `rating` tinyint(1) DEFAULT NULL COMMENT '1-5 stars, NULL = belum kasih rating'
  AFTER `comment`;


-- ============================================================
-- BAGIAN 4: VERIFIKASI — cek semua tabel berhasil dibuat
-- ============================================================

SHOW TABLES LIKE 'rec_%';
-- Harusnya muncul:
--   rec_cbf_similarity
--   rec_cf_similarity
--   rec_major_ddc
--   rec_mining_log
--   rec_view_log

SHOW COLUMNS FROM rec_cbf_similarity;
SHOW COLUMNS FROM rec_cf_similarity;
SHOW COLUMNS FROM rec_major_ddc;
SHOW COLUMNS FROM rec_view_log;

-- Cek data mapping DDC berhasil masuk
SELECT * FROM rec_major_ddc ORDER BY ddc_start;

-- Cek kolom rating sudah ada di comment
SHOW COLUMNS FROM comment LIKE 'rating';


-- ============================================================
-- BAGIAN 5: QUERY DIAGNOSTIK — cek data di SLIMS
-- (jalankan setelah SLIMS terisi data)
-- ============================================================

-- Berapa buku yang ada?
SELECT COUNT(*) AS total_buku FROM biblio WHERE opac_hide = 0;

-- Berapa anggota aktif?
SELECT COUNT(*) AS total_member
FROM member
WHERE expire_date >= CURDATE() AND is_pending = 0;

-- Berapa transaksi pinjaman?
SELECT
    COUNT(*)             AS total_transaksi,
    SUM(is_return = 1)   AS sudah_kembali,
    SUM(is_return = 0)   AS masih_dipinjam
FROM loan;

-- Berapa buku yang punya topik (untuk CBF)?
SELECT COUNT(DISTINCT biblio_id) AS buku_ada_topik FROM biblio_topic;

-- Berapa buku yang belum punya topik (mungkin data kosong)?
SELECT COUNT(*) AS buku_tanpa_topik
FROM biblio b
WHERE b.opac_hide = 0
  AND NOT EXISTS (
      SELECT 1 FROM biblio_topic bt WHERE bt.biblio_id = b.biblio_id
  );
