# PHP AI Labyrinth 🤖🔀

> 🌐 **Sprache / Language:** [🇬🇧 English](README.md) · **🇩🇪 Deutsch**

Ein leichtgewichtiger, selbst gehosteter **Bot-Trap**, der KI-Crawler-Ressourcen verschwendet, indem er endlos realistische Fake-Artikel-Seiten generiert — jede verlinkt auf weitere Fake-Seiten.

Inspiriert von [Cloudflare's AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/).

---

## Wie es funktioniert

```
Mensch besucht die Seite  →  sieht die Falle nie (CSS-versteckter Link)
Bot/Crawler besucht       →  folgt verstecktem Link
                          →  landet auf realistisch aussehendem Artikel
                          →  folgt 6–12 „verwandten Artikel"-Links
                          →  landet auf weiteren Fake-Artikeln
                          →  ... für immer
```

- Seiten sind **deterministisch** — dieselbe `?p=` ID erzeugt immer denselben Inhalt (keine Datenbank nötig, reines PHP)
- Jede Seite sendet `X-Robots-Tag: noindex, nofollow` und `<meta robots="noindex,nofollow">` — der Trap taucht nie in echten Suchergebnissen auf
- Tiefenzähler verhindert Log-Explosion; stummes Reset nach `LABYRINTH_MAX_DEPTH`
- Legitime Crawler, die `robots.txt` respektieren, erreichen den Trap nie

---

## Voraussetzungen

- PHP 7.4+
- Beliebiger Webserver (Apache, Nginx, Caddy, …)

Keine Datenbank. Keine Abhängigkeiten. Zwei Dateien ablegen — fertig.

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

## Konfigurationsreferenz

Alle Einstellungen in `config.php`:

| Konstante | Standard | Beschreibung |
|---|---|---|
| `LABYRINTH_MAX_DEPTH` | `100` | Maximale Tiefe vor stummen Reset |
| `LABYRINTH_LINKS_MIN` | `6` | Mindestanzahl ausgehender Links pro Seite |
| `LABYRINTH_LINKS_MAX` | `12` | Maximale ausgehende Links pro Seite |
| `LABYRINTH_PARAGRAPHS_MIN` | `4` | Mindestanzahl Absätze pro Seite |
| `LABYRINTH_PARAGRAPHS_MAX` | `8` | Maximale Absätze pro Seite |
| `LABYRINTH_SITE_NAME` | `'Fachredaktion'` | Wird im Footer angezeigt |
| `LABYRINTH_BASE_PATH` | `'/research/articles.php'` | Selbstreferenzierender URL-Pfad |
| `LABYRINTH_LOG_VISITS` | `true` | Bot-Besuche via `error_log()` protokollieren |
| `LABYRINTH_LOCALE` | `'de_DE.UTF-8'` | Locale für Datumsformatierung |

Topics, Absätze, Autoren und Zwischenüberschriften sind ebenfalls in der Datei und können beliebig erweitert werden.

---

## Monitoring

Wenn `LABYRINTH_LOG_VISITS` aktiviert ist, wird jeder Besuch ins PHP-Error-Log geschrieben:

```
AI Labyrinth: IP=66.249.66.1 UA=Googlebot/2.1 page=3b4c1d2e depth=7
```

Mit beliebigem Log-Aggregator (grep, GoAccess, Loki, …) auswerten, um zu sehen, welche Bots in der Falle stecken.

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

## Caddy Integration

```caddy
# Caddyfile
example.com {
    # PHP via FPM (socket oder TCP)
    php_fastcgi unix//run/php/php8.2-fpm.sock

    # Clean URL: /trap/articles → labyrinth.php
    rewrite /trap/articles* /trap/labyrinth.php?{query}

    # config.php sperren
    respond /trap/config.php 403
}
```

> Caddy startet mit automatischem HTTPS — kein weiterer TLS-Aufwand nötig.

---

## Traefik Integration

Traefik selbst leitet nur HTTP-Traffic weiter; PHP wird von einem dahinterliegenden Dienst (z. B. PHP-FPM-Container) ausgeführt. Die Labyrinth-Dateien liegen im Webserver-Container (Apache/Nginx), Traefik ist der Reverse Proxy davor.

### Docker Compose Beispiel

```yaml
# docker-compose.yml
services:
  php-app:
    image: php:8.2-apache          # oder nginx + php-fpm
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
      - "--certificatesresolvers.letsencrypt.acme.email=deine@email.de"
      - "--providers.docker=true"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - "/var/run/docker.sock:/var/run/docker.sock:ro"
      - "./acme.json:/acme.json"
```

### Clean URL via Traefik Middleware (optional)

Wenn du die `.php`-Endung aus der URL verstecken willst, ohne den Webserver anzufassen:

```yaml
labels:
  # Middleware: /trap/articles → /trap/labyrinth.php
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.regex=^/trap/articles(.*)"
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.replacement=/trap/labyrinth.php$$1"
  - "traefik.http.routers.labyrinth.middlewares=labyrinth-rewrite"
```

### config.php via Traefik sperren

```yaml
labels:
  # Alle Requests auf config.php mit 403 abweisen
  - "traefik.http.middlewares.block-config.redirectregex.regex=.*config\\.php.*"
  - "traefik.http.middlewares.block-config.redirectregex.permanent=false"
```

> Alternativ (besser): `config.php` auf Webserver-Ebene sperren (`.htaccess` / Nginx `deny all`) — das ist zuverlässiger als eine Traefik-Middleware.

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

## Lizenz

MIT — siehe [LICENSE](LICENSE)

---

## Credits

Konzept inspiriert von [Cloudflare AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/) (2025).  
PHP-Implementierung von [Philipp Gabriel](https://github.com/PhilGabriel).
