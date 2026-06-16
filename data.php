<?php
/**
 * SIMULASI RECOMMENDATION SYSTEM — DATA
 * ======================================
 * File ini berisi data dummy untuk simulasi CBF dan CF
 * TANPA integrasi SLIMS dulu — murni PHP array
 *
 * Data: 10 buku, 4 user
 */

// ============================================================
// DATA BUKU — 10 buku dengan metadata lengkap
// Simulasi tabel `biblio` di SLIMS
// ============================================================
$books = [
    1 => [
        'biblio_id'      => 1,
        'title'          => 'Pemrograman Python untuk Pemula',
        'subject'        => 'python pemrograman komputer coding',
        'classification' => '005.13',
        'author'         => 'Budi Santoso',
    ],
    2 => [
        'biblio_id'      => 2,
        'title'          => 'Machine Learning dengan Python',
        'subject'        => 'machine learning python kecerdasan buatan',
        'classification' => '006.31',
        'author'         => 'Andi Wijaya',
    ],
    3 => [
        'biblio_id'      => 3,
        'title'          => 'Deep Learning dan Neural Network',
        'subject'        => 'deep learning neural network kecerdasan buatan',
        'classification' => '006.32',
        'author'         => 'Sari Dewi',
    ],
    4 => [
        'biblio_id'      => 4,
        'title'          => 'Algoritma dan Struktur Data',
        'subject'        => 'algoritma struktur data pemrograman komputer',
        'classification' => '005.1',
        'author'         => 'Reza Pratama',
    ],
    5 => [
        'biblio_id'      => 5,
        'title'          => 'Basis Data MySQL untuk Web',
        'subject'        => 'database mysql basis data web server',
        'classification' => '005.74',
        'author'         => 'Nina Marlina',
    ],
    6 => [
        'biblio_id'      => 6,
        'title'          => 'Jaringan Komputer dan Keamanan',
        'subject'        => 'jaringan komputer keamanan network',
        'classification' => '004.6',
        'author'         => 'Hendra Kusuma',
    ],
    7 => [
        'biblio_id'      => 7,
        'title'          => 'Pemrograman Web dengan PHP',
        'subject'        => 'php web pemrograman coding server',
        'classification' => '005.13',
        'author'         => 'Dewi Rahayu',
    ],
    8 => [
        'biblio_id'      => 8,
        'title'          => 'Data Science dan Analitik',
        'subject'        => 'data science analitik statistik python',
        'classification' => '006.31',
        'author'         => 'Ahmad Fauzi',
    ],
    9 => [
        'biblio_id'      => 9,
        'title'          => 'Sistem Operasi Linux',
        'subject'        => 'linux sistem operasi komputer server',
        'classification' => '005.43',
        'author'         => 'Bambang Setiawan',
    ],
    10 => [
        'biblio_id'      => 10,
        'title'          => 'Kecerdasan Buatan Teori dan Praktik',
        'subject'        => 'kecerdasan buatan artificial intelligence machine learning',
        'classification' => '006.3',
        'author'         => 'Rina Agustina',
    ],
];

// ============================================================
// DATA USER — 4 user dengan riwayat pinjaman
// Simulasi tabel `loan` di SLIMS
// ============================================================
$users = [
    1 => ['member_id' => 1, 'name' => 'Budi',   'jurusan' => 'Teknik Informatika'],
    2 => ['member_id' => 2, 'name' => 'Ani',    'jurusan' => 'Sistem Informasi'],
    3 => ['member_id' => 3, 'name' => 'Reza',   'jurusan' => 'Teknik Informatika'],
    4 => ['member_id' => 4, 'name' => 'Sari',   'jurusan' => 'Ilmu Komputer'],
];

// ============================================================
// DATA PINJAMAN — simulasi tabel `loan` di SLIMS
// Format: [member_id => [biblio_id, biblio_id, ...]]
// ============================================================
$loanHistory = [
    1 => [1, 2, 4],     // Budi  pinjam: Python, ML, Algoritma
    2 => [1, 2, 3, 8],  // Ani   pinjam: Python, ML, Deep Learning, Data Science
    3 => [4, 5, 7],     // Reza  pinjam: Algoritma, MySQL, PHP
    4 => [2, 3, 10],    // Sari  pinjam: ML, Deep Learning, AI
];
