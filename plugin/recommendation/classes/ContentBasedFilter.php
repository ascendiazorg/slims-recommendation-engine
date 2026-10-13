<?php
/**
 * Content-Based Filtering - TF-IDF + Cosine Similarity
 * Diadaptasi dari simulasi/cbf.php untuk database SLIMS
 */
class ContentBasedFilter
{
    private $dbs;

    public function __construct($dbs)
    {
        $this->dbs = $dbs;
    }

    private function getBooks(): array
    {
        $result = $this->dbs->query(
            "SELECT b.biblio_id, b.title,
                COALESCE(b.classification, '000') AS ddc,
                GROUP_CONCAT(DISTINCT t.topic SEPARATOR ' ')       AS subject,
                GROUP_CONCAT(DISTINCT a.author_name SEPARATOR ' ') AS author
             FROM biblio b
             LEFT JOIN biblio_topic bt  ON b.biblio_id = bt.biblio_id
             LEFT JOIN mst_topic t      ON bt.topic_id = t.topic_id
             LEFT JOIN biblio_author ba ON b.biblio_id = ba.biblio_id
             LEFT JOIN mst_author a     ON ba.author_id = a.author_id
             WHERE b.opac_hide = 0
             GROUP BY b.biblio_id, b.title, b.classification"
        );
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[(int)$row['biblio_id']] = $row;
        }
        return $books;
    }

    private function tokenize(string $text): array
    {
        $tokens = preg_split('/\s+/', strtolower(trim($text)));
        return array_values(array_filter($tokens, fn($t) => strlen($t) >= 3));
    }

    private function cosineSim(array $vecA, array $vecB): float
    {
        $dot = $normA = $normB = 0.0;
        foreach ($vecA as $k => $w) {
            $dot   += $w * ($vecB[$k] ?? 0);
            $normA += $w * $w;
        }
        foreach ($vecB as $w) $normB += $w * $w;
        $d = sqrt($normA) * sqrt($normB);
        return $d > 0 ? round($dot / $d, 6) : 0.0;
    }

    private function buildTfidf(array $books): array
    {
        $tokenized = [];
        foreach ($books as $id => $book) {
            // Gabungkan title + subject + DDC classification + author untuk vector yang lebih kaya
            $text = ($book['title']   ?? '') . ' '
                  . ($book['subject'] ?? '') . ' '
                  . ($book['ddc']     ?? '') . ' '
                  . ($book['author']  ?? '');
            $tokenized[$id] = $this->tokenize($text);
        }

        $N        = count($books);
        $allTerms = array_unique(array_merge(...array_values($tokenized)));
        $idf      = [];

        foreach ($allTerms as $kata) {
            $df = 0;
            foreach ($tokenized as $tokens) {
                if (in_array($kata, $tokens)) $df++;
            }
            $idf[$kata] = log($N / ($df + 1)) + 1;
        }

        $tfidf = [];
        foreach ($tokenized as $id => $tokens) {
            $total = count($tokens);
            if ($total === 0) { $tfidf[$id] = []; continue; }
            $freq = array_count_values($tokens);
            foreach ($freq as $kata => $jml) {
                $tfidf[$id][$kata] = ($jml / $total) * ($idf[$kata] ?? 1);
            }
        }
        return $tfidf;
    }

    public function runMining(): int
    {
        $books = $this->getBooks();
        if (empty($books)) return 0;

        $tfidf   = $this->buildTfidf($books);
        $bookIds = array_keys($books);
        $count   = 0;

        $this->dbs->query("TRUNCATE TABLE rec_cbf_similarity");

        foreach ($bookIds as $idA) {
            foreach ($bookIds as $idB) {
                if ($idA >= $idB) continue;
                $sim = $this->cosineSim($tfidf[$idA] ?? [], $tfidf[$idB] ?? []);
                if ($sim <= 0) continue;

                $this->dbs->query(sprintf(
                    "INSERT INTO rec_cbf_similarity (biblio_id_a, biblio_id_b, similarity)
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
                "SELECT biblio_id_b AS cand, similarity FROM rec_cbf_similarity
                 WHERE biblio_id_a = {$ownedId} AND biblio_id_b NOT IN ({$excludeList})
                 UNION
                 SELECT biblio_id_a AS cand, similarity FROM rec_cbf_similarity
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
