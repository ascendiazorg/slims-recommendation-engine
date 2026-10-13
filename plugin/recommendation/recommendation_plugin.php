<?php
/**
 * Plugin Name: Recommendation Engine
 * Description: Sistem Rekomendasi Buku Hybrid (CF 60% + CBF 40%) untuk SLIMS OPAC
 * Version: 2.0
 * Author: Cendia - Thesis BINUS
 */

$plugin = \SLiMS\Plugins::getInstance();

$plugin->registerMenu('bibliography', 'Recommendation Engine', __DIR__ . '/admin/index.php');

$plugin->registerHook(\SLiMS\Plugins::CONTENT_BEFORE_LOAD, function ($opac) {
    // placeholder — widget dirender via rec_render_widget() di _member.php
});

function rec_render_widget(): string
{
    if (empty($_SESSION['mid'])) return '';

    global $dbs;

    $check = $dbs->query("SHOW TABLES LIKE 'rec_cf_similarity'");
    if ($check === false || $check->num_rows === 0) {
        return '';
    }

    require_once SB . 'plugins/recommendation/classes/HybridRecommender.php';
    $hybrid = new HybridRecommender($dbs);

    $recommendations = $hybrid->getRecommendations($_SESSION['mid'], 10);

    // Tambahkan cover online untuk buku yang tidak punya file gambar lokal
    foreach ($recommendations as &$rec) {
        if (!rec_has_cover($rec['image'] ?? '')) {
            $rec['online_cover'] = rec_fetch_cover($rec['title'], $dbs);
        } else {
            $rec['online_cover'] = '';
        }
    }
    unset($rec);

    // Evaluasi — cache di session selama 1 jam
    $cacheStale = empty($_SESSION['rec_eval_cache'])
        || (time() - ($_SESSION['rec_eval_cache']['ts'] ?? 0)) > 3600
        || (($_SESSION['rec_eval_cache']['cf']['f_measure'] ?? 0) == 0
            && ($_SESSION['rec_eval_cache']['cbf']['f_measure'] ?? 0) == 0);

    if ($cacheStale) {
        $eval = $hybrid->evaluateAll(5, 30);
        $_SESSION['rec_eval_cache'] = array_merge($eval, ['ts' => time()]);
    }
    $eval = $_SESSION['rec_eval_cache'];

    return rec_build_widget_html($recommendations, $eval);
}

function rec_cover_url(string $image): string
{
    if (filter_var($image, FILTER_VALIDATE_URL)) return $image;
    if (!empty($image) && file_exists(SB . 'images' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $image)) {
        $loc = 'images/docs/' . $image;
        return SWB . 'lib/minigalnano/createthumb.php?filename=' . urlencode($loc) . '&width=240';
    }
    return '';
}

function rec_has_cover(string $image): bool
{
    if (filter_var($image, FILTER_VALIDATE_URL)) return true;
    return !empty($image) && file_exists(SB . 'images' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $image);
}

/**
 * Ambil cover nyata dari Open Library berdasarkan judul buku.
 * Hasil di-cache ke tabel rec_cover_cache agar tidak fetch ulang.
 */
function rec_fetch_cover(string $title, $dbs): string
{
    static $mem = [];
    if (isset($mem[$title])) return $mem[$title];

    $hash = md5($title);

    // Pastikan tabel cache ada
    $dbs->query("CREATE TABLE IF NOT EXISTS rec_cover_cache (
        title_hash CHAR(32) PRIMARY KEY,
        cover_url  VARCHAR(500) NOT NULL DEFAULT '',
        cached_at  DATETIME NOT NULL
    )");

    // Cek cache DB
    $stmt = $dbs->prepare("SELECT cover_url FROM rec_cover_cache WHERE title_hash = ?");
    if ($stmt) {
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row !== null) {
            return $mem[$title] = ($row['cover_url'] ?? '');
        }
    }

    // Fetch dari Open Library Search API
    $apiUrl   = 'https://openlibrary.org/search.json?title='
              . urlencode($title)
              . '&fields=cover_i&limit=5';
    $coverUrl = '';

    // Fetch hanya jika SSL tersedia (cURL atau file_get_contents + openssl)
    $json = false;
    if (function_exists('curl_init') && extension_loaded('openssl')) {
        try {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'SLIMS-RecommendationEngine/2.0',
            ]);
            $json = curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            $json = false;
        }
    } elseif (ini_get('allow_url_fopen') && extension_loaded('openssl')) {
        try {
            $ctx  = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $json = @file_get_contents($apiUrl, false, $ctx);
        } catch (\Throwable $e) {
            $json = false;
        }
    }
    // Jika SSL tidak tersedia, lewati fetch — JavaScript akan mengambil cover client-side

    if ($json) {
        $data = json_decode($json, true);
        // Cari hasil pertama yang punya cover_i
        foreach (($data['docs'] ?? []) as $doc) {
            if (!empty($doc['cover_i'])) {
                $coverUrl = 'https://covers.openlibrary.org/b/id/' . (int)$doc['cover_i'] . '-M.jpg';
                break;
            }
        }
    }

    // Simpan ke cache (string kosong = tidak ditemukan, agar tidak re-fetch)
    $stmt2 = $dbs->prepare(
        "INSERT INTO rec_cover_cache (title_hash, cover_url, cached_at) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE cover_url = VALUES(cover_url), cached_at = NOW()"
    );
    if ($stmt2) {
        $stmt2->bind_param('ss', $hash, $coverUrl);
        $stmt2->execute();
        $stmt2->close();
    }

    return $mem[$title] = $coverUrl;
}

function rec_build_widget_html(array $recs, array $eval = []): string
{
    ob_start();
    $source = $recs[0]['source'] ?? 'hybrid';
    $headerLabel = match($source) {
        'jurusan' => 'Rekomendasi Buku untuk Kamu',
        'populer' => 'Buku Populer di Perpustakaan',
        default   => 'Rekomendasi Buku untuk Kamu',
    };
    $sourceNote = match($source) {
        'jurusan' => 'Dipilih berdasarkan bidang studi kamu',
        'populer' => 'Paling banyak dipinjam anggota perpustakaan',
        default   => 'Berdasarkan riwayat peminjaman kamu',
    };
    ?>
    <div class="mt-4 px-3 pb-3">

        <style>
        /* Grid rekomendasi: 5 kolom per baris (5 atas, 5 bawah untuk 10 item) */
        .rec-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px 12px;}
        .rec-grid__item{min-width:0;}
        .rec-grid .card{height:100%;margin-bottom:0 !important;}
        @media (max-width:991.98px){.rec-grid{grid-template-columns:repeat(4,1fr);}}
        @media (max-width:767.98px){.rec-grid{grid-template-columns:repeat(3,1fr);}}
        @media (max-width:479.98px){.rec-grid{grid-template-columns:repeat(2,1fr);}}
        </style>

        <h4 class="mb-3">
            <?= htmlspecialchars($headerLabel) ?>
            <br><small class="subtitle-section"><?= htmlspecialchars($sourceNote) ?></small>
        </h4>

        <?php if (empty($recs)): ?>
        <p class="text-muted">Belum ada rekomendasi tersedia saat ini.</p>
        <?php else: ?>
        <div class="rec-grid">
            <?php foreach ($recs as $rec):
                $image       = $rec['image'] ?? '';
                $hasCover    = rec_has_cover($image);
                $onlineCover = $rec['online_cover'] ?? '';

                // Prioritas: file lokal → Open Library (server-side cache) → SVG fallback
                if ($hasCover) {
                    $displayCover  = rec_cover_url($image);
                    $needsJsFetch  = false;
                } elseif ($onlineCover !== '') {
                    $displayCover  = $onlineCover;
                    $needsJsFetch  = false;
                } else {
                    $displayCover  = SWB . 'plugins/recommendation/cover.php?title=' . urlencode($rec['title']);
                    $needsJsFetch  = true;
                }

                $cfScore  = $rec['cf_score']  ?? 0;
                $cbfScore = $rec['cbf_score'] ?? 0;
                $hybScore = $rec['score']     ?? 0;
                $cfPct    = min(100, (int)round($cfScore  * 100));
                $cbfPct   = min(100, (int)round($cbfScore * 100));
            ?>
            <div class="rec-grid__item">
                <div class="card p-0 mb-3">
                    <a href="index.php?p=show_detail&id=<?= (int)$rec['biblio_id'] ?>">
                        <div class="d-flex justify-content-center align-items-center bg-grey-light"
                             style="height:170px;overflow:hidden">
                            <img loading="lazy"
                                 src="<?= htmlspecialchars($displayCover) ?>"
                                 <?= $needsJsFetch ? 'data-ol-title="' . htmlspecialchars($rec['title'], ENT_QUOTES) . '"' : '' ?>
                                 alt="<?= htmlspecialchars($rec['title']) ?>"
                                 class="img-thumbnail shadow"
                                 style="width:110px;height:155px;object-fit:cover">
                        </div>
                    </a>
                    <div class="card-body p-2">
                        <a href="index.php?p=show_detail&id=<?= (int)$rec['biblio_id'] ?>"
                           class="text-decoration-none d-block mb-2"
                           style="font-size:12px;color:#333;line-height:1.35">
                            <?= htmlspecialchars(mb_substr($rec['title'], 0, 55)) ?>
                        </a>
                        <?php if ($source === 'hybrid'): ?>
                        <div style="font-size:10px">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge badge-primary mr-1"
                                      style="font-size:9px;background:#2c5f8a;min-width:26px">CF</span>
                                <div class="progress flex-grow-1 mr-1" style="height:3px">
                                    <div class="progress-bar"
                                         style="width:<?= $cfPct ?>%;background:#2c5f8a"></div>
                                </div>
                                <span class="text-muted"><?= number_format($cfScore, 3) ?></span>
                            </div>
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge badge-success mr-1"
                                      style="font-size:9px;min-width:26px">CBF</span>
                                <div class="progress flex-grow-1 mr-1" style="height:3px">
                                    <div class="progress-bar bg-success"
                                         style="width:<?= $cbfPct ?>%"></div>
                                </div>
                                <span class="text-muted"><?= number_format($cbfScore, 3) ?></span>
                            </div>
                            <div class="text-center" style="color:#2c5f8a;font-weight:600">
                                Hybrid: <?= number_format($hybScore, 3) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        $hasCF  = !empty($eval['cf']['total'])  && $eval['cf']['total']  > 0;
        $hasCBF = !empty($eval['cbf']['total']) && $eval['cbf']['total'] > 0;
        if ($hasCF && $hasCBF):
            $cf  = $eval['cf'];
            $cbf = $eval['cbf'];
            $hyb = $eval['hybrid'];
            $maxF = max($cf['f_measure'], $cbf['f_measure'], $hyb['f_measure']);
            $algos = [
                ['label' => 'CF — Collaborative Filtering',  'data' => $cf],
                ['label' => 'CBF — Content-Based Filtering', 'data' => $cbf],
                ['label' => 'Hybrid (CF 60% + CBF 40%)',     'data' => $hyb],
            ];
        ?>
        <h5 class="mt-2 mb-2" style="font-size:13px;color:#555">
            Evaluasi Sistem — LOOCV Top-5
            <small class="text-muted" style="font-size:11px;font-weight:400">
                (<?= $eval['total_members'] ?? 0 ?> member, cache 1 jam)
            </small>
        </h5>
        <table class="table table-sm table-striped table-bordered" style="font-size:12px">
            <thead>
                <tr>
                    <th style="background:#2c5f8a;color:#fff;border-color:#2c5f8a">Algoritma</th>
                    <th class="text-center"
                        style="background:#2c5f8a;color:#fff;border-color:#2c5f8a">Precision</th>
                    <th class="text-center"
                        style="background:#2c5f8a;color:#fff;border-color:#2c5f8a">Recall</th>
                    <th class="text-center"
                        style="background:#2c5f8a;color:#fff;border-color:#2c5f8a">F1-Measure</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($algos as $a):
                    $d    = $a['data'];
                    $best = $d['f_measure'] >= $maxF;
                ?>
                <tr>
                    <td><?= $a['label'] ?></td>
                    <td class="text-center"><?= number_format($d['precision'] * 100, 2) ?>%</td>
                    <td class="text-center"><?= number_format($d['recall']    * 100, 2) ?>%</td>
                    <td class="text-center <?= $best ? 'font-weight-bold text-success' : '' ?>">
                        <?= number_format($d['f_measure'] * 100, 2) ?>%<?= $best ? ' ★' : '' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </div>
    <script>
    // Upgrade SVG placeholder covers ke cover nyata dari Open Library (client-side)
    (function () {
        var imgs = document.querySelectorAll('img[data-ol-title]');
        if (!imgs.length || typeof fetch === 'undefined') return;
        imgs.forEach(function (img) {
            var title = img.getAttribute('data-ol-title');
            fetch('https://openlibrary.org/search.json?title='
                  + encodeURIComponent(title)
                  + '&fields=cover_i&limit=5')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var docs = data.docs || [];
                    for (var i = 0; i < docs.length; i++) {
                        if (docs[i].cover_i) {
                            var url = 'https://covers.openlibrary.org/b/id/'
                                    + docs[i].cover_i + '-M.jpg';
                            var t = new Image();
                            t.onload = (function (el, src) {
                                return function () { el.src = src; };
                            }(img, url));
                            t.src = url;
                            break;
                        }
                    }
                })
                .catch(function () {});
        });
    }());
    </script>
    <?php
    return ob_get_clean();
}
