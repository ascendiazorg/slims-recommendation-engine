<?php
/**
 * ColdStartHandler — rekomendasi untuk anggota baru tanpa riwayat peminjaman.
 *
 * Strategi:
 *  1. Baca inst_name dari tabel member.
 *  2. Petakan ke rentang DDC via mapMajorToDdc().
 *  3. Ambil buku populer dalam rentang itu via getPopularInDdcRange().
 *  4. Fallback ke buku populer global jika jurusan tidak dikenali.
 *
 * Return format getRecommendations(): [ biblio_id => popularity_score ]
 * Konsisten dengan cfRecommend() dan cbfRecommend() di rekomendasi.php.
 */
class ColdStartHandler
{
    private PDO $db;

    // Keyword jurusan → [ddc_start, ddc_end]
    // Pencocokan pakai stripos (case-insensitive, partial match).
    private const DDC_MAP = [
        'Informatika'      => [0,   99],
        'Komputer'         => [0,   99],
        'Ilmu Komputer'    => [0,   99],
        'Sistem Informasi' => [0,   99],
        'Teknik Elektro'   => [600, 699],
        'Manajemen'        => [650, 659],
        'Akuntansi'        => [650, 659],
        'Ekonomi'          => [330, 339],
        'Bisnis'           => [650, 659],
        'Hukum'            => [340, 349],
        'Kedokteran'       => [610, 619],
        'Keperawatan'      => [610, 619],
        'Farmasi'          => [615, 619],
        'Psikologi'        => [150, 159],
        'Pendidikan'       => [370, 379],
        'Pertanian'        => [630, 639],
        'Matematika'       => [510, 519],
        'Statistika'       => [510, 519],
        'Fisika'           => [530, 539],
        'Kimia'            => [540, 549],
        'Biologi'          => [570, 579],
        'Teknik Sipil'     => [620, 629],
        'Teknik Mesin'     => [620, 629],
        'Komunikasi'       => [300, 309],
        'Sastra'           => [800, 899],
        'Sejarah'          => [900, 999],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Kembalikan Top-N rekomendasi untuk anggota baru (cold start).
     * Jika inst_name tidak dikenali atau tidak ada buku di DDC-nya,
     * fallback ke buku paling populer secara global.
     *
     * @return array [ biblio_id => popularity_score ]
     */
    public function getRecommendations(string $memberId, int $topN = 10): array
    {
        // Ambil jurusan anggota
        $stmt = $this->db->prepare(
            "SELECT inst_name FROM member WHERE member_id = ? LIMIT 1"
        );
        $stmt->execute([$memberId]);
        $row      = $stmt->fetch();
        $instName = $row ? trim((string)($row['inst_name'] ?? '')) : '';

        // Petakan jurusan ke rentang DDC
        $ranges = $this->mapMajorToDdc($instName);

        // Kumpulkan skor dari setiap rentang DDC yang cocok
        $scores = [];
        foreach ($ranges as [$ddcStart, $ddcEnd]) {
            foreach ($this->getPopularInDdcRange($ddcStart, $ddcEnd, $topN) as $id => $count) {
                // Ambil skor tertinggi jika biblio_id muncul di beberapa rentang
                $scores[$id] = max($scores[$id] ?? 0, $count);
            }
        }

        // Fallback: buku populer global
        if (empty($scores)) {
            $scores = $this->getPopularGlobal($topN);
        }

        arsort($scores);
        return array_slice($scores, 0, $topN, true);
    }

    /**
     * Petakan string jurusan ke satu atau lebih rentang DDC.
     * Deduplikasi berdasarkan pasang (start, end) agar tidak query dua kali
     * untuk rentang yang sama.
     *
     * @return array [ [ddc_start, ddc_end], ... ]
     */
    public function mapMajorToDdc(string $instName): array
    {
        if ($instName === '') return [];

        $matched = [];
        $seen    = [];

        foreach (self::DDC_MAP as $keyword => [$start, $end]) {
            $key = "{$start}-{$end}";
            if (!isset($seen[$key]) && stripos($instName, $keyword) !== false) {
                $matched[]  = [$start, $end];
                $seen[$key] = true;
            }
        }

        return $matched;
    }

    /**
     * Ambil buku paling sering dipinjam dalam rentang DDC [ddcStart, ddcEnd].
     *
     * classification di SLiMS disimpan sebagai string ("005.13", "330", "621.3").
     * CAST ke UNSIGNED otomatis memotong desimal: "005.13" → 5, "621.3" → 621.
     * Filter REGEXP '^[0-9]' mengabaikan baris tanpa kode numerik.
     *
     * @return array [ biblio_id => borrow_count ]
     */
    public function getPopularInDdcRange(int $ddcStart, int $ddcEnd, int $topN = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT b.biblio_id,
                   COUNT(l.loan_id) AS borrow_count
            FROM   biblio b
            LEFT JOIN item i ON b.biblio_id = i.biblio_id
            LEFT JOIN loan l ON i.item_code = l.item_code AND l.is_return = 1
            WHERE  b.opac_hide = 0
              AND  b.classification REGEXP '^[0-9]'
              AND  CAST(b.classification AS UNSIGNED) BETWEEN ? AND ?
            GROUP  BY b.biblio_id
            ORDER  BY borrow_count DESC, b.input_date DESC
            LIMIT  ?
        ");
        $stmt->execute([$ddcStart, $ddcEnd, $topN]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int)$row['biblio_id']] = (float)$row['borrow_count'];
        }
        return $result;
    }

    /**
     * Fallback: buku paling populer dari seluruh koleksi tanpa filter DDC.
     *
     * @return array [ biblio_id => borrow_count ]
     */
    private function getPopularGlobal(int $topN): array
    {
        $stmt = $this->db->prepare("
            SELECT b.biblio_id,
                   COUNT(l.loan_id) AS borrow_count
            FROM   biblio b
            LEFT JOIN item i ON b.biblio_id = i.biblio_id
            LEFT JOIN loan l ON i.item_code = l.item_code AND l.is_return = 1
            WHERE  b.opac_hide = 0
            GROUP  BY b.biblio_id
            ORDER  BY borrow_count DESC, b.input_date DESC
            LIMIT  ?
        ");
        $stmt->execute([$topN]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int)$row['biblio_id']] = (float)$row['borrow_count'];
        }
        return $result;
    }
}
