<?php
/**
 * PHP AI Labyrinth — Bot Monitor Gateway
 *
 * Intercepts requests from bots that attempt to access paths restricted in
 * .htaccess / robots.txt. Logs each intrusion attempt (IP, User-Agent, path)
 * and silently redirects the bot into the AI Labyrinth where it wastes tokens
 * following infinite fake content instead of receiving a plain 403.
 *
 * Usage
 * -----
 * This script is the rewrite target for mod_rewrite bot-trap rules defined in
 * .htaccess.  Apache's internal rewrite sets REDIRECT_URL to the original
 * request path, which is used here for logging.
 *
 * Direct HTTP access (without a prior rewrite) is harmless: the visitor is
 * simply redirected to the labyrinth entry page.
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

// ---- Redirect into the AI Labyrinth --------------------------------------------
//
// Validate LABYRINTH_BASE_PATH before use to prevent header injection in case
// of misconfiguration (OWASP A05 — open redirect / header injection guard).

$base = LABYRINTH_BASE_PATH;
if (!preg_match('#^/[a-zA-Z0-9/_\-.]*\.php$#', $base)) {
    // Fallback to a safe default if the constant is misconfigured
    $base = '/labyrinth.php';
}

header('Location: ' . $base . '?p=' . urlencode($entry_page_id) . '&d=0', true, 302);
exit;
