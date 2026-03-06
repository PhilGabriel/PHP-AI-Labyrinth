# PHP AI Labyrinth 🤖🔀

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
- Any web server (Apache, Nginx, Caddy, …)

No database. No dependencies. Drop in two files and go.

---

## Installation

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
```

Optionally replace the German topics, authors and paragraphs with content in your language or niche.

### 3. Add a hidden entry link to your site

The entry point must be invisible to humans but follow-able by bots:

```html
<!-- Somewhere in your page HTML, e.g. footer -->
<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> **Why not just block bots?** Blocking reveals you know about them. A labyrinth silently wastes their compute budget instead.

### 4. Exclude from `robots.txt`

The trap path must be **absent** from `robots.txt` (or explicitly allowed) so that bots *think* it's fair game:

```
# robots.txt — do NOT disallow your labyrinth path
User-agent: *
Disallow: /admin/
Disallow: /api/
# /trap/ is intentionally not listed here
```

See [`robots.txt.example`](robots.txt.example) for a complete example.

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

### Example: count trap hits per IP (bash)

```bash
grep 'AI Labyrinth' /var/log/php/error.log | awk -F'IP=' '{print $2}' | cut -d' ' -f1 | sort | uniq -c | sort -rn | head
```

---

## Apache Integration

Use the included [`.htaccess.example`](.htaccess.example) to:

- Rewrite `/research/articles` (clean URL) → `labyrinth.php`
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

# Block direct config access
location ~ /config\.php$ {
    deny all;
}
```

---

## Security Notes

- `config.php` contains no secrets — it's safe to commit
- Consider placing `config.php` outside the web root and adjusting the `require_once` path in `labyrinth.php` for extra caution
- The trap never reads from or writes to a database, so there is no SQL injection surface
- All output is run through `htmlspecialchars()` — no XSS risk from URL parameters

---

## Customization Ideas

- **Multi-language**: Duplicate `config.php` per locale, serve based on `Accept-Language` header
- **Domain-matched content**: Adjust topics to match your actual site niche for more convincing content
- **Analytics integration**: Fire a server-side analytics event on each trap hit
- **Rate-aware response**: Slow responses with `usleep()` for high-frequency crawlers

---

## License

MIT — see [LICENSE](LICENSE)

---

## Credits

Concept inspired by [Cloudflare AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/) (2025).  
PHP implementation by [Philipp Gabriel](https://github.com/PhilGabriel).
