-- ============================================================
-- SIMULASI TABEL QUERY — RECOMMENDATION SYSTEM UNTUK SLIMS
-- ============================================================
-- Tabel ini menambah struktur database SLIMS yang sudah ada
-- TIDAK mengubah tabel inti SLIMS (biblio, loan, member, dll)
--
-- Jalankan di phpMyAdmin atau MySQL CLI:
-- mysql -u root -p slims9 < tabel_query.sql
-- ============================================================


-- ============================================================
-- A. DATA DUMMY — Simulasi isi tabel biblio SLIMS
--    (sudah ada di SLIMS, ini hanya untuk testing)
-- ============================================================

-- Hapus data dummy lama jika ada
DELETE FROM biblio WHERE biblio_id BETWEEN 1001 AND 1010;

INSERT INTO biblio (biblio_id, title, subject, classification, opac_hide) VALUES
(1001, 'Pemrograman Python untuk Pemula',    'python pemrograman komputer coding',           '005.13', 0),
(1002, 'Machine Learning dengan Python',     'machine learning python kecerdasan buatan',    '006.31', 0),
(1003, 'Deep Learning dan Neural Network',   'deep learning neural network kecerdasan buatan','006.32', 0),
(1004, 'Algoritma dan Struktur Data',        'algoritma struktur data pemrograman komputer', '005.1',  0),
(1005, 'Basis Data MySQL untuk Web',         'database mysql basis data web server',         '005.74', 0),
(1006, 'Jaringan Komputer dan Keamanan',     'jaringan komputer keamanan network',           '004.6',  0),
(1007, 'Pemrograman Web dengan PHP',         'php web pemrograman coding server',            '005.13', 0),
(1008, 'Data Science dan Analitik',          'data science analitik statistik python',       '006.31', 0),
(1009, 'Sistem Operasi Linux',               'linux sistem operasi komputer server',         '005.43', 0),
(1010, 'Kecerdasan Buatan Teori dan Praktik','kecerdasan buatan artificial intelligence',   '006.3',  0);


-- ============================================================
-- B. DATA DUMMY — Simulasi riwayat pinjaman (tabel loan)
--    4 user dengan pola pinjaman berbeda
-- ============================================================

-- Budi  (member_id=1): Python, ML, Algoritma
-- Ani   (member_id=2): Python, ML, Deep Learning, Data Science
-- Reza  (member_id=3): Algoritma, MySQL, PHP
-- Sari  (member_id=4): ML, Deep Learning, AI

INSERT INTO loan (item_code, member_id, loan_date, due_date) VALUES
-- Budi
('BC1001', 1, '2025-01-10', '2025-01-24'),
('BC1002', 1, '2025-01-15', '2025-01-29'),
('BC1004', 1, '2025-02-01', '2025-02-15'),
-- Ani
('BC1001', 2, '2025-01-12', '2025-01-26'),
('BC1002', 2, '2025-01-18', '2025-02-01'),
('BC1003', 2, '2025-02-05', '2025-02-19'),
('BC1008', 2, '2025-02-10', '2025-02-24'),
-- Reza
('BC1004', 3, '2025-01-20', '2025-02-03'),
('BC1005', 3, '2025-02-01', '2025-02-15'),
('BC1007', 3, '2025-02-12', '2025-02-26'),
-- Sari
('BC1002', 4, '2025-01-25', '2025-02-08'),
('BC1003', 4, '2025-02-03', '2025-02-17'),
('BC1010', 4, '2025-02-15', '2025-03-01');


-- ============================================================
-- C. TABEL BARU — Hasil Mining CBF
--    Menyimpan Cosine Similarity antar buku berdasarkan metadata
-- ============================================================

CREATE TABLE IF NOT EXISTS rec_cbf_similarity (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    biblio_id_a  INT NOT NULL,
    biblio_id_b  INT NOT NULL,
    similarity   FLOAT NOT NULL COMMENT 'Nilai Cosine Similarity 0.0-1.0',
    computed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cbf_pair (biblio_id_a, biblio_id_b),
    INDEX idx_cbf_a (biblio_id_a),
    INDEX idx_cbf_b (biblio_id_b)
) COMMENT 'Hasil mining Content-Based Filtering';


-- ============================================================
-- D. TABEL BARU — Hasil Mining CF
--    Menyimpan Jaccard Similarity antar buku dari pola pinjam
-- ============================================================

CREATE TABLE IF NOT EXISTS rec_cf_similarity (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    biblio_id_a  INT NOT NULL,
    biblio_id_b  INT NOT NULL,
    similarity   FLOAT NOT NULL COMMENT 'Nilai Jaccard Similarity 0.0-1.0',
    computed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cf_pair (biblio_id_a, biblio_id_b),
    INDEX idx_cf_a (biblio_id_a),
    INDEX idx_cf_b (biblio_id_b)
) COMMENT 'Hasil mining Collaborative Filtering';


-- ============================================================
-- E. TABEL BARU — Log Mining
--    Mencatat kapan mining dijalankan dan hasilnya
-- ============================================================

CREATE TABLE IF NOT EXISTS rec_mining_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    algorithm    VARCHAR(20) NOT NULL COMMENT 'CBF atau CF atau HYBRID',
    started_at   DATETIME NOT NULL,
    finished_at  DATETIME,
    total_pairs  INT DEFAULT 0,
    status       VARCHAR(20) DEFAULT 'running'
        COMMENT 'running | done | failed'
) COMMENT 'Log eksekusi mining algoritma rekomendasi';


-- ============================================================
-- F. TABEL BARU — Mapping Jurusan ke DDC (Cold Start)
--    Digunakan untuk merekomendasikan ke user baru
-- ============================================================

CREATE TABLE IF NOT EXISTS rec_major_ddc (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    major_keyword VARCHAR(100) NOT NULL COMMENT 'Kata kunci jurusan',
    ddc_start     INT NOT NULL COMMENT 'Awal range DDC',
    ddc_end       INT NOT NULL COMMENT 'Akhir range DDC',
    INDEX idx_keyword (major_keyword)
) COMMENT 'Mapping nama jurusan ke range DDC untuk cold start';

-- Isi data mapping DDC
INSERT INTO rec_major_ddc (major_keyword, ddc_start, ddc_end) VALUES
('Informatika',     0,   99),
('Komputer',        0,   99),
('Sistem Informasi',0,   99),
('Teknik Elektro',  600, 699),
('Manajemen',       650, 659),
('Akuntansi',       650, 659),
('Ekonomi',         330, 339),
('Hukum',           340, 349),
('Kedokteran',      610, 619),
('Keperawatan',     610, 619),
('Farmasi',         615, 619),
('Psikologi',       150, 159),
('Pendidikan',      370, 379),
('Pertanian',       630, 639),
('Matematika',      510, 519),
('Fisika',          530, 539),
('Kimia',           540, 549),
('Biologi',         570, 579);


-- ============================================================
-- G. QUERY SIMULASI — Ambil data untuk CBF
--    "Cari buku serupa dengan buku_id = 1001"
-- ============================================================

-- Buat contoh data CBF (setelah mining)
INSERT INTO rec_cbf_similarity (biblio_id_a, biblio_id_b, similarity) VALUES
(1001, 1002, 0.7231),
(1001, 1004, 0.5412),
(1001, 1007, 0.4823),
(1002, 1003, 0.8145),
(1002, 1008, 0.7632),
(1002, 1010, 0.6541),
(1003, 1010, 0.7823),
(1004, 1007, 0.5234),
(1005, 1007, 0.6123),
(1006, 1009, 0.5891)
ON DUPLICATE KEY UPDATE similarity = VALUES(similarity);

-- QUERY: Rekomendasi CBF untuk buku tertentu
SELECT
    b.biblio_id,
    b.title,
    b.subject,
    b.classification,
    ROUND(s.similarity, 4) AS similarity_score
FROM rec_cbf_similarity s
JOIN biblio b ON (
    CASE
        WHEN s.biblio_id_a = 1001 THEN s.biblio_id_b
        ELSE s.biblio_id_a
    END = b.biblio_id
)
WHERE s.biblio_id_a = 1001
   OR s.biblio_id_b = 1001
ORDER BY similarity_score DESC
LIMIT 5;
-- OUTPUT: Buku paling mirip dengan buku 1001 (Python)


-- ============================================================
-- H. QUERY SIMULASI — Ambil data untuk CF
--    "Buku apa yang sering dipinjam bersama buku_id = 1002?"
-- ============================================================

-- Buat contoh data CF (setelah mining)
INSERT INTO rec_cf_similarity (biblio_id_a, biblio_id_b, similarity) VALUES
(1001, 1002, 0.6667),  -- Budi & Ani keduanya pinjam Python dan ML
(1002, 1003, 0.5000),  -- Ani & Sari keduanya pinjam ML dan Deep Learning
(1002, 1008, 0.3333),  -- hanya Ani yang pinjam keduanya
(1003, 1010, 0.3333),  -- hanya Sari yang pinjam keduanya
(1004, 1007, 0.3333)   -- hanya Reza yang pinjam keduanya
ON DUPLICATE KEY UPDATE similarity = VALUES(similarity);

-- QUERY: Rekomendasi CF untuk user tertentu (member_id = 1, Budi)
-- Step 1: Ambil buku yang sudah dipinjam Budi
SELECT DISTINCT bi.biblio_id
FROM loan l
JOIN biblio_item bi ON l.item_code = bi.item_code
WHERE l.member_id = 1;
-- OUTPUT: 1001, 1002, 1004

-- Step 2: Cari buku serupa yang belum dipinjam Budi
SELECT
    CASE
        WHEN s.biblio_id_a IN (1001, 1002, 1004) THEN s.biblio_id_b
        ELSE s.biblio_id_a
    END AS recommended_biblio_id,
    b.title,
    SUM(s.similarity) AS total_similarity
FROM rec_cf_similarity s
JOIN biblio b ON (
    CASE
        WHEN s.biblio_id_a IN (1001, 1002, 1004) THEN s.biblio_id_b
        ELSE s.biblio_id_a
    END = b.biblio_id
)
WHERE (s.biblio_id_a IN (1001, 1002, 1004)
    OR s.biblio_id_b IN (1001, 1002, 1004))
  AND CASE
        WHEN s.biblio_id_a IN (1001, 1002, 1004) THEN s.biblio_id_b
        ELSE s.biblio_id_a
      END NOT IN (1001, 1002, 1004)
GROUP BY recommended_biblio_id, b.title
ORDER BY total_similarity DESC
LIMIT 5;
-- OUTPUT: Top 5 buku rekomendasi CF untuk Budi


-- ============================================================
-- I. QUERY SIMULASI — Cold Start
--    "User baru dari jurusan Teknik Informatika"
-- ============================================================

-- Step 1: Cari DDC range untuk jurusan 'Teknik Informatika'
SELECT ddc_start, ddc_end
FROM rec_major_ddc
WHERE 'Teknik Informatika' LIKE CONCAT('%', major_keyword, '%')
LIMIT 1;
-- OUTPUT: ddc_start=0, ddc_end=99

-- Step 2: Ambil buku populer di DDC range tersebut
SELECT
    b.biblio_id,
    b.title,
    b.subject,
    b.classification,
    COUNT(l.loan_id) AS borrow_count
FROM biblio b
JOIN biblio_item bi ON b.biblio_id = bi.biblio_id
JOIN loan l ON bi.item_code = l.item_code
WHERE CAST(b.classification AS UNSIGNED) BETWEEN 0 AND 99
  AND b.opac_hide = 0
GROUP BY b.biblio_id
ORDER BY borrow_count DESC
LIMIT 6;
-- OUTPUT: Buku paling populer di DDC 000-099 (Ilmu Komputer)


-- ============================================================
-- J. QUERY SIMULASI — Hybrid (gabungkan CBF + CF)
--    "Rekomendasi final untuk Budi (member_id=1)"
-- ============================================================

-- Subquery CBF skor untuk Budi
SELECT
    rec.biblio_id,
    b.title,
    rec.cbf_score,
    rec.cf_score,
    ROUND((0.60 * rec.cf_score) + (0.40 * rec.cbf_score), 4) AS hybrid_score
FROM (
    -- CBF skor (rata-rata similarity dengan buku yang sudah dipinjam Budi)
    SELECT
        CASE
            WHEN s.biblio_id_a IN (1001, 1002, 1004) THEN s.biblio_id_b
            ELSE s.biblio_id_a
        END AS biblio_id,
        AVG(s.similarity) AS cbf_score,
        0 AS cf_score
    FROM rec_cbf_similarity s
    WHERE (s.biblio_id_a IN (1001, 1002, 1004)
        OR s.biblio_id_b IN (1001, 1002, 1004))
      AND CASE
            WHEN s.biblio_id_a IN (1001, 1002, 1004) THEN s.biblio_id_b
            ELSE s.biblio_id_a
          END NOT IN (1001, 1002, 1004)
    GROUP BY biblio_id
) rec
JOIN biblio b ON rec.biblio_id = b.biblio_id
ORDER BY hybrid_score DESC
LIMIT 6;
-- OUTPUT: Top-6 rekomendasi Hybrid untuk Budi
