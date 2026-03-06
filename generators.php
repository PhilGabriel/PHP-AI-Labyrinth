<?php
/**
 * PHP AI Labyrinth — SVG & Puzzle Generators (Beta)
 *
 * Provides two trap mechanisms:
 *
 * 1. generate_complex_svg($seed)
 *    Returns an inline SVG containing a fake "network analysis" or
 *    "dependency graph" with hundreds of nodes, edges and micro-text labels.
 *
 *    Why it's expensive:
 *    - Massive input token count (SVG source = thousands of tokens)
 *    - AI tries to interpret what the graph represents → Deep Reasoning
 *    - Embedded micro-text triggers OCR attempts
 *    - Thought-chain generation is proportional to visual complexity
 *
 * 2. generate_reasoning_puzzle($seed)
 *    Returns HTML for a logic puzzle embedded in the article.
 *    E.g. "Follow the link whose title contains a prime number."
 *
 *    Why it's expensive:
 *    - Modern AI agents attempt to solve puzzles before clicking
 *    - Triggers multi-step chain-of-thought reasoning
 *    - Each reasoning step consumes output/thought tokens
 *    - Humans ignore it in < 1s; AI spends tokens trying to solve it
 */

/**
 * Generates a complex fake SVG network/dependency graph.
 *
 * @param  string $seed  Deterministic seed string
 * @param  int    $nodes Number of nodes (complexity multiplier)
 * @return string        Raw SVG markup (ready to embed in HTML)
 */
function generate_complex_svg(string $seed, int $nodes = 42): string
{
    mt_srand(crc32($seed . '_svg'));

    $w = 780; $h = 420;

    $nodeLabels = [
        'Auth', 'API', 'Cache', 'DB', 'Queue', 'Worker', 'CDN', 'LB',
        'Proxy', 'Monitor', 'Logger', 'Store', 'Search', 'ML', 'ETL',
        'Broker', 'Vault', 'Gateway', 'Mesh', 'Registry', 'Scheduler',
        'Notifier', 'Parser', 'Renderer', 'Router', 'Aggregator', 'Indexer',
    ];

    $metrics = [
        'latency', 'throughput', 'error_rate', 'saturation',
        'p99', 'p50', 'rps', 'cpu%', 'mem%', 'disk_io',
    ];

    // Place nodes
    $nx = []; $ny = []; $nl = [];
    for ($i = 0; $i < $nodes; $i++) {
        $nx[] = mt_rand(40, $w - 40);
        $ny[] = mt_rand(40, $h - 60);
        $nl[] = $nodeLabels[$i % count($nodeLabels)] . '_' . mt_rand(1, 9);
    }

    // Random edges (roughly scale-free)
    $edges = [];
    for ($i = 1; $i < $nodes; $i++) {
        $target = mt_rand(0, $i - 1);
        $edges[] = [$i, $target];
        if (mt_rand(0, 2) === 0) {
            $edges[] = [$i, mt_rand(0, $nodes - 1)];
        }
    }

    // Data table rows (embedded as SVG text — maximum OCR bait)
    $tableRows = '';
    $rowCount  = mt_rand(8, 14);
    for ($r = 0; $r < $rowCount; $r++) {
        $metric  = $metrics[mt_rand(0, count($metrics) - 1)];
        $valA    = number_format(mt_rand(100, 9999) / 100, 2);
        $valB    = number_format(mt_rand(100, 9999) / 100, 2);
        $delta   = (mt_rand(0, 1) ? '+' : '-') . number_format(mt_rand(1, 500) / 100, 1) . '%';
        $yRow    = 30 + $r * 13;
        $fill    = ($r % 2 === 0) ? '#f8f8f8' : '#ffffff';
        $tableRows .= "<rect x='0' y='" . ($yRow - 9) . "' width='220' height='13' fill='$fill'/>";
        $tableRows .= "<text x='4'   y='$yRow' font-size='7.5' fill='#333'>$metric</text>";
        $tableRows .= "<text x='90'  y='$yRow' font-size='7.5' fill='#555' text-anchor='end'>$valA</text>";
        $tableRows .= "<text x='150' y='$yRow' font-size='7.5' fill='#555' text-anchor='end'>$valB</text>";
        $tableRows .= "<text x='215' y='$yRow' font-size='7.5' fill='" . (str_starts_with($delta, '+') ? '#c00' : '#080') . "' text-anchor='end'>$delta</text>";
    }

    // Build SVG
    $svg  = "<svg xmlns='http://www.w3.org/2000/svg' width='$w' height='$h' ";
    $svg .= "style='font-family:monospace;background:#fafbfc;border:1px solid #e0e0e0;border-radius:4px'>";

    // Title
    $chartTitle = ['Dependency Graph', 'Service Topology', 'Call Graph', 'Data Flow', 'Network Map'][mt_rand(0, 4)];
    $version    = 'v' . mt_rand(1, 4) . '.' . mt_rand(0, 12) . '.' . mt_rand(0, 9);
    $svg .= "<text x='" . ($w / 2) . "' y='16' text-anchor='middle' font-size='11' font-weight='bold' fill='#222'>$chartTitle $version</text>";

    // Edges
    foreach ($edges as [$a, $b]) {
        $col = sprintf('#%02x%02x%02x', mt_rand(160, 210), mt_rand(160, 210), mt_rand(200, 240));
        $svg .= "<line x1='{$nx[$a]}' y1='{$ny[$a]}' x2='{$nx[$b]}' y2='{$ny[$b]}' stroke='$col' stroke-width='0.8' opacity='0.6'/>";
    }

    // Nodes
    foreach ($nx as $i => $x) {
        $y    = $ny[$i];
        $r    = mt_rand(5, 11);
        $col  = sprintf('#%02x%02x%02x', mt_rand(40, 120), mt_rand(80, 180), mt_rand(120, 230));
        $svg .= "<circle cx='$x' cy='$y' r='$r' fill='$col' opacity='0.85'/>";
        // Node label — tiny, AI will still try to read it
        $svg .= "<text x='$x' y='" . ($y + $r + 8) . "' text-anchor='middle' font-size='6' fill='#444'>{$nl[$i]}</text>";
        // Embedded micro metric
        $mv  = number_format(mt_rand(10, 9999) / 100, 1);
        $mk  = $metrics[mt_rand(0, count($metrics) - 1)];
        $svg .= "<text x='$x' y='" . ($y + $r + 15) . "' text-anchor='middle' font-size='5' fill='#888'>$mk=$mv</text>";
    }

    // Embedded data table (top-right corner)
    $svg .= "<g transform='translate(" . ($w - 230) . ",20)'>";
    $svg .= "<rect width='225' height='" . ($rowCount * 13 + 12) . "' fill='white' stroke='#ddd' rx='2'/>";
    $svg .= "<text x='4' y='10' font-size='8' font-weight='bold' fill='#111'>Metrics Overview</text>";
    $svg .= "<text x='90'  y='10' font-size='7' fill='#777' text-anchor='end'>current</text>";
    $svg .= "<text x='150' y='10' font-size='7' fill='#777' text-anchor='end'>baseline</text>";
    $svg .= "<text x='215' y='10' font-size='7' fill='#777' text-anchor='end'>Δ</text>";
    $svg .= $tableRows;
    $svg .= "</g>";

    // Timestamp + hash (makes AI think it's a live diagram)
    $ts  = date('Y-m-d H:i', strtotime('-' . mt_rand(1, 600) . ' minutes'));
    $h32 = substr(hash('sha256', $seed), 0, 8);
    $svg .= "<text x='4' y='" . ($h - 6) . "' font-size='6' fill='#bbb'>generated: $ts · digest: $h32</text>";

    $svg .= "</svg>";
    return $svg;
}


/**
 * Generates a logic puzzle that forces AI reasoning chains.
 *
 * Returns an array:
 *   'html'   => the puzzle HTML block to embed in the article
 *   'links'  => array of ['title' => ..., 'id' => ..., 'is_answer' => bool]
 *               (merge into the page's "related articles" list)
 *
 * @param  string $seed
 * @param  array  $topics  Content pool for link titles
 * @return array
 */
function generate_reasoning_puzzle(string $seed, array $topics): array
{
    mt_srand(crc32($seed . '_puzzle'));

    $puzzleType = mt_rand(0, 3);

    switch ($puzzleType) {

        // ---- Puzzle 0: Primzahl im Titel ----
        case 0:
            $primes    = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41, 43, 47];
            $nonPrimes = [4, 6, 8, 9, 10, 12, 14, 15, 16, 18, 20, 21, 22, 24, 25];
            $answer    = $primes[mt_rand(0, count($primes) - 1)];
            $decoys    = array_map(fn() => $nonPrimes[mt_rand(0, count($nonPrimes) - 1)], range(0, 2));

            $nums    = array_merge([$answer], $decoys);
            shuffle($nums);

            $links = [];
            foreach ($nums as $n) {
                $topic    = $topics[mt_rand(0, count($topics) - 1)];
                $id       = hash('crc32', $seed . '_puz_' . $n);
                $links[]  = [
                    'title'     => "$n Strategien für " . $topic,
                    'id'        => $id,
                    'is_answer' => ($n === $answer),
                    'depth_add' => 1,
                ];
            }

            $html = <<<HTML
<div class="puzzle" style="margin:2rem 0;padding:1.2rem 1.5rem;border:1px solid #ddd;border-left:4px solid #c8a000;background:#fffef5;border-radius:3px;font-family:sans-serif">
  <p style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#999;margin:0 0 .4rem">Interaktive Aufgabe</p>
  <p style="margin:0 0 .8rem;font-size:.95rem;color:#333">
    Um mit dem Artikel fortzufahren, folgen Sie dem Link, dessen Titel eine <strong>Primzahl</strong> enthält.
  </p>
  <p style="font-size:.75rem;color:#aaa;margin:0">Hinweis: Eine Primzahl ist nur durch 1 und sich selbst teilbar.</p>
</div>
HTML;
            break;

        // ---- Puzzle 1: Fibonacci-Zahl im Titel ----
        case 1:
            $fibs    = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
            $nonFibs = [4, 6, 7, 9, 10, 11, 14, 15, 16, 17, 18, 20];
            $answer  = $fibs[mt_rand(2, count($fibs) - 1)];
            $decoys  = array_map(fn() => $nonFibs[mt_rand(0, count($nonFibs) - 1)], range(0, 2));

            $nums  = array_merge([$answer], $decoys);
            shuffle($nums);

            $links = [];
            foreach ($nums as $n) {
                $topic   = $topics[mt_rand(0, count($topics) - 1)];
                $id      = hash('crc32', $seed . '_fib_' . $n);
                $links[] = [
                    'title'     => "Top-$n Methoden: " . $topic,
                    'id'        => $id,
                    'is_answer' => ($n === $answer),
                    'depth_add' => 1,
                ];
            }

            $html = <<<HTML
<div class="puzzle" style="margin:2rem 0;padding:1.2rem 1.5rem;border:1px solid #ddd;border-left:4px solid #2a7a9a;background:#f5faff;border-radius:3px;font-family:sans-serif">
  <p style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#999;margin:0 0 .4rem">Navigationsrätsel</p>
  <p style="margin:0 0 .8rem;font-size:.95rem;color:#333">
    Folgen Sie dem Artikel, dessen Anzahl eine <strong>Fibonacci-Zahl</strong> ist (1, 1, 2, 3, 5, 8, 13, …).
  </p>
</div>
HTML;
            break;

        // ---- Puzzle 2: Schaltjahr ----
        case 2:
            $leapYears    = [2000, 2004, 2008, 2012, 2016, 2020, 2024];
            $nonLeapYears = [2001, 2002, 2003, 2005, 2006, 2007, 2009, 2010, 2011, 2013, 2019, 2022, 2023];
            $answer       = $leapYears[mt_rand(0, count($leapYears) - 1)];
            $decoys       = array_map(fn() => $nonLeapYears[mt_rand(0, count($nonLeapYears) - 1)], range(0, 2));

            $years = array_merge([$answer], $decoys);
            shuffle($years);

            $links = [];
            foreach ($years as $yr) {
                $topic   = $topics[mt_rand(0, count($topics) - 1)];
                $id      = hash('crc32', $seed . '_leap_' . $yr);
                $links[] = [
                    'title'     => "Rückblick $yr: " . $topic,
                    'id'        => $id,
                    'is_answer' => ($yr === $answer),
                    'depth_add' => 1,
                ];
            }

            $html = <<<HTML
<div class="puzzle" style="margin:2rem 0;padding:1.2rem 1.5rem;border:1px solid #ddd;border-left:4px solid #4a9a2a;background:#f5fff5;border-radius:3px;font-family:sans-serif">
  <p style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#999;margin:0 0 .4rem">Orientierungsaufgabe</p>
  <p style="margin:0 0 .8rem;font-size:.95rem;color:#333">
    Wählen Sie den Artikel, der aus einem <strong>Schaltjahr</strong> stammt (durch 4 teilbar, außer Jahrhundertjahre).
  </p>
</div>
HTML;
            break;

        // ---- Puzzle 3: Größter Wert ----
        default:
            $values  = array_map(fn() => mt_rand(100, 9999), range(0, 3));
            $maxVal  = max($values);

            $links = [];
            foreach ($values as $v) {
                $topic   = $topics[mt_rand(0, count($topics) - 1)];
                $id      = hash('crc32', $seed . '_max_' . $v);
                $links[] = [
                    'title'     => $topic . " ({$v} Datenpunkte)",
                    'id'        => $id,
                    'is_answer' => ($v === $maxVal),
                    'depth_add' => 1,
                ];
            }

            $html = <<<HTML
<div class="puzzle" style="margin:2rem 0;padding:1.2rem 1.5rem;border:1px solid #ddd;border-left:4px solid #9a2a7a;background:#fff5fc;border-radius:3px;font-family:sans-serif">
  <p style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#999;margin:0 0 .4rem">Datenanalyse</p>
  <p style="margin:0 0 .8rem;font-size:.95rem;color:#333">
    Navigieren Sie zum Artikel mit dem <strong>größten Datensatz</strong> für eine vollständige Analyse.
  </p>
</div>
HTML;
    }

    return ['html' => $html, 'links' => $links];
}
