<?php
/**
 * Cold Start Handler
 * Untuk member yang belum punya riwayat peminjaman.
 * Strategi: buku populer sesuai jurusan (DDC) → fallback buku populer global.
 */
class ColdStartHandler
{
    private $dbs;

    // Mapping kata kunci jurusan ke prefix DDC
    private const DDC_MAP = [
        'informatika'  => ['000', '001', '002', '003', '004', '005', '006'],
        'komputer'     => ['000', '001', '002', '003', '004', '005', '006'],
        'teknik info'  => ['000', '001', '002', '003', '004', '005', '006'],
        'sistem info'  => ['000', '001', '002', '003', '004', '005', '006'],
        'perpustakaan' => ['020', '021', '022', '023', '025', '026', '027'],
        'library'      => ['020', '021', '022', '023', '025', '026', '027'],
        'politik'      => ['320', '321', '322', '323', '324', '327'],
        'ekonomi'      => ['330', '331', '332', '333', '336', '337', '338'],
        'akuntansi'    => ['657', '658'],
        'manajemen'    => ['650', '658'],
        'hukum'        => ['340', '341', '342', '343', '344', '345'],
        'psikologi'    => ['150', '152', '153', '154', '155', '157'],
        'sosiologi'    => ['301', '302', '303', '304', '305', '306'],
        'pendidikan'   => ['370', '371', '372', '373', '374', '375'],
        'kedokteran'   => ['610', '611', '612', '613', '614', '615', '616'],
        'keperawatan'  => ['610', '616', '617'],
        'sipil'        => ['620', '624'],
        'elektro'      => ['620', '621'],
        'arsitektur'   => ['720', '721', '722'],
        'komunikasi'   => ['302', '384', '385'],
        'sastra'       => ['800', '810', '820', '830', '840', '850'],
        'bahasa'       => ['400', '410', '420', '430', '440', '450'],
        'matematika'   => ['510', '511', '512', '513', '514', '515'],
        'fisika'       => ['530', '531', '532', '533'],
        'kimia'        => ['540', '541', '542', '543'],
        'biologi'      => ['570', '571', '572', '573', '574'],
        'pertanian'    => ['630', '631', '632', '633', '634'],
        'farmasi'      => ['615'],
        'bisnis'       => ['650', '657', '658', '659'],
    ];

    public function __construct($dbs)
    {
        $this->dbs = $dbs;
    }

    private function getDdcPrefixes(string $instName): array
    {
        $lower = strtolower($instName);
        foreach (self::DDC_MAP as $keyword => $prefixes) {
            if (str_contains($lower, $keyword)) {
                return $prefixes;
            }
        }
        return [];
    }

    private function buildWhereClause(array $prefixes): string
    {
        $conditions = [];
        foreach ($prefixes as $p) {
            $escaped = $this->dbs->escape_string($p);
            $conditions[] = "b.classification LIKE '{$escaped}%'";
        }
        return implode(' OR ', $conditions);
    }

    public function getRecommendations(string $memberId, int $topN = 5): array
    {
        // Ambil jurusan member
        $row = $this->dbs->query(
            "SELECT inst_name FROM member
             WHERE member_id = '{$this->dbs->escape_string($memberId)}'"
        )->fetch_assoc();

        $instName = $row['inst_name'] ?? '';
        $prefixes = $this->getDdcPrefixes($instName);

        // Coba rekomendasi berdasarkan DDC jurusan
        if (!empty($prefixes)) {
            $where  = $this->buildWhereClause($prefixes);
            $result = $this->dbs->query(
                "SELECT b.biblio_id, b.title, b.image,
                        COUNT(lh.loan_id) AS borrow_count
                 FROM biblio b
                 JOIN loan_history lh ON b.biblio_id = lh.biblio_id
                 WHERE ({$where}) AND b.opac_hide = 0
                 GROUP BY b.biblio_id, b.title, b.image
                 ORDER BY borrow_count DESC
                 LIMIT {$topN}"
            );

            $recs = $this->fetchRecs($result, 'jurusan');
            if (!empty($recs)) return $recs;
        }

        // Fallback: buku paling populer secara global
        $result = $this->dbs->query(
            "SELECT b.biblio_id, b.title, b.image,
                    COUNT(lh.loan_id) AS borrow_count
             FROM biblio b
             JOIN loan_history lh ON b.biblio_id = lh.biblio_id
             WHERE b.opac_hide = 0
             GROUP BY b.biblio_id, b.title, b.image
             ORDER BY borrow_count DESC
             LIMIT {$topN}"
        );

        return $this->fetchRecs($result, 'populer');
    }

    private function fetchRecs($result, string $source): array
    {
        $recs = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $recs[] = [
                'rank'         => $rank++,
                'biblio_id'    => (int)$row['biblio_id'],
                'title'        => $row['title'],
                'image'        => $row['image'] ?? '',
                'score'        => (int)$row['borrow_count'],
                'score_label'  => 'Dipinjam ' . $row['borrow_count'] . 'x',
                'source'       => $source,
            ];
        }
        return $recs;
    }
}
