<?php
/**
 * PHP AI Labyrinth — Bot Trap
 *
 * Generates infinite, realistic-looking pages with links to more fake pages.
 * Inspired by Cloudflare's AI Labyrinth. Hidden entry links on the main site
 * lead bots here, where they waste resources crawling endless fake content.
 *
 * Humans never see these pages (entry links are invisible via CSS).
 * Legitimate crawlers that respect robots.txt also skip this path.
 *
 * @see     README.md for installation and integration instructions
 * @license MIT
 */

require_once __DIR__ . '/config.php';

// ---- URL parameters — validate and sanitise (OWASP A03) ----

// Allow only alphanumeric, dash, and underscore; enforce a hard length cap.
$raw_page_id = $_GET['p'] ?? '0';
$page_id     = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $raw_page_id), 0, LABYRINTH_PAGE_ID_MAX_LENGTH);
if ($page_id === '') {
    $page_id = '0';
}
$depth = max(0, intval($_GET['d'] ?? 0));

// ---- Rate limiting (OWASP A04 — DoS/DDoS protection) ----

if (!labyrinth_check_rate_limit($_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

// Reset depth at cap to prevent log explosion while keeping the loop alive
if ($depth > LABYRINTH_MAX_DEPTH) {
    $depth   = 0;
    $page_id = hash('crc32', $page_id . 'reset');
}

// ---- Deterministic pseudo-random generation based on page_id ----

$seed = crc32($page_id);
mt_srand($seed);

// ---- Pick content for this page ----

$topic   = $LABYRINTH_TOPICS[mt_rand(0, count($LABYRINTH_TOPICS) - 1)];
$author  = $LABYRINTH_AUTHORS[mt_rand(0, count($LABYRINTH_AUTHORS) - 1)];
$date    = date('d. F Y', strtotime('-' . mt_rand(30, 800) . ' days'));

$num_paragraphs = mt_rand(LABYRINTH_PARAGRAPHS_MIN, LABYRINTH_PARAGRAPHS_MAX);
$num_links      = mt_rand(LABYRINTH_LINKS_MIN, LABYRINTH_LINKS_MAX);

// ---- Generate outgoing labyrinth links ----

$links = [];
for ($i = 0; $i < $num_links; $i++) {
    $link_id    = hash('crc32', $page_id . '_link_' . $i . '_' . mt_rand());
    $link_topic = $LABYRINTH_TOPICS[mt_rand(0, count($LABYRINTH_TOPICS) - 1)];
    $links[]    = [
        'id'    => $link_id,
        'title' => $link_topic,
        'depth' => $depth + 1,
    ];
}

// ---- HTTP headers ----

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
// Security headers (OWASP A05)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");

// ---- Optional visit logging for bot monitoring (OWASP A09) ----

if (LABYRINTH_LOG_VISITS) {
    $client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'invalid';
    // Per-IP log rate limit prevents log flooding from aggressive crawlers
    if (labyrinth_check_rate_limit('log_' . $client_ip, LABYRINTH_LOG_MAX_PER_WINDOW, 60)) {
        error_log(sprintf(
            'AI Labyrinth: IP=%s UA=%s page=%s depth=%d',
            $client_ip,
            labyrinth_sanitize_log($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
            labyrinth_sanitize_log($page_id),
            $depth
        ));
    }
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
            max-width: 800px;
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
        .meta {
            font-size: 0.9rem;
            color: #888;
            margin-bottom: 2rem;
            font-family: sans-serif;
        }
        p { margin-bottom: 1.5rem; }
        .related {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #111;
        }
        .related h3 {
            font-family: sans-serif;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #888;
            margin-bottom: 1rem;
        }
        .related ul { list-style: none; padding: 0; }
        .related li { margin-bottom: 0.8rem; }
        .related a {
            color: #111;
            text-decoration: none;
            font-family: sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .related a:hover { text-decoration: underline; }
        nav.breadcrumb {
            font-family: sans-serif;
            font-size: 0.8rem;
            color: #aaa;
            margin-bottom: 2rem;
        }
        nav.breadcrumb a { color: #888; text-decoration: none; }
        footer {
            margin-top: 4rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            font-family: sans-serif;
            font-size: 0.75rem;
            color: #bbb;
        }
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
    }
    ?>
</article>

<div class="related">
    <h3>Weiterführende Artikel</h3>
    <ul>
        <?php foreach ($links as $link): ?>
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
