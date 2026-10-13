<?php
/**
 * Collaborative Filtering - Item-Based CF + Jaccard Similarity
 * Diadaptasi dari simulasi/cf.php untuk database SLIMS
 */
class CollaborativeFilter
{
    private $dbs;

    public function __construct($dbs)
    {
        $this->dbs = $dbs;
    }

    private function buildBorrowerIndex(): array
    {
        $result = $this->dbs->query(
            "SELECT DISTINCT member_id, biblio_id FROM loan_history WHERE is_return = 1"
        );
        $index = [];
        while ($row = $result->fetch_assoc()) {
            $index[(int)$row['biblio_id']][] = $row['member_id'];
        }
        return $index;
    }

    private function jaccard(array $setA, array $setB): float
    {
        $irisan   = count(array_intersect($setA, $setB));
        $gabungan = count(array_unique(array_merge($setA, $setB)));
        return $gabungan > 0 ? round($irisan / $gabungan, 6) : 0.0;
    }

    public function runMining(): int
    {
        $index = $this->buildBorrowerIndex();
        if (empty($index)) return 0;

        $bookIds = array_keys($index);
        $count   = 0;

        $this->dbs->query("TRUNCATE TABLE rec_cf_similarity");

        foreach ($bookIds as $idA) {
            foreach ($bookIds as $idB) {
                if ($idA >= $idB) continue;
                $sim = $this->jaccard($index[$idA], $index[$idB]);
                if ($sim <= 0) continue;

                $this->dbs->query(sprintf(
                    "INSERT INTO rec_cf_similarity (biblio_id_a, biblio_id_b, similarity)
                     VALUES (%d, %d, %.6f)
                     ON DUPLICATE KEY UPDATE similarity = %.6f",
                    $idA, $idB, $sim, $sim
                ));
                $count++;
            }
        }
        return $count;
    }

    public function getScores(array $borrowed, array $exclude = []): array
    {
        if (empty($borrowed)) return [];
        $borrowed    = array_map('intval', $borrowed);
        $exclude     = array_map('intval', $exclude);
        $excludeList = implode(',', array_merge($borrowed, $exclude));
        $scores = [];

        foreach ($borrowed as $ownedId) {
            $result = $this->dbs->query(
                "SELECT biblio_id_b AS cand, similarity FROM rec_cf_similarity
                 WHERE biblio_id_a = {$ownedId} AND biblio_id_b NOT IN ({$excludeList})
                 UNION
                 SELECT biblio_id_a AS cand, similarity FROM rec_cf_similarity
                 WHERE biblio_id_b = {$ownedId} AND biblio_id_a NOT IN ({$excludeList})"
            );
            while ($row = $result->fetch_assoc()) {
                $cand = (int)$row['cand'];
                $scores[$cand] = ($scores[$cand] ?? 0) + (float)$row['similarity'];
            }
        }
        return $scores;
    }
}
