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

## Installation & Setup

### 1. Dateien kopieren

```bash
# Repo klonen
git clone https://github.com/PhilGabriel/PHP-AI-Labyrinth.git

# In dein Projekt kopieren (Beispielpfad)
cp PHP-AI-Labyrinth/labyrinth.php  /var/www/html/trap/labyrinth.php
cp PHP-AI-Labyrinth/config.php     /var/www/html/trap/config.php
```

### 2. `config.php` anpassen

Mindestens den Base-Path auf deinen tatsächlichen URL-Pfad setzen:

```php
define('LABYRINTH_BASE_PATH', '/trap/labyrinth.php');
define('LABYRINTH_SITE_NAME', 'Meine Webseite');
```

Optional: Topics, Autoren und Absätze in deiner Sprache und Nische eintragen — je glaubwürdiger der Inhalt zum Rest deiner Seite passt, desto effektiver die Falle.

### 3. Einstiegslink auf deiner Seite verstecken

Bots folgen Links — Menschen sehen diesen nicht:

```html
<!-- Im Footer oder einer beliebigen Seite -->
<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> **Warum nicht einfach blocken?** Blockieren verrät dem Bot, dass er erkannt wurde. Ein Labyrinth vergeudet still sein Compute-Budget, ohne ihn zu warnen.

### 4. `robots.txt` — den Trap-Pfad *nicht* eintragen

Der Trap-Pfad darf **nicht** unter `Disallow` stehen — sonst überspringen Bots, die `robots.txt` respektieren, die Falle. Nur aggressive Scraper, die `robots.txt` ignorieren, laufen hinein — und genau die sollen rein.

```
# robots.txt — Trap-Pfad absichtlich nicht disallowed
User-agent: *
Disallow: /admin/
Disallow: /api/
# /trap/ ist hier bewusst nicht gelistet
```

Siehe [`robots.txt.example`](robots.txt.example) für ein vollständiges Beispiel.

### 5. Webserver konfigurieren (optional: Clean URLs)

**Apache** — in `.htaccess` oder VHost:
```apache
RewriteEngine On
RewriteRule ^trap/articles$   /trap/labyrinth.php   [L,QSA]
```

**Nginx** — in der `server`-Block-Konfiguration:
```nginx
location /trap/articles {
    try_files $uri /trap/labyrinth.php?$query_string;
}
location ~ /config\.php$ { deny all; }
```

Damit erscheint der Trap als saubere URL `/trap/articles?p=…&d=…` ohne `.php`-Endung.

---

## WordPress Integration

PHP AI Labyrinth lässt sich in WordPress ohne Plugin einbinden.

### Option A: Eigene PHP-Datei neben WordPress (empfohlen)

WordPress läuft typischerweise im Webroot. Lege `labyrinth.php` und `config.php` einfach in einen Unterordner:

```
/var/www/html/
├── wp-config.php         ← WordPress
├── wp-content/
├── index.php
└── trap/
    ├── labyrinth.php     ← Labyrinth (eigenständig, kein WP-Bootstrap)
    └── config.php
```

WordPress wird dabei **nicht** geladen — die Dateien laufen vollkommen unabhängig. Kein Plugin, kein Hook nötig.

In `config.php` setzen:
```php
define('LABYRINTH_BASE_PATH', '/trap/labyrinth.php');
```

Den Einstiegslink in die `footer.php` des aktiven Themes einfügen:
```php
// wp-content/themes/dein-theme/footer.php
<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> Bei Theme-Updates geht der Footer-Eintrag verloren — besser ein **Child-Theme** nutzen oder einen `wp_footer`-Hook in der `functions.php`:

```php
// functions.php des Child-Themes
add_action('wp_footer', function () {
    echo '<a href="/trap/labyrinth.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>';
});
```

### Option B: Als Custom WordPress Page Template

Für vollständige WordPress-Integration kann `labyrinth.php` als Page-Template angelegt werden. Achtung: Dann lädt WordPress den gesamten Bootstrap (DB-Verbindung, Plugins, etc.) mit — es sei denn, der WP-Bootstrap wird gezielt mit `define('SHORTINIT', true)` unterbunden.

Diese Option ist nur sinnvoll, wenn du die WP-Rewrite-Engine für saubere URLs brauchst.

---

## Mögliche Konflikte

| Situation | Problem | Lösung |
|---|---|---|
| **Security-Plugins** (Wordfence, iThemes) | Scannen den Labyrinth-Pfad selbst und lösen Alarme aus | Trap-Pfad in der Plugin-Whitelist eintragen oder den Pfad unscheinbarer benennen |
| **Caching-Plugins / CDN** | Gecachte Seiten liefern immer dieselbe Seite — Bots laufen im Kreis der selben 1–2 Seiten | Trap-Pfad vom Cache ausschließen (z. B. in W3 Total Cache: „Never cache pages: `/trap/.*`") |
| **`robots.txt` Plugins** | Plugins wie Yoast SEO können automatisch alle Nicht-WP-Pfade disallowed eintragen | Trap-Pfad in den Plugin-Einstellungen aus dem `robots.txt`-Block entfernen |
| **WAF / Cloudflare Firewall** | Eigene WAF-Regeln können den Trap-Traffic blocken, bevor er PHP erreicht | Trap-Pfad in WAF-Bypass-Regeln aufnehmen (`uri contains /trap/ → skip`) |
| **Rate Limiting** | Dein eigenes Rate Limiting blockt aggressive Bots, bevor sie tief ins Labyrinth laufen | Trap-Pfad vom Rate Limiting ausnehmen — du willst Bots möglichst viele Requests machen lassen |
| **PHP `open_basedir`** | Restrictive `open_basedir`-Einstellungen können `require_once` für `config.php` blockieren | Beide Dateien im selben Verzeichnis ablegen oder `open_basedir` anpassen |
| **Shared Hosting** | Kein Zugriff auf `error_log()` oder Logs nicht sichtbar | `LABYRINTH_LOG_VISITS` auf `false` setzen; alternativ in eine eigene Logdatei schreiben |

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

### Beispiel: Trap-Hits pro IP zählen (bash)

```bash
grep 'AI Labyrinth' /var/log/php/error.log | awk -F'IP=' '{print $2}' | cut -d' ' -f1 | sort | uniq -c | sort -rn | head
```

---

## Datenschutz & IP-Speicherung

> ⚠️ **Hinweis für Betreiber in der EU / DSGVO-Kontext**

Wenn `LABYRINTH_LOG_VISITS` aktiviert ist, werden **IP-Adressen im PHP-Error-Log** gespeichert. Das dient ausschließlich der **temporären Abwehr von Angriffen** (Bots, Scraper, automatisierte Crawler) und ist technisch notwendig, um:

- festzustellen, welche IPs den Trap aktiv ausnutzen
- wiederkehrende Angreifer erkennen und blocken zu können (z. B. via `fail2ban`, Firewall-Rules)
- die Effektivität der Maßnahme zu überprüfen

### Rechtliche Einordnung

Nach Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse) ist die **kurzfristige Speicherung von IP-Adressen zur Abwehr von Angriffen** in der Regel zulässig, wenn:

1. die Speicherung **zeitlich begrenzt** ist (empfohlen: max. 7 Tage, im Idealfall 24–72 Stunden)
2. die Daten **nicht zu anderen Zwecken** verwendet werden
3. dies in der **Datenschutzerklärung** der Webseite erwähnt wird

### Empfehlung zur Log-Rotation

```bash
# /etc/logrotate.d/php-labyrinth
/var/log/php/error.log {
    daily
    rotate 3          # nur 3 Tage aufheben
    compress
    missingok
    notifempty
    postrotate
        # PHP-FPM ggf. neu laden
        systemctl reload php8.2-fpm 2>/dev/null || true
    endscript
}
```

### Wenn du keine IPs speichern möchtest

In `config.php` einfach deaktivieren:

```php
define('LABYRINTH_LOG_VISITS', false);
```

Oder IPs vor dem Loggen hashen (Pseudonymisierung):

```php
// In labyrinth.php, Logging-Abschnitt anpassen:
$hashed_ip = hash('sha256', ($client_ip ?? '') . 'your-salt-here');
error_log("AI Labyrinth: IP={$hashed_ip} UA={$user_agent} page={$page_id} depth={$depth}");
```

> 💡 **Datenschutzerklärung**: Wenn du IP-Adressen loggst, ergänze deine Datenschutzerklärung um einen Hinweis wie: *„Bei Zugriffen auf Sicherheitsmechanismen unserer Website können IP-Adressen temporär (max. 7 Tage) zur Abwehr automatisierter Angriffe gespeichert werden."*

---

## Apache Integration

Siehe [`htaccess.example`](.htaccess.example) für:

- Clean-URL-Rewrite: `/research/articles` → `labyrinth.php`
- Zugriff auf `config.php` blockieren
- Caching-Header setzen

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

# config.php vor direktem Zugriff schützen
location ~ /config\.php$ {
    deny all;
}
```

---

## Sicherheitshinweise

- `config.php` enthält keine Secrets — sicher zu committen
- Für zusätzliche Sicherheit `config.php` außerhalb des Web-Roots ablegen und den `require_once`-Pfad in `labyrinth.php` anpassen
- Kein Datenbankzugriff — kein SQL-Injection-Risiko
- Alle Ausgaben werden durch `htmlspecialchars()` gesichert — kein XSS-Risiko durch URL-Parameter

---

## Weitere Ideen

- **Mehrsprachig**: `config.php` pro Locale duplizieren, anhand `Accept-Language`-Header ausliefern
- **Themenpassend**: Topics an deine echten Seiteninhalte anpassen — je glaubwürdiger, desto effektiver
- **Analytics-Event**: Bei jedem Trap-Hit einen Server-seitigen Event feuern (Umami, Plausible, Matomo)
- **Künstliche Verzögerung**: `usleep(500000)` für hochfrequente Crawler — kostet dich kaum etwas, kostet den Bot Zeit

---

## License

MIT — see [LICENSE](LICENSE)

---

## Credits

Concept inspired by [Cloudflare AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/) (2025).  
PHP implementation by [Philipp Gabriel](https://github.com/PhilGabriel).
