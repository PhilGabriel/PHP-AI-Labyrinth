<?php
/**
 * PHP AI Labyrinth — Bot Monitor Gateway
 *
 * Intercepts requests from bots that attempt to access paths restricted in
 * .htaccess / robots.txt. Logs each intrusion attempt (IP, User-Agent, path)
 * and silently serves the bot an AI Labyrinth page where it wastes tokens
 * following infinite fake content instead of receiving a plain 403.
 *
 * The labyrinth page is rendered directly (no Location redirect), so even
 * bots that never follow redirects are fed fake content immediately.
 *
 * Usage
 * -----
 * This script is the rewrite target for mod_rewrite bot-trap rules defined in
 * .htaccess.  Apache's internal rewrite sets REDIRECT_URL to the original
 * request path, which is used here for logging.
 *
 * Direct HTTP access (without a prior rewrite) is harmless: the visitor is
 * simply served a labyrinth entry page.
 *
 * @see     .htaccess.example  for the required RewriteRule directives
 * @license MIT
 */

require_once __DIR__ . '/config.php';

// ---- Recover the original forbidden path ----------------------------------------
//
// When Apache performs an *internal* rewrite (no [R] flag), it stores the
// original URI in REDIRECT_URL.  Fall back to REQUEST_URI for direct access.

$original_path = $_SERVER['REDIRECT_URL'] ?? ($_SERVER['REQUEST_URI'] ?? '/');

// ---- Sanitise all user-supplied values (OWASP A03) ------------------------------

$client_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'invalid';
$user_agent = labyrinth_sanitize_log($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
// Strip control characters from the path; limit length to avoid log bloat
$safe_path = labyrinth_sanitize_log($original_path, 512);

// ---- Rate limiting (OWASP A04 — DoS/DDoS protection) ---------------------------

if (!labyrinth_check_rate_limit($client_ip)) {
    http_response_code(429);
    header('Retry-After: 60');
    exit;
}

// ---- Log the intrusion attempt (OWASP A09) --------------------------------------

if (LABYRINTH_LOG_VISITS) {
    // Per-IP log rate limit keeps log files from exploding under DDoS conditions
    if (labyrinth_check_rate_limit('log_' . $client_ip, LABYRINTH_LOG_MAX_PER_WINDOW, 60)) {
        error_log(sprintf(
            'BOT_MONITOR: IP=%s UA=%s path=%s',
            $client_ip,
            $user_agent,
            $safe_path
        ));
    }
}

// ---- Derive a deterministic labyrinth entry page --------------------------------
//
// Using the forbidden path + IP as seed means the same bot always enters the
// same branch of the labyrinth, producing consistent (cached) responses.

$entry_page_id = 'trap_' . substr(md5($safe_path . $client_ip), 0, 16);

// ---- Serve the AI Labyrinth entry page ------------------------------------------
//
// Render directly instead of issuing a 302: bots that ignore redirects are
// fed fake content anyway, and there is one round-trip less per trapped hit.
// LABYRINTH_GATEWAY tells labyrinth.php to skip its own rate-limit check and
// visit logging — both already happened above for this request.

define('LABYRINTH_GATEWAY', true);
$_GET['p'] = $entry_page_id;
$_GET['d'] = '0';

require __DIR__ . '/labyrinth.php';
exit;
