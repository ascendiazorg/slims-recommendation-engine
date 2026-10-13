<?php
/**
 * Hybrid Recommender - CF (60%) + CBF (40%)
 * Dilengkapi evaluasi Precision, Recall, F-Measure (Leave-One-Out)
 */
class HybridRecommender
{
    private $dbs;
    private $cf;
    private $cbf;

    const BOBOT_CF  = 0.60;
    const BOBOT_CBF = 0.40;
    const TOP_N     = 5;

    public function __construct($dbs)
    {
        $this->dbs = $dbs;
        require_once __DIR__ . '/CollaborativeFilter.php';
        require_once __DIR__ . '/ContentBasedFilter.php';
        require_once __DIR__ . '/ColdStartHandler.php';
        $this->cf  = new CollaborativeFilter($dbs);
        $this->cbf = new ContentBasedFilter($dbs);
    }

    private function normalize(array $scores): array
    {
        $max = !empty($scores) ? max($scores) : 0;
        return $max > 0 ? array_map(fn($s) => $s / $max, $scores) : $scores;
    }

    private function getBorrowed(string $memberId, array $exclude = []): array
    {
        $sql = "SELECT DISTINCT biblio_id FROM loan_history
                WHERE member_id = '{$this->dbs->escape_string($memberId)}'
                AND is_return = 1";
        if (!empty($exclude)) {
            $sql .= " AND biblio_id NOT IN (" . implode(',', array_map('intval', $exclude)) . ")";
        }
        $result   = $this->dbs->query($sql);
        $borrowed = [];
        while ($row = $result->fetch_assoc()) {
            $borrowed[] = (int)$row['biblio_id'];
        }
        return $borrowed;
    }

    private function computeScores(string $memberId, int $topN, array $exclude = []): array
    {
        $borrowed = $this->getBorrowed($memberId, $exclude);
        if (empty($borrowed)) return ['hybrid' => [], 'cf_norm' => [], 'cbf_norm' => []];

        $cfRaw  = $this->cf->getScores($borrowed, $exclude);
        $cbfRaw = $this->cbf->getScores($borrowed, $exclude);

        $cfNorm  = $this->normalize($cfRaw);
        $cbfNorm = $this->normalize($cbfRaw);

        $candidates = array_unique(array_merge(array_keys($cfNorm), array_keys($cbfNorm)));
        $hybrid = [];
        foreach ($candidates as $cid) {
            $hybrid[$cid] = (self::BOBOT_CF  * ($cfNorm[$cid]  ?? 0))
                           + (self::BOBOT_CBF * ($cbfNorm[$cid] ?? 0));
        }
        arsort($hybrid);
        $hybrid = array_slice($hybrid, 0, $topN, true);

        return ['hybrid' => $hybrid, 'cf_norm' => $cfNorm, 'cbf_norm' => $cbfNorm];
    }

    public function getRecommendations(string $memberId, int $topN = self::TOP_N): array
    {
        $data   = $this->computeScores($memberId, $topN);
        $scores = $data['hybrid'];

        // Tidak ada riwayat atau similarity → Cold Start
        if (empty($scores)) {
            $cold = new ColdStartHandler($this->dbs);
            return $cold->getRecommendations($memberId, $topN);
        }

        $topIds = implode(',', array_keys($scores));
        $result = $this->dbs->query(
            "SELECT biblio_id, title, image FROM biblio WHERE biblio_id IN ({$topIds})"
        );
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[(int)$row['biblio_id']] = $row;
        }

        $recs = [];
        $rank = 1;
        foreach ($scores as $bibId => $score) {
            if (!isset($books[$bibId])) continue;
            $recs[] = [
                'rank'      => $rank++,
                'biblio_id' => $bibId,
                'title'     => $books[$bibId]['title'],
                'image'     => $books[$bibId]['image'] ?? '',
                'score'     => round($score, 4),
                'cf_score'  => round($data['cf_norm'][$bibId]  ?? 0, 4),
                'cbf_score' => round($data['cbf_norm'][$bibId] ?? 0, 4),
                'source'    => 'hybrid',
            ];
        }
        return $recs;
    }

    // Evaluasi terpisah per algoritma — CF, CBF, Hybrid (LOOCV, maks 30 member)
    public function evaluateAll(int $topN = self::TOP_N, int $maxMembers = 30): array
    {
        $result = $this->dbs->query(
            "SELECT member_id, COUNT(DISTINCT biblio_id) AS total
             FROM loan_history WHERE is_return = 1
             GROUP BY member_id HAVING total >= 2
             LIMIT {$maxMembers}"
        );
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row['member_id'];
        }

        if (empty($members)) {
            $empty = ['precision' => 0, 'recall' => 0, 'f_measure' => 0, 'hits' => 0, 'total' => 0];
            return ['cf' => $empty, 'cbf' => $empty, 'hybrid' => $empty, 'total_members' => 0];
        }

        $hits = ['cf' => 0, 'cbf' => 0, 'hybrid' => 0];
        $totalTests = 0;

        foreach ($members as $memberId) {
            $r2 = $this->dbs->query(
                "SELECT biblio_id,
                        COALESCE(MAX(return_date), MAX(loan_date), '1900-01-01') AS last_ret
                 FROM loan_history
                 WHERE member_id = '{$this->dbs->escape_string($memberId)}' AND is_return = 1
                 GROUP BY biblio_id ORDER BY last_ret ASC"
            );
            $allBorrowed = [];
            while ($row = $r2->fetch_assoc()) {
                $allBorrowed[] = (int)$row['biblio_id'];
            }
            if (count($allBorrowed) < 2) continue;

            $testBook  = array_pop($allBorrowed);
            // testBook sudah di-pop dari $allBorrowed → tidak perlu di-exclude lagi
            // getScores() otomatis exclude $allBorrowed dari kandidat
            $cfRaw     = $this->cf->getScores($allBorrowed, []);
            $cbfRaw    = $this->cbf->getScores($allBorrowed, []);

            arsort($cfRaw);  $cfTop  = array_slice($cfRaw,  0, $topN, true);
            arsort($cbfRaw); $cbfTop = array_slice($cbfRaw, 0, $topN, true);

            $cfNorm  = $this->normalize($cfRaw);
            $cbfNorm = $this->normalize($cbfRaw);
            $candidates = array_unique(array_merge(array_keys($cfNorm), array_keys($cbfNorm)));
            $hybScores = [];
            foreach ($candidates as $cid) {
                $hybScores[$cid] = (self::BOBOT_CF * ($cfNorm[$cid] ?? 0))
                                 + (self::BOBOT_CBF * ($cbfNorm[$cid] ?? 0));
            }
            arsort($hybScores);
            $hybTop = array_slice($hybScores, 0, $topN, true);

            if (array_key_exists($testBook, $cfTop))  $hits['cf']++;
            if (array_key_exists($testBook, $cbfTop)) $hits['cbf']++;
            if (array_key_exists($testBook, $hybTop)) $hits['hybrid']++;
            $totalTests++;
        }

        $calc = function(int $h) use ($totalTests, $topN): array {
            if ($totalTests === 0) return ['precision' => 0, 'recall' => 0, 'f_measure' => 0, 'hits' => 0, 'total' => 0];
            $p = $h / ($totalTests * $topN);
            $r = $h / $totalTests;
            $f = ($p + $r) > 0 ? 2 * $p * $r / ($p + $r) : 0;
            return [
                'precision' => round($p, 4),
                'recall'    => round($r, 4),
                'f_measure' => round($f, 4),
                'hits'      => $h,
                'total'     => $totalTests,
            ];
        };

        return [
            'cf'            => $calc($hits['cf']),
            'cbf'           => $calc($hits['cbf']),
            'hybrid'        => $calc($hits['hybrid']),
            'total_members' => count($members),
        ];
    }

    // Evaluasi Leave-One-Out Cross Validation
    public function evaluate(int $topN = self::TOP_N): array
    {
        $result = $this->dbs->query(
            "SELECT member_id, COUNT(DISTINCT biblio_id) AS total
             FROM loan_history WHERE is_return = 1
             GROUP BY member_id HAVING total >= 2"
        );
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row['member_id'];
        }

        if (empty($members)) {
            return ['precision' => 0, 'recall' => 0, 'f_measure' => 0, 'hits' => 0, 'total_test' => 0];
        }

        $hits = 0;
        $totalTests = 0;

        foreach ($members as $memberId) {
            // Urutkan berdasarkan waktu kembali — sembunyikan buku yang PALING TERAKHIR dipinjam
            $result2 = $this->dbs->query(
                "SELECT biblio_id,
                        COALESCE(MAX(return_date), MAX(loan_date), '1900-01-01') AS last_return
                 FROM loan_history
                 WHERE member_id = '{$this->dbs->escape_string($memberId)}' AND is_return = 1
                 GROUP BY biblio_id
                 ORDER BY last_return ASC"
            );
            $allBorrowed = [];
            while ($row = $result2->fetch_assoc()) {
                $allBorrowed[] = (int)$row['biblio_id'];
            }
            if (count($allBorrowed) < 2) continue;

            // Sembunyikan buku yang paling terakhir dikembalikan sebagai data uji
            $testBook = array_pop($allBorrowed);
            $scores   = $this->computeScores($memberId, $topN, [$testBook]);

            if (array_key_exists($testBook, $scores)) $hits++;
            $totalTests++;
        }

        if ($totalTests === 0) {
            return ['precision' => 0, 'recall' => 0, 'f_measure' => 0, 'hits' => 0, 'total_test' => 0];
        }

        $precision = $hits / ($totalTests * $topN);
        $recall    = $hits / $totalTests;
        $fMeasure  = ($precision + $recall) > 0
            ? 2 * $precision * $recall / ($precision + $recall)
            : 0;

        return [
            'precision'  => round($precision, 4),
            'recall'     => round($recall, 4),
            'f_measure'  => round($fMeasure, 4),
            'hits'       => $hits,
            'total_test' => $totalTests,
        ];
    }
}
