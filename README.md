# SLiMS Recommendation Engine

Plugin rekomendasi buku untuk **SLiMS 9.7.2 (Bulian)** berbasis **Hybrid Jaccard Similarity dan TF-IDF Cosine Similarity**.

| Komponen | Algoritma | Dipakai untuk |
|---|---|---|
| Content-Based Filtering (CBF) | TF-IDF + Cosine Similarity (judul, subjek, pengarang) | Widget "Buku dengan Konten Serupa" di halaman detail buku |
| Collaborative Filtering (CF) | Item-Based + Jaccard Similarity (riwayat peminjaman) | Bagian dari skor hybrid |
| Hybrid | Weighted fusion: **CF × 60% + CBF × 40%** | Widget "Rekomendasi Buku untuk Kamu" di area member |
| Cold Start | Pemetaan jurusan (`member.inst_name`) → buku populer | Member baru yang belum punya riwayat pinjam |

---

## Struktur Repo

```
.
├── plugin/recommendation/     ← PLUGIN SLiMS (yang dipasang di SLiMS)
├── slims-patch/               ← File inti SLiMS yang dimodifikasi agar widget tampil
└── *.php, *.sql (root)        ← Skrip simulasi, mining manual, dan evaluasi (tahap pengembangan)
```

---

## 1. Folder `plugin/recommendation/`

Ini plugin yang benar-benar berjalan di SLiMS. Salin ke `<root-slims>/plugins/recommendation/`.

```
plugin/recommendation/
├── recommendation_plugin.php   ← Entry point plugin: daftar menu admin + fungsi rec_render_widget()
├── classes/
│   ├── ContentBasedFilter.php  ← TF-IDF + Cosine Similarity → tabel rec_cbf_similarity
│   ├── CollaborativeFilter.php ← Item-Based Jaccard Similarity → tabel rec_cf_similarity
│   ├── HybridRecommender.php   ← Gabungan CF 60% + CBF 40%, fallback ke Cold Start
│   └── ColdStartHandler.php    ← Rekomendasi untuk member tanpa riwayat pinjam
├── admin/index.php             ← Panel admin (tab Dashboard, Mining, Log Mining)
├── recommend.php               ← API JSON (AJAX) untuk widget "Buku dengan Konten Serupa"
├── cover.php                   ← Generator sampul SVG untuk buku tanpa gambar sampul
├── mine.php                    ← Menjalankan mining dari browser (butuh login admin)
├── mine_cli.php                ← Menjalankan mining dari terminal / cron job
├── evaluate.php                ← Evaluasi CF vs CBF vs Hybrid (Leave-One-Out, Top-5)
├── simulate.php                ← Simulasi perhitungan langkah demi langkah dari data SLiMS
├── install.sql                 ← Tabel rec_cf_similarity, rec_cbf_similarity, rec_mining_log
└── .htaccess                   ← Mengizinkan eksekusi PHP di folder plugin
```

### Alur kerja plugin

```
Data SLiMS (biblio, loan, item, member)
        │
        ▼
   Mining  (mine.php / mine_cli.php / tab Mining di admin)
   ├─ CollaborativeFilter → rec_cf_similarity
   └─ ContentBasedFilter  → rec_cbf_similarity
        │
        ▼
   Penyajian (membaca tabel hasil mining, tidak menghitung ulang)
   ├─ Halaman detail buku → recommend.php (CBF) → widget "Buku dengan Konten Serupa"
   └─ Area member         → HybridRecommender  → widget "Rekomendasi Buku untuk Kamu"
                             └─ tanpa riwayat → ColdStartHandler
```

---

## 2. Folder `slims-patch/`

File bawaan SLiMS yang perlu diganti supaya widget tampil. Strukturnya sama dengan folder SLiMS, jadi cukup salin ke path yang sama.

| File | Perubahan |
|---|---|
| `lib/contents/show_detail.inc.php` | Menambahkan blok `#rec-widget` ("Buku dengan Konten Serupa") yang memanggil `plugins/recommendation/recommend.php` via AJAX |
| `template/default/parts/_member.php` | Menambahkan `rec_render_widget()` untuk menampilkan widget hybrid di area member |

> Backup file asli SLiMS sebelum menimpa. Patch dibuat untuk SLiMS 9.7.2 dengan template OPAC `default`.

---

## Instalasi Plugin

1. Salin `plugin/recommendation/` ke `<root-slims>/plugins/recommendation/`.
2. Salin isi `slims-patch/` ke `<root-slims>/` (timpa file yang sama).
3. Jalankan `plugin/recommendation/install.sql` di database SLiMS (phpMyAdmin → tab SQL).
4. Login admin SLiMS → **System → Plugins** → aktifkan **Recommendation Engine**.
5. Buka **Bibliography → Recommendation Engine** → tab **Mining** → jalankan mining.
   Alternatif lewat terminal:
   ```bash
   php plugins/recommendation/mine_cli.php
   ```
6. Cek hasilnya:
   - Buka detail buku mana saja di OPAC, lalu lihat widget **Buku dengan Konten Serupa**.
   - Login sebagai member (`index.php?p=member`), lalu lihat widget **Rekomendasi Buku untuk Kamu**.

Mining perlu diulang setiap kali ada buku atau transaksi peminjaman baru. Bisa juga dijadwalkan dengan cron/Task Scheduler memakai `mine_cli.php`.

---

## 3. Skrip di Root (Tahap Pengembangan)

Skrip yang dipakai sebelum integrasi ke plugin, untuk simulasi dan pengujian algoritma.

| File | Fungsi |
|---|---|
| `data.php` | Data dummy: 10 buku, 4 user |
| `cbf_simulation.php`, `cf_simulation.php`, `hybrid_simulation.php`, `ibcf_simulation.php` | Simulasi algoritma dengan data dummy (tanpa database) |
| `cbf.php`, `cf.php`, `hybrid.php` | Implementasi algoritma versi awal |
| `cbf_slims.php`, `cf_slims.php` | Algoritma yang membaca langsung dari database SLiMS |
| `1_mine_cbf.php`, `2_mine_cf.php` | Mining manual CBF dan CF ke tabel similarity |
| `ColdStartHandler.php` | Versi awal cold start handler |
| `rekomendasi.php` | Halaman demo rekomendasi per member (standalone) |
| `evaluate.php`, `ibcf_evaluation.php` | Evaluasi Precision/Recall/F-Measure dengan database |
| `evaluasi_standalone.php` | Evaluasi tanpa database (20 buku, 15 user dummy) |
| `install.sql`, `tabel_query.sql` | Struktur tabel rekomendasi + query simulasi |
| `slims_schema_real.sql` | Referensi skema database SLiMS |
| `config.example.php` | Contoh konfigurasi database. Salin ke `config.php` lalu isi kredensial |

### Menjalankan simulasi

```bash
# Tanpa database
php cbf_simulation.php
php cf_simulation.php
php hybrid_simulation.php
php evaluasi_standalone.php

# Dengan database SLiMS (isi config.php dulu)
cp config.example.php config.php
php 1_mine_cbf.php
php 2_mine_cf.php
php evaluate.php
```

### Data simulasi (`data.php`)

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

| User | Jurusan | Buku yang Dipinjam |
|------|---------|-------------------|
| Budi | Teknik Informatika | Python, ML, Algoritma |
| Ani | Sistem Informasi | Python, ML, Deep Learning, Data Science |
| Reza | Teknik Informatika | Algoritma, MySQL, PHP |
| Sari | Ilmu Komputer | ML, Deep Learning, AI |

---

## Prasyarat

- SLiMS 9.7.2 (Bulian)
- PHP 8.1+
- MySQL / MariaDB
