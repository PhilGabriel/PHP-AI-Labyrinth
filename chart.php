<?php
/**
 * PHP AI Labyrinth — GD Chart Generator (Beta)
 *
 * Serves a fake but realistic-looking PNG bar chart.
 * Linked from labyrinth pages as <img src="chart.php?seed=...">
 *
 * Why it's expensive for AI:
 *   - Forces a second HTTP request per page
 *   - Triggers vision/multimodal processing (1,000–3,000 extra tokens per image)
 *   - Contains axis labels, a legend, a title and numeric values in tiny fonts
 *     → AI tries to OCR all of it
 *
 * Requires: php-gd (sudo apt install php-gd / yum install php-gd)
 * Fallback: if GD is unavailable, outputs an inline SVG chart instead.
 */

$seed  = $_GET['seed'] ?? '0';
$style = $_GET['style'] ?? 'bar'; // bar | line | pie

mt_srand(crc32($seed));

// --- Fake dataset ---
$topics = [
    'Q1 2023', 'Q2 2023', 'Q3 2023', 'Q4 2023',
    'Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024',
];
$labels  = array_slice($topics, mt_rand(0, 3), 5);
$values  = array_map(fn() => mt_rand(18, 97), $labels);
$metric  = ['Conversion Rate (%)', 'Deployment Frequency', 'MTTR (h)', 'Availability (%)', 'Throughput (req/s)'][mt_rand(0, 4)];
$title   = $metric . ' — ' . ['Q-Übersicht', 'Jahresvergleich', 'KPI-Report', 'Trendanalyse'][mt_rand(0, 3)];

if (!extension_loaded('gd')) {
    // ---- SVG fallback ----
    header('Content-Type: image/svg+xml');
    $bars = '';
    $maxV = max($values);
    $w = 540; $h = 300; $pad = 50; $bw = 60;
    foreach ($values as $i => $v) {
        $bh  = (int)(($v / $maxV) * ($h - $pad * 2));
        $x   = $pad + $i * ($bw + 12);
        $y   = $h - $pad - $bh;
        $col = sprintf('#%02x%02x%02x', mt_rand(60,180), mt_rand(80,200), mt_rand(120,240));
        $bars .= "<rect x='$x' y='$y' width='$bw' height='$bh' fill='$col' rx='3'/>";
        $bars .= "<text x='" . ($x + $bw/2) . "' y='" . ($y-5) . "' text-anchor='middle' font-size='11' fill='#333'>$v</text>";
        $bars .= "<text x='" . ($x + $bw/2) . "' y='" . ($h-$pad+14) . "' text-anchor='middle' font-size='10' fill='#666'>{$labels[$i]}</text>";
    }
    echo "<?xml version='1.0' encoding='UTF-8'?>";
    echo "<svg xmlns='http://www.w3.org/2000/svg' width='$w' height='$h' style='background:#fff;font-family:sans-serif'>";
    echo "<text x='" . ($w/2) . "' y='22' text-anchor='middle' font-size='13' font-weight='bold' fill='#222'>" . htmlspecialchars($title) . "</text>";
    echo $bars;
    echo "<line x1='$pad' y1='" . ($h-$pad) . "' x2='" . ($w-$pad) . "' y2='" . ($h-$pad) . "' stroke='#ccc'/>";
    echo "</svg>";
    exit;
}

// ---- GD PNG chart ----
$w = 620; $h = 360;
$img = imagecreatetruecolor($w, $h);
imageantialias($img, true);

$white  = imagecolorallocate($img, 255, 255, 255);
$grey   = imagecolorallocate($img, 220, 220, 220);
$dark   = imagecolorallocate($img, 40,  40,  40);
$accent = imagecolorallocate($img, 55,  90, 185);
$light  = imagecolorallocate($img, 245, 247, 252);

imagefill($img, 0, 0, $white);
imagefilledrectangle($img, 0, 0, $w, $h, $light);

// Title
$font_size = 4;
imagestring($img, $font_size, (int)(($w - strlen($title) * imagefontwidth($font_size)) / 2), 12, $title, $dark);

// Grid lines
$padL = 60; $padB = 50; $padR = 20; $padT = 40;
$chartW = $w - $padL - $padR;
$chartH = $h - $padT - $padB;
$maxV   = max($values) + 10;
$step   = $chartH / 5;
for ($i = 0; $i <= 5; $i++) {
    $y = $padT + $i * $step;
    imageline($img, $padL, (int)$y, $w - $padR, (int)$y, $grey);
    $val = (int)($maxV - ($i / 5) * $maxV);
    imagestring($img, 2, $padL - 35, (int)$y - 6, (string)$val, $dark);
}

// Axes
imageline($img, $padL, $padT, $padL, $h - $padB, $dark);
imageline($img, $padL, $h - $padB, $w - $padR, $h - $padB, $dark);

// Bars
$n   = count($values);
$gap = 10;
$bw  = (int)(($chartW - ($n + 1) * $gap) / $n);

$colors = [];
foreach ($values as $i => $_) {
    $colors[] = imagecolorallocate($img,
        mt_rand(50, 100) + ($i * 30) % 100,
        mt_rand(80, 160),
        mt_rand(140, 220)
    );
}

foreach ($values as $i => $v) {
    $bh = (int)(($v / $maxV) * $chartH);
    $x1 = $padL + $gap + $i * ($bw + $gap);
    $y1 = $h - $padB - $bh;
    $x2 = $x1 + $bw;
    $y2 = $h - $padB;

    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $colors[$i]);
    imagerectangle($img, $x1, $y1, $x2, $y2, $accent);

    // Value label above bar
    imagestring($img, 2, $x1 + (int)(($bw - strlen((string)$v) * imagefontwidth(2)) / 2), $y1 - 14, (string)$v, $dark);

    // X-axis label
    $lx = $x1 + (int)(($bw - strlen($labels[$i]) * imagefontwidth(1)) / 2);
    imagestring($img, 1, $lx, $h - $padB + 6, $labels[$i], $dark);
}

// Y-axis label (rotated simulation via stacked chars)
$yLabel = 'Value';
for ($c = 0; $c < strlen($yLabel); $c++) {
    imagechar($img, 2, 4, $padT + 30 + $c * 10, $yLabel[$c], $dark);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($img);
imagedestroy($img);
