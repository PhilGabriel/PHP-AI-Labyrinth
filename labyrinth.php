<?php
/**
 * PHP AI Labyrinth — Bot Trap (Beta)
 *
 * Generates infinite, realistic-looking pages with links to more fake pages.
 * Beta adds three AI cost multipliers (all toggleable via config.php):
 *
 *   1. Chart images  — force vision/multimodal processing (~1k–3k tokens/img)
 *   2. Complex SVGs  — trigger deep reasoning to interpret the graph
 *   3. Logic puzzles — trigger chain-of-thought before the AI clicks
 *
 * @see     README.md for installation and integration instructions
 * @license MIT
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/generators.php';

// ---- URL parameters ----

$page_id = $_GET['p'] ?? '0';
$depth   = intval($_GET['d'] ?? 0);

if ($depth > LABYRINTH_MAX_DEPTH) {
    $depth   = 0;
    $page_id = hash('crc32', $page_id . 'reset');
}

// ---- Deterministic generation ----

$seed = crc32($page_id);
mt_srand($seed);

$topic  = $LABYRINTH_TOPICS[mt_rand(0, count($LABYRINTH_TOPICS) - 1)];
$author = $LABYRINTH_AUTHORS[mt_rand(0, count($LABYRINTH_AUTHORS) - 1)];
$date   = date('d. F Y', strtotime('-' . mt_rand(30, 800) . ' days'));

$num_paragraphs = mt_rand(LABYRINTH_PARAGRAPHS_MIN, LABYRINTH_PARAGRAPHS_MAX);
$num_links      = mt_rand(LABYRINTH_LINKS_MIN, LABYRINTH_LINKS_MAX);

// ---- Regular labyrinth links ----

$links = [];
for ($i = 0; $i < $num_links; $i++) {
    $link_id    = hash('crc32', $page_id . '_link_' . $i . '_' . mt_rand());
    $link_topic = $LABYRINTH_TOPICS[mt_rand(0, count($LABYRINTH_TOPICS) - 1)];
    $links[]    = ['id' => $link_id, 'title' => $link_topic, 'depth' => $depth + 1];
}

// ---- BETA: Logic puzzle (prepends its own links) ----

$puzzle_html  = '';
$puzzle_links = [];
if (defined('LABYRINTH_ENABLE_PUZZLES') && LABYRINTH_ENABLE_PUZZLES) {
    $puzzle = generate_reasoning_puzzle($page_id, $LABYRINTH_TOPICS);
    $puzzle_html = $puzzle['html'];
    foreach ($puzzle['links'] as $pl) {
        $puzzle_links[] = [
            'id'    => $pl['id'],
            'title' => $pl['title'],
            'depth' => $depth + $pl['depth_add'],
        ];
    }
}

// Merge puzzle links at top (answer is indistinguishable from decoys)
$all_links = array_merge($puzzle_links, $links);

// ---- BETA: SVG graph ----

$svg_html = '';
if (defined('LABYRINTH_ENABLE_SVG') && LABYRINTH_ENABLE_SVG) {
    $svg_html = generate_complex_svg($page_id, LABYRINTH_SVG_NODES);
}

// ---- BETA: Chart image URL ----

$chart_url = '';
if (defined('LABYRINTH_ENABLE_CHARTS') && LABYRINTH_ENABLE_CHARTS) {
    $chart_seed = urlencode(hash('crc32', $page_id . '_chart'));
    $chart_url  = LABYRINTH_CHART_PATH . '?seed=' . $chart_seed . '&style=bar';
}

// ---- HTTP headers ----

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');

// ---- Optional visit logging ----

if (LABYRINTH_LOG_VISITS) {
    $client_ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    error_log(sprintf(
        'AI Labyrinth: IP=%s UA=%s page=%s depth=%d',
        $client_ip, $user_agent, $page_id, $depth
    ));
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($topic); ?> — Fachbeiträge</title>
    <meta name="description" content="<?php echo htmlspecialchars("Fachartikel: $topic — Aktuelle Einblicke und Best Practices"); ?>">
    <style>
        body {
            font-family: Georgia, 'Times New Roman', serif;
            max-width: 860px;
            margin: 0 auto;
            padding: 2rem;
            line-height: 1.8;
            color: #222;
            background: #fff;
        }
        h1 {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 2.2rem;
            line-height: 1.2;
            margin-bottom: 0.5rem;
            color: #111;
        }
        h2 {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 1.4rem;
            margin-top: 2.5rem;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        .meta { font-size: .9rem; color: #888; margin-bottom: 2rem; font-family: sans-serif; }
        p { margin-bottom: 1.5rem; }
        .figure {
            margin: 2rem 0;
            text-align: center;
        }
        .figure img {
            max-width: 100%;
            border: 1px solid #e8e8e8;
            border-radius: 4px;
        }
        .figure figcaption {
            font-size: .78rem;
            color: #999;
            margin-top: .4rem;
            font-family: sans-serif;
        }
        .svg-figure {
            margin: 2rem 0;
            overflow-x: auto;
        }
        .svg-figure figcaption {
            font-size: .78rem;
            color: #999;
            margin-top: .4rem;
            font-family: sans-serif;
            text-align: center;
        }
        .related { margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #111; }
        .related h3 {
            font-family: sans-serif;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #888;
            margin-bottom: 1rem;
        }
        .related ul { list-style: none; padding: 0; }
        .related li { margin-bottom: .8rem; }
        .related a { color: #111; text-decoration: none; font-family: sans-serif; font-weight: 600; font-size: .95rem; }
        .related a:hover { text-decoration: underline; }
        nav.breadcrumb { font-family: sans-serif; font-size: .8rem; color: #aaa; margin-bottom: 2rem; }
        nav.breadcrumb a { color: #888; text-decoration: none; }
        footer { margin-top: 4rem; padding-top: 1rem; border-top: 1px solid #eee; font-family: sans-serif; font-size: .75rem; color: #bbb; }
    </style>
</head>
<body>

<nav class="breadcrumb">
    <a href="/">Home</a> &raquo;
    <a href="/research/articles">Fachbeiträge</a> &raquo;
    <?php echo htmlspecialchars($topic); ?>
</nav>

<article>
    <h1><?php echo htmlspecialchars($topic); ?></h1>
    <div class="meta">
        Von <?php echo htmlspecialchars($author); ?> &middot;
        <?php echo $date; ?> &middot;
        <?php echo mt_rand(5, 15); ?> Min. Lesezeit
    </div>

    <?php
    $used = [];
    $chartInserted = false;
    $svgInserted   = false;

    for ($i = 0; $i < $num_paragraphs; $i++) {

        if ($i > 0 && $i % 2 === 0) {
            $sh_idx = intval($i / 2) - 1;
            if (isset($LABYRINTH_SUBHEADINGS[$sh_idx])) {
                echo '<h2>' . htmlspecialchars($LABYRINTH_SUBHEADINGS[$sh_idx]) . '</h2>';
            }
        }

        do {
            $p_idx = mt_rand(0, count($LABYRINTH_PARAGRAPHS) - 1);
        } while (in_array($p_idx, $used) && count($used) < count($LABYRINTH_PARAGRAPHS));
        $used[] = $p_idx;

        echo '<p>' . htmlspecialchars($LABYRINTH_PARAGRAPHS[$p_idx]) . '</p>';

        // Insert GD chart after paragraph 1
        if (!$chartInserted && $chart_url && $i === 1) {
            $chartInserted = true;
            $caption = htmlspecialchars("Abbildung 1: Kennzahlen-Übersicht — $topic");
            echo <<<HTML
<figure class="figure">
    <img src="{$chart_url}" alt="{$caption}" width="620" height="360" loading="eager">
    <figcaption>{$caption}</figcaption>
</figure>
HTML;
        }

        // Insert SVG after paragraph 3
        if (!$svgInserted && $svg_html && $i === 3) {
            $svgInserted = true;
            $svgCaption = htmlspecialchars("Abb. 2: Systemarchitektur und Abhängigkeiten — " . substr($topic, 0, 40));
            echo '<figure class="svg-figure">';
            echo $svg_html;
            echo "<figcaption>$svgCaption</figcaption>";
            echo '</figure>';
        }
    }
    ?>

    <?php if ($puzzle_html): ?>
        <?php echo $puzzle_html; ?>
    <?php endif; ?>

</article>

<div class="related">
    <h3>Weiterführende Artikel</h3>
    <ul>
        <?php foreach ($all_links as $link): ?>
            <li>
                <a href="<?php echo htmlspecialchars(LABYRINTH_BASE_PATH . '?p=' . urlencode($link['id']) . '&d=' . $link['depth']); ?>">
                    <?php echo htmlspecialchars($link['title']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(LABYRINTH_SITE_NAME); ?> &middot; Alle Rechte vorbehalten
</footer>

</body>
</html>
