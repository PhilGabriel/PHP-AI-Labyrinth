# PHP AI Labyrinth 🤖🔀

> 🌐 **Language / Sprache:** **🇬🇧 English** · [🇩🇪 Deutsch](README.de.md)

A lightweight, self-hosted **bot trap** that wastes AI crawler resources by generating infinite, realistic-looking fake article pages — each linking to more fake pages.

Inspired by [Cloudflare's AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/).

---

## How It Works

```
Human visits your site   →  never sees the trap (CSS hidden link)
Compliant AI crawler     →  respects robots.txt, stays away entirely
Rogue bot/scraper        →  ignores robots.txt, follows hidden link or honeypot
                         →  lands on a realistic-looking article
                         →  follows 6–12 "related article" links
                         →  lands on more fake articles
                         →  ... forever, burning tokens on every page
```

Three mechanisms work together:

1. **The labyrinth** (`labyrinth.php`) — generates endless fake article pages. A hidden link on your real site is the entrance; humans never see it, compliant crawlers are kept away via `robots.txt`.
2. **The content generator** (`generator.php`) — assembles article text from combinatorial sentence templates. Every page is unique, so crawler deduplication cannot collapse the labyrinth into a handful of repeated pages. Maximum token burn per page.
3. **The bot monitor** (`bot-monitor.php`, optional) — catches known AI crawlers red-handed on paths your `robots.txt` forbids, logs the violation, and serves them a labyrinth page **directly** (no redirect a bot could ignore).

Key properties:

- Pages are **deterministic** — the same `?p=` ID always generates the same content (no database needed, pure PHP), so responses stay cacheable
- Every page sends `X-Robots-Tag: noindex, nofollow` and `<meta robots="noindex,nofollow">` so the trap never pollutes real search results
- Depth counter prevents log explosion; resets silently after `LABYRINTH_MAX_DEPTH`
- Built-in per-IP rate limiting and log flood protection
- Optional **tarpit delay** slows crawlers down at almost no cost to you

---

## Requirements

- PHP 7.4+
- Any web server (Apache, Nginx, Caddy, Traefik, …)

No database. No dependencies. Drop in three files and go.

---

## Installation & Setup

### 1. Copy the files

> ⚠️ **Give the entry script an inconspicuous name.** A URL containing `labyrinth` or `trap` tells a smart crawler exactly what it walked into. `articles.php` under `/research/` blends in.

```bash
# Clone the repo
git clone https://github.com/PhilGabriel/PHP-AI-Labyrinth.git

# Copy to your project (example path — note the renamed entry script)
cp PHP-AI-Labyrinth/labyrinth.php  /var/www/html/research/articles.php
cp PHP-AI-Labyrinth/generator.php  /var/www/html/research/generator.php
cp PHP-AI-Labyrinth/config.php     /var/www/html/research/config.php
```

`generator.php` and `config.php` must keep their names (the entry script includes them by name); only the entry script itself is free to be renamed.

### 2. Adjust `config.php`

At minimum, set your base path and site name:

```php
define('LABYRINTH_BASE_PATH', '/research/articles.php');
define('LABYRINTH_SITE_NAME', 'My Website');
```

Optionally replace the German topics, authors and word pools with content in your language and niche — the more convincing the content matches your site, the more effective the trap (see [Content Generator](#content-generator)).

### 3. Add a hidden entry link to your site

Bots follow links — humans never see this one:

```html
<!-- In your footer or any page -->
<a href="/research/articles.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> **Why not just block bots?** Blocking reveals that you detected them. A labyrinth silently wastes their compute budget without tipping them off.

### 4. Set up `robots.txt`

Three rules make the trap work (see [`robots.txt.example`](robots.txt.example) for a complete file):

1. **The labyrinth path must NOT be disallowed.** Otherwise bots that respect `robots.txt` skip it — but those aren't your problem anyway. Only rogue crawlers follow the hidden link.
2. **Disallow a honeypot path** (e.g. `/trap/`) that nothing legitimate ever links to. Any request to it is by definition a robots.txt violation — route it into the labyrinth (see [Bot Monitor](#bot-monitor-catch-robotstxt-violators-optional)).
3. **Tell AI training crawlers to stay off entirely.** Compliant ones (most large operators) will honor this and leave. The ones that ignore it walk straight into your traps.

```
# AI/LLM crawlers: stay off the whole site
User-agent: GPTBot
User-agent: ClaudeBot
User-agent: CCBot
User-agent: Bytespider
User-agent: PerplexityBot
User-agent: meta-externalagent
Disallow: /

User-agent: *
Disallow: /admin/
Disallow: /trap/
# /research/articles.php is intentionally absent — it is the labyrinth entry
```

### 5. Configure your web server (optional: clean URLs)

**Apache** — in `.htaccess` or VHost:
```apache
RewriteEngine On
RewriteRule ^research/articles$   /research/articles.php   [L,QSA]
```

**Nginx** — in the `server` block:
```nginx
location /research/articles {
    try_files $uri /research/articles.php?$query_string;
}
location ~ /(config|generator)\.php$ { deny all; }
```

This exposes the trap as a clean URL `/research/articles?p=…&d=…` without the `.php` extension.

---

## Bot Monitor: catch robots.txt violators (optional)

`bot-monitor.php` is the second trap mechanism. It targets bots that **provably** ignore your rules:

- Requests to the **honeypot path** (`/trap/` — disallowed in `robots.txt`, linked from nowhere)
- Requests from **known AI crawler User-Agents** to sensitive paths (`/admin`, `/wp-login.php`, `/.env`, …)

Each hit is logged (`BOT_MONITOR: IP=… UA=… path=…`) and the bot is served a labyrinth page **in the same response** — no `Location` redirect that a bot could simply not follow. The entry page is derived deterministically from the forbidden path + IP, so responses stay cacheable.

Setup (Apache): copy `bot-monitor.php` next to your `.htaccess` and add the rewrite rules from [`.htaccess.example`](.htaccess.example) — they route honeypot hits and AI-crawler requests to sensitive paths into the monitor. The User-Agent list in the example covers the common AI crawlers and scraper libraries; extend it as new ones appear.

---

## Content Generator

Static bait text has a weakness: crawlers deduplicate. If every labyrinth page is a permutation of the same dozen paragraphs, a scraper's dedup layer collapses the whole labyrinth into ~12 paragraphs' worth of tokens — trap defused.

`generator.php` fixes this. It assembles grammatically correct German sentences from independent word pools (subjects × predicates × adverbs × conditions × sentence templates), yielding **billions of distinct paragraphs**. Each page mixes ~80 % generated text with an occasional hand-written paragraph and bullet list, plus varied article titles and deterministic publication dates.

Everything runs off a single `mt_srand()` seed derived from the page ID:

- same page ID → byte-identical page, every time (cache-friendly, no database)
- different page ID → genuinely different text (dedup-proof, maximum token burn)

**Customizing / translating:** edit the word pools at the top of `generator.php` (subjects, predicates, adverbs, conditions, intros, list items) and the pools in `config.php` (topics, authors, subheadings, month names). Keep the predicate structure `[verb, object]` intact — it is what keeps both main-clause and subordinate-clause word order grammatical.

---

## WordPress Integration

PHP AI Labyrinth integrates into WordPress without any plugin.

### Option A: Standalone PHP files alongside WordPress (recommended)

WordPress typically runs at the web root. Just drop the three files into a subdirectory:

```
/var/www/html/
├── wp-config.php         ← WordPress
├── wp-content/
├── index.php
└── research/
    ├── articles.php      ← labyrinth.php, renamed (standalone, no WP bootstrap)
    ├── generator.php
    └── config.php
```

WordPress is **not loaded** — the files run completely independently. No plugin, no hook required.

In `config.php` set:
```php
define('LABYRINTH_BASE_PATH', '/research/articles.php');
```

Add the entry link via a `wp_footer` hook (survives theme updates, unlike editing `footer.php` directly):

```php
// Child theme functions.php
add_action('wp_footer', function () {
    echo '<a href="/research/articles.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>';
});
```

### Option B: As a custom WordPress page template

`labyrinth.php` can be used as a WP page template for full WordPress integration. Note: WordPress will load its entire bootstrap (DB connection, plugins, etc.) unless you add `define('SHORTINIT', true)` to suppress it.

Only useful if you need WordPress's rewrite engine to handle clean URLs.

---

## Potential Conflicts

| Situation | Problem | Solution |
|---|---|---|
| **Security plugins** (Wordfence, iThemes) | Plugin scans the labyrinth path and fires alerts | Whitelist the trap path in the plugin settings, or use a less obvious path name |
| **Caching plugins / CDN** | Cached pages always return the same page — bots loop through only 1–2 pages | Exclude the trap path from cache (e.g. W3 Total Cache: "Never cache pages: `/research/.*`") |
| **`robots.txt` plugins** | Plugins like Yoast SEO may auto-disallow non-WP paths | Remove the labyrinth path from the plugin's disallow list |
| **WAF / Cloudflare Firewall** | Your own WAF rules block trap traffic before it reaches PHP | Add the trap path to WAF bypass rules (`uri contains /research/ → skip`) |
| **Rate limiting** | Your own rate limiting blocks aggressive bots before they go deep | Exclude the trap path from external rate limiting — you *want* bots to send many requests (the built-in limiter still caps genuine DoS) |
| **PHP `open_basedir`** | Restrictive `open_basedir` settings can block `require_once` for `config.php` | Place all three files in the same directory, or adjust `open_basedir` |
| **Shared hosting** | No access to `error_log()` output or logs not visible | Set `LABYRINTH_LOG_VISITS` to `false`, or write to a custom log file |

---

## Configuration Reference

All settings live in `config.php`:

| Constant | Default | Description |
|---|---|---|
| `LABYRINTH_MAX_DEPTH` | `100` | Depth cap before silent reset |
| `LABYRINTH_LINKS_MIN` / `_MAX` | `6` / `12` | Outgoing links per page |
| `LABYRINTH_PARAGRAPHS_MIN` / `_MAX` | `4` / `8` | Paragraphs per page |
| `LABYRINTH_SITE_NAME` | `'Fachredaktion'` | Shown in footer |
| `LABYRINTH_BASE_PATH` | `'/research/articles.php'` | Self-referencing URL path |
| `LABYRINTH_LOG_VISITS` | `true` | Log bot visits via `error_log()` |
| `LABYRINTH_DELAY_MIN_MS` / `_MAX_MS` | `0` / `0` | Tarpit delay per page in ms (see below) |
| `LABYRINTH_RATE_LIMIT_ENABLED` | `true` | Per-IP rate limiting on/off |
| `LABYRINTH_RATE_LIMIT_MAX` | `60` | Max requests per IP per window |
| `LABYRINTH_RATE_LIMIT_WINDOW` | `60` | Rate-limit window in seconds |
| `LABYRINTH_PAGE_ID_MAX_LENGTH` | `64` | Max length of the `?p=` parameter |
| `LABYRINTH_LOG_MAX_PER_WINDOW` | `5` | Max log lines per IP per minute |

Arrays for topics, paragraphs, authors, subheadings and month names are in the same file; the sentence-generator word pools live in `generator.php`. All can be freely extended.

### Tarpit delay

Setting `LABYRINTH_DELAY_MIN_MS` / `LABYRINTH_DELAY_MAX_MS` (e.g. `250` / `1500`) makes every labyrinth page sleep for a deterministic, per-page amount of time before responding. It costs the crawler wall-clock time and costs you almost nothing — **but** each sleeping request occupies a PHP-FPM worker. Only enable it if your worker pool has headroom, and rely on the built-in rate limiter (values are hard-capped at 5000 ms as a safety net). Default: off.

---

## Monitoring

When `LABYRINTH_LOG_VISITS` is `true`, each visit is written to your PHP error log:

```
AI Labyrinth: IP=203.0.113.42 UA=SomeScraper/1.0 page=3b4c1d2e depth=7
BOT_MONITOR: IP=203.0.113.42 UA=GPTBot/1.1 path=/trap/
```

`AI Labyrinth:` lines are regular labyrinth page views; `BOT_MONITOR:` lines are proven robots.txt violations caught by the gateway. Parse them with any log aggregator (grep, GoAccess, Loki, …) to see which bots are in the trap.

### Count trap hits per IP (bash)

```bash
grep 'AI Labyrinth' /var/log/php/error.log | awk -F'IP=' '{print $2}' | cut -d' ' -f1 | sort | uniq -c | sort -rn | head
```

`BOT_MONITOR` IPs are ideal input for `fail2ban` or firewall blocklists — every single hit is a provable violation.

---

## Privacy & IP Address Storage

> ⚠️ **Note for operators subject to GDPR / EU privacy law**

When `LABYRINTH_LOG_VISITS` is enabled, **IP addresses are stored in the PHP error log**. This serves the sole purpose of **temporarily defending against attacks** (bots, scrapers, automated crawlers) and is technically necessary to:

- Identify which IPs are actively exploiting your site
- Detect repeat offenders and block them (e.g. via `fail2ban`, firewall rules)
- Verify the effectiveness of the measure

### Legal basis

Under Art. 6(1)(f) GDPR (legitimate interest), **short-term storage of IP addresses for attack mitigation** is generally permissible when:

1. Storage is **time-limited** (recommended: max. 7 days, ideally 24–72 hours)
2. Data is **not used for any other purpose**
3. This is **mentioned in the site's privacy policy**

### Recommended log rotation

```bash
# /etc/logrotate.d/php-labyrinth
/var/log/php/error.log {
    daily
    rotate 3          # keep only 3 days
    compress
    missingok
    notifempty
    postrotate
        systemctl reload php8.2-fpm 2>/dev/null || true
    endscript
}
```

### If you don't want to store IPs

Simply disable in `config.php`:

```php
define('LABYRINTH_LOG_VISITS', false);
```

Or pseudonymise IPs before logging:

```php
// In labyrinth.php, adjust the logging section:
$hashed_ip = hash('sha256', $client_ip . 'your-salt-here');
error_log("AI Labyrinth: IP={$hashed_ip} UA=... page={$page_id} depth={$depth}");
```

> 💡 **Privacy policy**: If you log IPs, add a note such as: *"IP addresses may be stored temporarily (max. 7 days) when security mechanisms on this website are triggered, for the purpose of mitigating automated attacks."*

---

## Apache Integration

See [`.htaccess.example`](.htaccess.example) for the complete file:

- Clean-URL rewrite: `/research/articles` → `articles.php`
- Bot-monitor rules: honeypot path + AI-crawler User-Agents on sensitive paths → `bot-monitor.php`
- Block direct access to `config.php`
- Security and caching headers

```apache
RewriteEngine On
RewriteRule ^research/articles$ /research/articles.php [L,QSA]
```

---

## Nginx Integration

```nginx
location /research/articles {
    try_files $uri /research/articles.php?$query_string;
}

# Block direct access to config.php / generator.php
location ~ /(config|generator)\.php$ {
    deny all;
}
```

---

## Caddy Integration

```caddy
# Caddyfile
example.com {
    # PHP via FPM (socket or TCP)
    php_fastcgi unix//run/php/php8.2-fpm.sock

    # Clean URL: /research/articles → articles.php
    rewrite /research/articles* /research/articles.php?{query}

    # Block direct config/generator access
    respond /research/config.php 403
    respond /research/generator.php 403
}
```

> Caddy starts with automatic HTTPS — no additional TLS configuration needed.

---

## Traefik Integration

Traefik only forwards HTTP traffic; PHP is executed by a backend service (e.g. a PHP-FPM container). The labyrinth files live in the web server container (Apache/Nginx), with Traefik acting as the reverse proxy in front.

### Docker Compose example

```yaml
# docker-compose.yml
services:
  php-app:
    image: php:8.2-apache              # or nginx + php-fpm
    volumes:
      - ./research:/var/www/html/research   # articles.php + generator.php + config.php
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.labyrinth.rule=Host(`example.com`)"
      - "traefik.http.routers.labyrinth.entrypoints=websecure"
      - "traefik.http.routers.labyrinth.tls.certresolver=letsencrypt"
      - "traefik.http.services.labyrinth.loadbalancer.server.port=80"

  traefik:
    image: traefik:v3
    command:
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.tlschallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.email=your@email.com"
      - "--providers.docker=true"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - "/var/run/docker.sock:/var/run/docker.sock:ro"
      - "./acme.json:/acme.json"
```

### Clean URL via Traefik middleware (optional)

To hide the `.php` extension without touching the web server config:

```yaml
labels:
  # Middleware: /research/articles → /research/articles.php
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.regex=^/research/articles$"
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.replacement=/research/articles.php"
  - "traefik.http.routers.labyrinth.middlewares=labyrinth-rewrite"
```

> Block `config.php` / `generator.php` at the web server level (`.htaccess` / Nginx `deny all`) rather than via a Traefik middleware — that is more reliable.

---

## Testing

A smoke test suite covers the core promises (deterministic pages, per-page variety, noindex headers, direct gateway rendering, localized dates):

```bash
tests/smoke.sh
```

It lints all PHP files, boots PHP's built-in web server, and probes the endpoints — the same script runs in CI (GitHub Actions, PHP 7.4 and 8.4).

---

## Security Notes

- `config.php` and `generator.php` contain no secrets — safe to commit; blocking direct web access to them is still good hygiene
- For extra caution, place `config.php` outside the web root and adjust the `require_once` path in `labyrinth.php`
- No database access — no SQL injection surface
- All output is run through `htmlspecialchars()` — no XSS risk from URL parameters
- Logged values (User-Agent, paths) are sanitized against log injection; log volume is rate-limited per IP

---

## Customization Ideas

- **Multi-language**: Duplicate `config.php` + word pools per locale, serve based on `Accept-Language` header
- **Niche-matched content**: Replace topics and word pools with content matching your actual site — the more convincing, the more effective
- **Analytics event**: Fire a server-side event on each trap hit (Umami, Plausible, Matomo)
- **fail2ban integration**: Feed `BOT_MONITOR:` log lines into a fail2ban jail — every hit is a proven robots.txt violation

---

## License

MIT — see [LICENSE](LICENSE)

---

## Credits

Concept inspired by [Cloudflare AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/) (2025).  
PHP implementation by [Philipp Gabriel](https://github.com/PhilGabriel).
