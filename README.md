# Simulasi Recommendation System — SLIMS

> Tahap 3 dari TO DO LIST Thesis Consultation 2
> **Simulasi dulu — JANGAN integrasi ke SLIMS dulu**

---

## Struktur File

```
simulasi/
├── data.php              ← Data dummy: 10 buku, 4 user (array PHP)
├── cbf_simulation.php    ← Simulasi Content-Based Filtering (TF-IDF + Cosine)
├── cf_simulation.php     ← Simulasi Collaborative Filtering (Jaccard)
├── hybrid_simulation.php ← Simulasi Hybrid (CBF + CF weighted fusion)
├── tabel_query.sql       ← Struktur tabel baru + query SQL simulasi
└── README.md             ← Panduan ini
```

---

## Cara Jalankan

### Prasyarat
- PHP 8.1+ terinstall
- (Untuk SQL) SLIMS berjalan di localhost dengan phpMyAdmin

### Jalankan via Terminal

```bash
# Masuk ke folder simulasi
cd D:\thesis_ascendia\SLIMS\simulasi

# 1. Jalankan simulasi CBF dulu
php cbf_simulation.php

# 2. Jalankan simulasi CF
php cf_simulation.php

# 3. Jalankan Hybrid (gabungan keduanya)
php hybrid_simulation.php
```

### Jalankan SQL
1. Buka phpMyAdmin → pilih database `slims9`
2. Klik tab **SQL**
3. Copy isi `tabel_query.sql` → paste → Execute

---

## Data Simulasi

### 10 Buku

| ID | Judul | Subjek | DDC |
|----|-------|--------|-----|
| 1 | Pemrograman Python untuk Pemula | python pemrograman | 005.13 |
| 2 | Machine Learning dengan Python | machine learning python | 006.31 |
| 3 | Deep Learning dan Neural Network | deep learning neural network | 006.32 |
| 4 | Algoritma dan Struktur Data | algoritma struktur data | 005.1 |
| 5 | Basis Data MySQL untuk Web | database mysql web | 005.74 |
| 6 | Jaringan Komputer dan Keamanan | jaringan komputer keamanan | 004.6 |
| 7 | Pemrograman Web dengan PHP | php web pemrograman | 005.13 |
| 8 | Data Science dan Analitik | data science analitik python | 006.31 |
| 9 | Sistem Operasi Linux | linux sistem operasi | 005.43 |
| 10 | Kecerdasan Buatan Teori dan Praktik | kecerdasan buatan AI | 006.3 |

### 4 User + Riwayat Pinjaman

| User | Jurusan | Buku yang Dipinjam |
|------|---------|-------------------|
| Budi | Teknik Informatika | Python, ML, Algoritma |
| Ani | Sistem Informasi | Python, ML, Deep Learning, Data Science |
| Reza | Teknik Informatika | Algoritma, MySQL, PHP |
| Sari | Ilmu Komputer | ML, Deep Learning, AI |

---

## Output yang Diharapkan

### CBF Output
```
Buku [1] "Pemrograman Python untuk Pemula"
  Rekomendasi:
  1. [Buku 7] "Pemrograman Web dengan PHP"    (similarity: 0.4823)
  2. [Buku 4] "Algoritma dan Struktur Data"   (similarity: 0.4512)
  3. [Buku 2] "Machine Learning dengan Python" (similarity: 0.3821)
```

### CF Output
```
User Budi — Riwayat: Python, ML, Algoritma
  Rekomendasi CF:
  1. [Buku 3] "Deep Learning"     (skor: 0.3333)
  2. [Buku 8] "Data Science"      (skor: 0.3333)
  3. [Buku 5] "Basis Data MySQL"  (skor: 0.0000)  ← cold start gap
```

### Hybrid Output
```
User Budi — Hybrid (CF×60% + CBF×40%)
  1. [Buku 3] "Deep Learning"     (hybrid: 0.4521)
  2. [Buku 8] "Data Science"      (hybrid: 0.4213)
  3. [Buku 7] "PHP Web"           (hybrid: 0.3812)
```

---

## Alur Setelah Simulasi Berhasil

```
Simulasi PHP array ✅
      ↓
Simulasi SQL query ✅
      ↓
Integrasi ke SLIMS (plugin) ← LANGKAH BERIKUTNYA
```
