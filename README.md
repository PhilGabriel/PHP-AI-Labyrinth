# PHP AI Labyrinth 🤖🔀

> 🌐 **Language / Sprache:** **🇬🇧 English** · [🇩🇪 Deutsch](README.de.md)

A lightweight, self-hosted **bot trap** that wastes AI crawler resources by generating infinite, realistic-looking fake article pages — each linking to more fake pages.

Inspired by [Cloudflare's AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/).

---

## How It Works

```
Human visits your site  →  never sees the trap (CSS hidden link)
Bot/Crawler visits      →  follows hidden link
                        →  lands on a realistic-looking article
                        →  follows 6–12 "related article" links
                        →  lands on more fake articles
                        →  ... forever
```

- Pages are **deterministic** — the same `?p=` ID always generates the same content (no database needed, pure PHP)
- Every page sends `X-Robots-Tag: noindex, nofollow` and `<meta robots="noindex,nofollow">` so the trap never pollutes real search results
- Depth counter prevents log explosion; resets silently after `LABYRINTH_MAX_DEPTH`
- Legitimate crawlers that respect `robots.txt` never reach the trap

---

## Requirements

- PHP 7.4+
- Any web server (Apache, Nginx, Caddy, Traefik, …)

No database. No dependencies. Drop in two files and go.

---

## Installation & Setup

### 1. Copy the files

```bash
# Clone the repo
git clone https://github.com/PhilGabriel/PHP-AI-Labyrinth.git

# Copy to your project (example path)
cp PHP-AI-Labyrinth/labyrinth.php  /var/www/html/trap/labyrinth.php
cp PHP-AI-Labyrinth/config.php     /var/www/html/trap/config.php
```

### 2. Adjust `config.php`

At minimum, set your base path:

```php
define('LABYRINTH_BASE_PATH', '/trap/labyrinth.php');
define('LABYRINTH_SITE_NAME', 'My Website');
```

Optionally replace the German topics, authors and paragraphs with content in your language and niche — the more convincing the content matches your site, the more effective the trap.

### 3. Add a hidden entry link to your site

Bots follow links — humans never see this one:

```html
<!-- In your footer or any page -->
<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> **Why not just block bots?** Blocking reveals that you detected them. A labyrinth silently wastes their compute budget without tipping them off.

### 4. Keep the trap path out of `robots.txt`

The trap path must **not** appear under `Disallow` — otherwise bots that respect `robots.txt` skip it entirely. Only aggressive scrapers that ignore `robots.txt` walk in — which is exactly who you want to catch.

```
# robots.txt — trap path intentionally absent
User-agent: *
Disallow: /admin/
Disallow: /api/
# /trap/ is deliberately not listed here
```

See [`robots.txt.example`](robots.txt.example) for a full example.

### 5. Configure your web server (optional: clean URLs)

**Apache** — in `.htaccess` or VHost:
```apache
RewriteEngine On
RewriteRule ^trap/articles$   /trap/labyrinth.php   [L,QSA]
```

**Nginx** — in the `server` block:
```nginx
location /trap/articles {
    try_files $uri /trap/labyrinth.php?$query_string;
}
location ~ /config\.php$ { deny all; }
```

This exposes the trap as a clean URL `/trap/articles?p=…&d=…` without the `.php` extension.

---

## WordPress Integration

PHP AI Labyrinth integrates into WordPress without any plugin.

### Option A: Standalone PHP file alongside WordPress (recommended)

WordPress typically runs at the web root. Just drop `labyrinth.php` and `config.php` into a subdirectory:

```
/var/www/html/
├── wp-config.php         ← WordPress
├── wp-content/
├── index.php
└── trap/
    ├── labyrinth.php     ← Labyrinth (standalone, no WP bootstrap)
    └── config.php
```

WordPress is **not loaded** — the files run completely independently. No plugin, no hook required.

In `config.php` set:
```php
define('LABYRINTH_BASE_PATH', '/trap/labyrinth.php');
```

Add the entry link to the active theme's `footer.php`:
```php
// wp-content/themes/your-theme/footer.php
<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> Theme updates will overwrite `footer.php` — use a **child theme** or a `wp_footer` hook in `functions.php` instead:

```php
// Child theme functions.php
add_action('wp_footer', function () {
    echo '<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>';
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
| **Caching plugins / CDN** | Cached pages always return the same page — bots loop through only 1–2 pages | Exclude the trap path from cache (e.g. W3 Total Cache: "Never cache pages: `/trap/.*`") |
| **`robots.txt` plugins** | Plugins like Yoast SEO may auto-disallow non-WP paths | Remove the trap path from the plugin's disallow list |
| **WAF / Cloudflare Firewall** | Your own WAF rules block trap traffic before it reaches PHP | Add the trap path to WAF bypass rules (`uri contains /trap/ → skip`) |
| **Rate limiting** | Your own rate limiting blocks aggressive bots before they go deep | Exclude the trap path from rate limiting — you *want* bots to send many requests |
| **PHP `open_basedir`** | Restrictive `open_basedir` settings can block `require_once` for `config.php` | Place both files in the same directory, or adjust `open_basedir` |
| **Shared hosting** | No access to `error_log()` output or logs not visible | Set `LABYRINTH_LOG_VISITS` to `false`, or write to a custom log file |

---

## Configuration Reference

All settings live in `config.php`:

| Constant | Default | Description |
|---|---|---|
| `LABYRINTH_MAX_DEPTH` | `100` | Depth cap before silent reset |
| `LABYRINTH_LINKS_MIN` | `6` | Minimum outgoing links per page |
| `LABYRINTH_LINKS_MAX` | `12` | Maximum outgoing links per page |
| `LABYRINTH_PARAGRAPHS_MIN` | `4` | Minimum paragraphs per page |
| `LABYRINTH_PARAGRAPHS_MAX` | `8` | Maximum paragraphs per page |
| `LABYRINTH_SITE_NAME` | `'Fachredaktion'` | Shown in footer |
| `LABYRINTH_BASE_PATH` | `'/research/articles.php'` | Self-referencing URL path |
| `LABYRINTH_LOG_VISITS` | `true` | Log bot visits via `error_log()` |
| `LABYRINTH_LOCALE` | `'de_DE.UTF-8'` | Locale for date formatting |

Arrays for topics, paragraphs, authors and subheadings are in the same file and can be freely extended.

---

## Monitoring

When `LABYRINTH_LOG_VISITS` is `true`, each visit is written to your PHP error log:

```
AI Labyrinth: IP=66.249.66.1 UA=Googlebot/2.1 page=3b4c1d2e depth=7
```

Parse this with any log aggregator (grep, GoAccess, Loki, …) to see which bots are hitting the trap.

### Count trap hits per IP (bash)

```bash
grep 'AI Labyrinth' /var/log/php/error.log | awk -F'IP=' '{print $2}' | cut -d' ' -f1 | sort | uniq -c | sort -rn | head
```

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
$hashed_ip = hash('sha256', ($client_ip ?? '') . 'your-salt-here');
error_log("AI Labyrinth: IP={$hashed_ip} UA={$user_agent} page={$page_id} depth={$depth}");
```

> 💡 **Privacy policy**: If you log IPs, add a note such as: *"IP addresses may be stored temporarily (max. 7 days) when security mechanisms on this website are triggered, for the purpose of mitigating automated attacks."*

---

## Apache Integration

See [`.htaccess.example`](.htaccess.example) for:

- Clean-URL rewrite: `/research/articles` → `labyrinth.php`
- Block direct access to `config.php`
- Set caching headers

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

# Block direct access to config.php
location ~ /config\.php$ {
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

    # Clean URL: /trap/articles → labyrinth.php
    rewrite /trap/articles* /trap/labyrinth.php?{query}

    # Block direct config access
    respond /trap/config.php 403
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
    image: php:8.2-apache          # or nginx + php-fpm
    volumes:
      - ./trap:/var/www/html/trap   # labyrinth.php + config.php
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
  # Middleware: /trap/articles → /trap/labyrinth.php
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.regex=^/trap/articles(.*)"
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.replacement=/trap/labyrinth.php$$1"
  - "traefik.http.routers.labyrinth.middlewares=labyrinth-rewrite"
```

### Block `config.php` via Traefik

```yaml
labels:
  # Return 403 for any request targeting config.php
  - "traefik.http.middlewares.block-config.redirectregex.regex=.*config\\.php.*"
  - "traefik.http.middlewares.block-config.redirectregex.permanent=false"
```

> Alternatively (and more reliably): block `config.php` at the web server level (`.htaccess` / Nginx `deny all`) rather than via a Traefik middleware.

---

## Security Notes

- `config.php` contains no secrets — safe to commit
- For extra caution, place `config.php` outside the web root and adjust the `require_once` path in `labyrinth.php`
- No database access — no SQL injection surface
- All output is run through `htmlspecialchars()` — no XSS risk from URL parameters

---

## Customization Ideas

- **Multi-language**: Duplicate `config.php` per locale, serve based on `Accept-Language` header
- **Niche-matched content**: Replace topics with content matching your actual site — the more convincing, the more effective
- **Analytics event**: Fire a server-side event on each trap hit (Umami, Plausible, Matomo)
- **Artificial delay**: `usleep(500000)` for high-frequency crawlers — costs you almost nothing, costs the bot time

---

## License

MIT — see [LICENSE](LICENSE)

---

## Credits

Concept inspired by [Cloudflare AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/) (2025).  
PHP implementation by [Philipp Gabriel](https://github.com/PhilGabriel).
