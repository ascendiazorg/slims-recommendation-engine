<?php
/**
 * cover.php — Generator sampul buku (SVG) berdasarkan judul.
 * Dipakai sebagai fallback saat buku tidak punya file sampul.
 * GET ?title=<judul>&author=<pengarang>
 */
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=604800');

$raw  = isset($_GET['title'])  ? (string) $_GET['title']  : 'Buku';
$auth = isset($_GET['author']) ? (string) $_GET['author'] : '';

$rawClean  = trim(strip_tags(mb_substr($raw, 0, 140)));
$authClean = trim(strip_tags(mb_substr($auth, 0, 60)));
if ($rawClean === '') $rawClean = 'Buku';

/* Palet warna deterministik dari judul (dua warna untuk gradient) */
$palette = [
    ['#1E3A8A', '#2563EB'], // biru
    ['#065F46', '#10B981'], // hijau
    ['#7C2D12', '#EA580C'], // oranye
    ['#4C1D95', '#7C3AED'], // ungu
    ['#134E4A', '#14B8A6'], // teal
    ['#831843', '#DB2777'], // magenta
    ['#1E293B', '#475569'], // slate
    ['#3F3F46', '#71717A'], // zinc
    ['#7F1D1D', '#DC2626'], // merah
    ['#155E75', '#0891B2'], // cyan
];
$p    = $palette[abs(crc32($rawClean)) % count($palette)];
$c1   = $p[0];
$c2   = $p[1];
$uid  = substr(md5($rawClean), 0, 8);

/* Bungkus judul jadi beberapa baris (maks ~16 karakter/baris, maks 6 baris) */
function wrapTitle(string $text, int $maxChars): array
{
    $words = preg_split('/\s+/', $text);
    $lines = [];
    $cur   = '';
    foreach ($words as $w) {
        if (mb_strlen($w) > $maxChars) { // pecah kata sangat panjang
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            foreach (mb_str_split($w, $maxChars) as $chunk) $lines[] = $chunk;
            continue;
        }
        if ($cur === '') {
            $cur = $w;
        } elseif (mb_strlen($cur) + 1 + mb_strlen($w) <= $maxChars) {
            $cur .= ' ' . $w;
        } else {
            $lines[] = $cur;
            $cur = $w;
        }
    }
    if ($cur !== '') $lines[] = $cur;
    return array_slice($lines, 0, 6);
}

$lines   = wrapTitle($rawClean, 16);
$n       = count($lines);
$fs      = $n >= 5 ? 11 : ($n >= 3 ? 12 : 13);
$lh      = $fs + 5;
$blockH  = $n * $lh;
$centerY = 92;                                  // titik tengah area judul
$startY  = (int) round($centerY - $blockH / 2 + $fs * 0.82);

$tspans = '';
foreach ($lines as $i => $line) {
    $safe = htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $tspans .= $i === 0
        ? "<tspan x=\"60\" y=\"{$startY}\">{$safe}</tspan>"
        : "<tspan x=\"60\" dy=\"{$lh}\">{$safe}</tspan>";
}

$authorSvg = '';
if ($authClean !== '') {
    $short = mb_strlen($authClean) > 22 ? mb_substr($authClean, 0, 20) . '…' : $authClean;
    $authorSvg = '<text x="60" y="163" text-anchor="middle" font-family="Segoe UI,Arial,sans-serif" '
               . 'font-size="7.5" fill="rgba(255,255,255,0.75)">'
               . htmlspecialchars($short, ENT_XML1, 'UTF-8') . '</text>';
}

echo <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="170" viewBox="0 0 120 170">
  <defs>
    <linearGradient id="g{$uid}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$c1}"/>
      <stop offset="1" stop-color="{$c2}"/>
    </linearGradient>
  </defs>

  <rect width="120" height="170" fill="url(#g{$uid})"/>
  <rect x="0" y="0" width="7" height="170" fill="rgba(0,0,0,0.25)"/>
  <rect x="7" y="0" width="113" height="30" fill="rgba(0,0,0,0.18)"/>
  <rect x="7" y="30" width="113" height="3" fill="rgba(255,255,255,0.55)"/>
  <rect x="7" y="146" width="113" height="24" fill="rgba(0,0,0,0.18)"/>

  <line x1="16" y1="12" x2="112" y2="12" stroke="rgba(255,255,255,0.20)" stroke-width="1"/>
  <line x1="16" y1="20" x2="112" y2="20" stroke="rgba(255,255,255,0.12)" stroke-width="1"/>

  <text font-family="Segoe UI,Arial,Helvetica,sans-serif" font-size="{$fs}" font-weight="700"
        fill="#ffffff" text-anchor="middle" letter-spacing="0.2">{$tspans}</text>

  {$authorSvg}

  <text x="60" y="21" text-anchor="middle" font-family="Segoe UI,Arial,sans-serif"
        font-size="11" fill="rgba(255,255,255,0.6)">&#128218;</text>
</svg>
SVG;
