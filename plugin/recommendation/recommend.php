<?php
/**
 * recommend.php — API endpoint untuk widget rekomendasi di halaman detail buku
 * Dipanggil via AJAX dari show_detail.inc.php
 * GET ?biblio_id=<int>
 * Response: JSON array of recommended books
 */

define('INDEX_AUTH', 1);
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? 'localhost';

require_once __DIR__ . '/../../sysconfig.inc.php';

header('Content-Type: application/json; charset=utf-8');

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$self     = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$slims_wb = $scheme . '://' . $host . preg_replace('#/plugins/recommendation/.*$#', '/', $self);

$biblioId = intval($_GET['biblio_id'] ?? 0);
if ($biblioId <= 0) {
    echo json_encode([]);
    exit;
}

/**
 * Resolusi URL sampul buku. Selalu mengembalikan gambar yang bisa ditampilkan.
 * Urutan: file sampul lokal  →  cache cover online (Open Library, jika ada)
 *         →  sampul buatan (cover.php, SVG berwarna dari judul).
 *
 * @return array{0:string,1:bool}  [url, isGenerated]
 */
function resolveCover(array $row, string $slims_wb, $dbs): array
{
    $img   = trim((string)($row['image'] ?? ''));
    $title = (string)($row['title'] ?? 'Buku');

    // 1. Sudah berupa URL penuh
    if ($img !== '' && filter_var($img, FILTER_VALIDATE_URL)) {
        return [$img, false];
    }

    // 2. File sampul lokal di images/docs/ → thumbnail generator SLiMS
    if ($img !== '' && file_exists(SB . 'images/docs/' . $img)) {
        return [
            $slims_wb . 'lib/minigalnano/createthumb.php?filename='
                . urlencode('images/docs/' . $img) . '&width=240',
            false,
        ];
    }

    // 3. Cover online yang sudah pernah di-cache (tanpa fetch live -> tetap cepat)
    $hash = md5($title);
    $res  = @$dbs->query(
        "SELECT cover_url FROM rec_cover_cache
         WHERE title_hash = '" . $dbs->escape_string($hash) . "'
           AND cover_url <> '' LIMIT 1"
    );
    if ($res && ($r = $res->fetch_assoc()) && !empty($r['cover_url'])) {
        return [$r['cover_url'], false];
    }

    // 4. Sampul buatan — SVG berwarna berisi judul buku (selalu tersedia, offline)
    return [
        $slims_wb . 'plugins/recommendation/cover.php?title=' . rawurlencode($title),
        true,
    ];
}

// Ambil Top-10 CBF similarity (murni content-based)
$books = [];

$sqlCbf = "SELECT b.biblio_id, b.title, b.image, s.similarity
            FROM rec_cbf_similarity s
            JOIN biblio b ON b.biblio_id = IF(s.biblio_id_a = {$biblioId}, s.biblio_id_b, s.biblio_id_a)
            WHERE (s.biblio_id_a = {$biblioId} OR s.biblio_id_b = {$biblioId})
              AND b.opac_hide = 0
            ORDER BY s.similarity DESC
            LIMIT 10";

$r = $dbs->query($sqlCbf);
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        $id                 = (int)$row['biblio_id'];
        [$cover, $isGen]    = resolveCover($row, $slims_wb, $dbs);
        $books[] = [
            'biblio_id'    => $id,
            'title'        => $row['title'],
            'image'        => $cover,
            'is_generated' => $isGen,
            'similarity'   => round((float)$row['similarity'], 4),
            'detail_url'   => $slims_wb . 'index.php?p=show_detail&id=' . $id,
        ];
    }
}

echo json_encode(array_values($books));
