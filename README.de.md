# PHP AI Labyrinth 🤖🔀

> 🌐 **Sprache / Language:** [🇬🇧 English](README.md) · **🇩🇪 Deutsch**

Ein leichtgewichtiger, selbst gehosteter **Bot-Trap**, der KI-Crawler-Ressourcen verschwendet, indem er endlos realistische Fake-Artikel-Seiten generiert — jede verlinkt auf weitere Fake-Seiten.

Inspiriert von [Cloudflare's AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/).

---

## Wie es funktioniert

```
Mensch besucht die Seite   →  sieht die Falle nie (CSS-versteckter Link)
Regelkonformer KI-Crawler  →  respektiert robots.txt, bleibt komplett fern
Aggressiver Bot/Scraper    →  ignoriert robots.txt, folgt Link oder Honeypot
                           →  landet auf realistisch aussehendem Artikel
                           →  folgt 6–12 „verwandten Artikel"-Links
                           →  landet auf weiteren Fake-Artikeln
                           →  ... für immer, und verbrennt auf jeder Seite Tokens
```

Drei Mechanismen greifen ineinander:

1. **Das Labyrinth** (`labyrinth.php`) — generiert endlose Fake-Artikel-Seiten. Ein versteckter Link auf deiner echten Seite ist der Eingang; Menschen sehen ihn nie, regelkonforme Crawler werden per `robots.txt` ferngehalten.
2. **Der Content-Generator** (`generator.php`) — baut Artikeltext aus kombinatorischen Satz-Templates. Jede Seite ist einzigartig — Crawler-Deduplizierung kann das Labyrinth nicht auf eine Handvoll wiederholter Seiten eindampfen. Maximaler Token-Burn pro Seite.
3. **Der Bot-Monitor** (`bot-monitor.php`, optional) — erwischt bekannte KI-Crawler auf Pfaden, die deine `robots.txt` verbietet, loggt den Verstoß und liefert ihnen **direkt** eine Labyrinth-Seite aus (kein Redirect, den ein Bot ignorieren könnte).

Zentrale Eigenschaften:

- Seiten sind **deterministisch** — dieselbe `?p=` ID erzeugt immer denselben Inhalt (keine Datenbank nötig, reines PHP), Antworten bleiben also cachebar
- Jede Seite sendet `X-Robots-Tag: noindex, nofollow` und `<meta robots="noindex,nofollow">` — der Trap taucht nie in echten Suchergebnissen auf
- Tiefenzähler verhindert Log-Explosion; stummes Reset nach `LABYRINTH_MAX_DEPTH`
- Eingebautes Rate-Limiting pro IP und Schutz vor Log-Flooding
- Optionales **Tarpit-Delay** bremst Crawler aus — kostet dich fast nichts

---

## Voraussetzungen

- PHP 7.4+
- Beliebiger Webserver (Apache, Nginx, Caddy, Traefik, …)

Keine Datenbank. Keine Abhängigkeiten. Drei Dateien ablegen — fertig.

---

## Installation & Setup

### 1. Dateien kopieren

> ⚠️ **Gib dem Einstiegs-Skript einen unauffälligen Namen.** Eine URL mit `labyrinth` oder `trap` verrät einem cleveren Crawler sofort, wo er gelandet ist. `articles.php` unter `/research/` fällt nicht auf.

```bash
# Repo klonen
git clone https://github.com/PhilGabriel/PHP-AI-Labyrinth.git

# In dein Projekt kopieren (Beispielpfad — Einstiegs-Skript umbenannt)
cp PHP-AI-Labyrinth/labyrinth.php  /var/www/html/research/articles.php
cp PHP-AI-Labyrinth/generator.php  /var/www/html/research/generator.php
cp PHP-AI-Labyrinth/config.php     /var/www/html/research/config.php
```

`generator.php` und `config.php` müssen ihre Namen behalten (das Einstiegs-Skript bindet sie namentlich ein); nur das Einstiegs-Skript selbst darf umbenannt werden.

### 2. `config.php` anpassen

Mindestens Base-Path und Seitennamen setzen:

```php
define('LABYRINTH_BASE_PATH', '/research/articles.php');
define('LABYRINTH_SITE_NAME', 'Meine Webseite');
```

Optional: Topics, Autoren und Wortpools an deine Sprache und Nische anpassen — je glaubwürdiger der Inhalt zum Rest deiner Seite passt, desto effektiver die Falle (siehe [Content-Generator](#content-generator)).

### 3. Einstiegslink auf deiner Seite verstecken

Bots folgen Links — Menschen sehen diesen nicht:

```html
<!-- Im Footer oder einer beliebigen Seite -->
<a href="/research/articles.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>
```

> **Warum nicht einfach blocken?** Blockieren verrät dem Bot, dass er erkannt wurde. Ein Labyrinth vergeudet still sein Compute-Budget, ohne ihn zu warnen.

### 4. `robots.txt` einrichten

Drei Regeln machen die Falle scharf (vollständige Datei: [`robots.txt.example`](robots.txt.example)):

1. **Der Labyrinth-Pfad darf NICHT disallowed sein.** Sonst überspringen Bots, die `robots.txt` respektieren, die Falle — aber die sind ohnehin nicht dein Problem. Nur aggressive Crawler folgen dem versteckten Link.
2. **Einen Honeypot-Pfad disallowen** (z. B. `/trap/`), auf den nichts Legitimes je verlinkt. Jeder Request dorthin ist per Definition ein robots.txt-Verstoß — und wird ins Labyrinth geleitet (siehe [Bot-Monitor](#bot-monitor-robotstxt-verstöße-abfangen-optional)).
3. **KI-Trainings-Crawler komplett aussperren.** Regelkonforme (die meisten großen Betreiber) halten sich daran und verschwinden. Wer die Regel ignoriert, läuft direkt in deine Fallen.

```
# KI/LLM-Crawler: komplette Seite tabu
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
# /research/articles.php fehlt hier absichtlich — das ist der Labyrinth-Eingang
```

### 5. Webserver konfigurieren (optional: Clean URLs)

**Apache** — in `.htaccess` oder VHost:
```apache
RewriteEngine On
RewriteRule ^research/articles$   /research/articles.php   [L,QSA]
```

**Nginx** — im `server`-Block:
```nginx
location /research/articles {
    try_files $uri /research/articles.php?$query_string;
}
location ~ /(config|generator)\.php$ { deny all; }
```

Damit erscheint der Trap als saubere URL `/research/articles?p=…&d=…` ohne `.php`-Endung.

---

## Bot-Monitor: robots.txt-Verstöße abfangen (optional)

`bot-monitor.php` ist der zweite Fallenmechanismus. Er zielt auf Bots, die deine Regeln **nachweislich** ignorieren:

- Requests auf den **Honeypot-Pfad** (`/trap/` — in `robots.txt` disallowed, von nirgendwo verlinkt)
- Requests von **bekannten KI-Crawler-User-Agents** auf sensible Pfade (`/admin`, `/wp-login.php`, `/.env`, …)

Jeder Treffer wird geloggt (`BOT_MONITOR: IP=… UA=… path=…`) und der Bot bekommt **in derselben Antwort** eine Labyrinth-Seite ausgeliefert — kein `Location`-Redirect, dem ein Bot einfach nicht folgen könnte. Die Einstiegsseite wird deterministisch aus verbotenem Pfad + IP abgeleitet, Antworten bleiben also cachebar.

Setup (Apache): `bot-monitor.php` neben die `.htaccess` legen und die Rewrite-Regeln aus [`.htaccess.example`](.htaccess.example) übernehmen — sie leiten Honeypot-Treffer und KI-Crawler-Requests auf sensible Pfade in den Monitor. Die User-Agent-Liste im Beispiel deckt die gängigen KI-Crawler und Scraper-Bibliotheken ab; bei Bedarf erweitern.

---

## Content-Generator

Statischer Ködertext hat eine Schwäche: Crawler deduplizieren. Wenn jede Labyrinth-Seite nur eine Permutation derselben zwölf Absätze ist, dampft die Dedup-Schicht eines Scrapers das ganze Labyrinth auf den Tokenwert von ~12 Absätzen ein — Falle entschärft.

`generator.php` löst das. Er baut grammatikalisch korrekte deutsche Sätze aus unabhängigen Wortpools (Subjekte × Prädikate × Adverbien × Bedingungen × Satz-Templates) — das ergibt **Milliarden unterschiedlicher Absätze**. Jede Seite mischt ~80 % generierten Text mit gelegentlichen handgeschriebenen Absätzen und Aufzählungslisten, dazu variierte Artikeltitel und deterministische Publikationsdaten.

Alles läuft über einen einzigen `mt_srand()`-Seed aus der Seiten-ID:

- gleiche Seiten-ID → byte-identische Seite, jedes Mal (cache-freundlich, keine Datenbank)
- andere Seiten-ID → wirklich anderer Text (dedup-sicher, maximaler Token-Burn)

**Anpassen / Übersetzen:** die Wortpools am Anfang von `generator.php` bearbeiten (Subjekte, Prädikate, Adverbien, Bedingungen, Einleitungen, Listenpunkte) sowie die Pools in `config.php` (Topics, Autoren, Zwischenüberschriften, Monatsnamen). Die Prädikat-Struktur `[Verb, Objekt]` beibehalten — sie sorgt dafür, dass Haupt- und Nebensatzstellung grammatisch bleiben.

---

## WordPress Integration

PHP AI Labyrinth lässt sich in WordPress ohne Plugin einbinden.

### Option A: Eigene PHP-Dateien neben WordPress (empfohlen)

WordPress läuft typischerweise im Webroot. Lege die drei Dateien einfach in einen Unterordner:

```
/var/www/html/
├── wp-config.php         ← WordPress
├── wp-content/
├── index.php
└── research/
    ├── articles.php      ← labyrinth.php, umbenannt (eigenständig, kein WP-Bootstrap)
    ├── generator.php
    └── config.php
```

WordPress wird dabei **nicht** geladen — die Dateien laufen vollkommen unabhängig. Kein Plugin, kein Hook nötig.

In `config.php` setzen:
```php
define('LABYRINTH_BASE_PATH', '/research/articles.php');
```

Den Einstiegslink per `wp_footer`-Hook einfügen (übersteht Theme-Updates — anders als direktes Editieren der `footer.php`):

```php
// functions.php des Child-Themes
add_action('wp_footer', function () {
    echo '<a href="/research/articles.php" style="display:none" tabindex="-1" aria-hidden="true">.</a>';
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
| **Caching-Plugins / CDN** | Gecachte Seiten liefern immer dieselbe Seite — Bots laufen im Kreis der selben 1–2 Seiten | Trap-Pfad vom Cache ausschließen (z. B. W3 Total Cache: „Never cache pages: `/research/.*`") |
| **`robots.txt` Plugins** | Plugins wie Yoast SEO können automatisch alle Nicht-WP-Pfade disallowed eintragen | Labyrinth-Pfad in den Plugin-Einstellungen aus dem `robots.txt`-Block entfernen |
| **WAF / Cloudflare Firewall** | Eigene WAF-Regeln können den Trap-Traffic blocken, bevor er PHP erreicht | Trap-Pfad in WAF-Bypass-Regeln aufnehmen (`uri contains /research/ → skip`) |
| **Rate Limiting** | Dein eigenes Rate Limiting blockt aggressive Bots, bevor sie tief ins Labyrinth laufen | Trap-Pfad vom externen Rate Limiting ausnehmen — Bots sollen viele Requests machen (der eingebaute Limiter deckelt echten DoS trotzdem) |
| **PHP `open_basedir`** | Restriktive `open_basedir`-Einstellungen können `require_once` für `config.php` blockieren | Alle drei Dateien im selben Verzeichnis ablegen oder `open_basedir` anpassen |
| **Shared Hosting** | Kein Zugriff auf `error_log()` oder Logs nicht sichtbar | `LABYRINTH_LOG_VISITS` auf `false` setzen; alternativ in eine eigene Logdatei schreiben |

---

## Konfigurationsreferenz

Alle Einstellungen in `config.php`:

| Konstante | Standard | Beschreibung |
|---|---|---|
| `LABYRINTH_MAX_DEPTH` | `100` | Maximale Tiefe vor stummem Reset |
| `LABYRINTH_LINKS_MIN` / `_MAX` | `6` / `12` | Ausgehende Links pro Seite |
| `LABYRINTH_PARAGRAPHS_MIN` / `_MAX` | `4` / `8` | Absätze pro Seite |
| `LABYRINTH_SITE_NAME` | `'Fachredaktion'` | Wird im Footer angezeigt |
| `LABYRINTH_BASE_PATH` | `'/research/articles.php'` | Selbstreferenzierender URL-Pfad |
| `LABYRINTH_LOG_VISITS` | `true` | Bot-Besuche via `error_log()` protokollieren |
| `LABYRINTH_DELAY_MIN_MS` / `_MAX_MS` | `0` / `0` | Tarpit-Delay pro Seite in ms (siehe unten) |
| `LABYRINTH_RATE_LIMIT_ENABLED` | `true` | Rate-Limiting pro IP an/aus |
| `LABYRINTH_RATE_LIMIT_MAX` | `60` | Max. Requests pro IP pro Fenster |
| `LABYRINTH_RATE_LIMIT_WINDOW` | `60` | Rate-Limit-Fenster in Sekunden |
| `LABYRINTH_PAGE_ID_MAX_LENGTH` | `64` | Max. Länge des `?p=`-Parameters |
| `LABYRINTH_LOG_MAX_PER_WINDOW` | `5` | Max. Logzeilen pro IP pro Minute |

Die Arrays für Topics, Absätze, Autoren, Zwischenüberschriften und Monatsnamen liegen in derselben Datei; die Wortpools des Satzgenerators in `generator.php`. Alles beliebig erweiterbar.

### Tarpit-Delay

Mit `LABYRINTH_DELAY_MIN_MS` / `LABYRINTH_DELAY_MAX_MS` (z. B. `250` / `1500`) schläft jede Labyrinth-Seite vor der Antwort eine deterministische, seitenabhängige Zeitspanne. Das kostet den Crawler Echtzeit und dich fast nichts — **aber** jeder schlafende Request belegt einen PHP-FPM-Worker. Nur aktivieren, wenn der Worker-Pool Luft hat; der eingebaute Rate-Limiter bleibt die Absicherung (Werte sind als Sicherheitsnetz hart auf 5000 ms gedeckelt). Standard: aus.

---

## Monitoring

Wenn `LABYRINTH_LOG_VISITS` aktiviert ist, wird jeder Besuch ins PHP-Error-Log geschrieben:

```
AI Labyrinth: IP=203.0.113.42 UA=SomeScraper/1.0 page=3b4c1d2e depth=7
BOT_MONITOR: IP=203.0.113.42 UA=GPTBot/1.1 path=/trap/
```

`AI Labyrinth:`-Zeilen sind normale Labyrinth-Seitenaufrufe; `BOT_MONITOR:`-Zeilen sind nachgewiesene robots.txt-Verstöße, die das Gateway abgefangen hat. Mit beliebigem Log-Aggregator (grep, GoAccess, Loki, …) auswerten.

### Beispiel: Trap-Hits pro IP zählen (bash)

```bash
grep 'AI Labyrinth' /var/log/php/error.log | awk -F'IP=' '{print $2}' | cut -d' ' -f1 | sort | uniq -c | sort -rn | head
```

`BOT_MONITOR`-IPs sind ideales Futter für `fail2ban` oder Firewall-Blocklisten — jeder einzelne Treffer ist ein nachweisbarer Verstoß.

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
$hashed_ip = hash('sha256', $client_ip . 'your-salt-here');
error_log("AI Labyrinth: IP={$hashed_ip} UA=... page={$page_id} depth={$depth}");
```

> 💡 **Datenschutzerklärung**: Wenn du IP-Adressen loggst, ergänze deine Datenschutzerklärung um einen Hinweis wie: *„Bei Zugriffen auf Sicherheitsmechanismen unserer Website können IP-Adressen temporär (max. 7 Tage) zur Abwehr automatisierter Angriffe gespeichert werden."*

---

## Apache Integration

Siehe [`.htaccess.example`](.htaccess.example) für die vollständige Datei:

- Clean-URL-Rewrite: `/research/articles` → `articles.php`
- Bot-Monitor-Regeln: Honeypot-Pfad + KI-Crawler-User-Agents auf sensiblen Pfaden → `bot-monitor.php`
- Direkten Zugriff auf `config.php` blockieren
- Security- und Caching-Header

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

# config.php / generator.php vor direktem Zugriff schützen
location ~ /(config|generator)\.php$ {
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

    # Clean URL: /research/articles → articles.php
    rewrite /research/articles* /research/articles.php?{query}

    # config.php / generator.php sperren
    respond /research/config.php 403
    respond /research/generator.php 403
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
    image: php:8.2-apache                  # oder nginx + php-fpm
    volumes:
      - ./research:/var/www/html/research  # articles.php + generator.php + config.php
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
  # Middleware: /research/articles → /research/articles.php
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.regex=^/research/articles$"
  - "traefik.http.middlewares.labyrinth-rewrite.replacepathregex.replacement=/research/articles.php"
  - "traefik.http.routers.labyrinth.middlewares=labyrinth-rewrite"
```

> `config.php` / `generator.php` besser auf Webserver-Ebene sperren (`.htaccess` / Nginx `deny all`) — das ist zuverlässiger als eine Traefik-Middleware.

---

## Tests

Eine Smoke-Test-Suite prüft die Kernversprechen (deterministische Seiten, Varianz pro Seite, noindex-Header, direktes Gateway-Rendering, lokalisierte Datumsangaben):

```bash
tests/smoke.sh
```

Das Skript lintet alle PHP-Dateien, startet PHPs eingebauten Webserver und testet die Endpunkte — dasselbe Skript läuft in der CI (GitHub Actions, PHP 7.4 und 8.4).

---

## Sicherheitshinweise

- `config.php` und `generator.php` enthalten keine Secrets — sicher zu committen; direkten Web-Zugriff trotzdem sperren (gute Hygiene)
- Für zusätzliche Sicherheit `config.php` außerhalb des Web-Roots ablegen und den `require_once`-Pfad in `labyrinth.php` anpassen
- Kein Datenbankzugriff — kein SQL-Injection-Risiko
- Alle Ausgaben werden durch `htmlspecialchars()` gesichert — kein XSS-Risiko durch URL-Parameter
- Geloggte Werte (User-Agent, Pfade) werden gegen Log-Injection bereinigt; das Log-Volumen ist pro IP begrenzt

---

## Weitere Ideen

- **Mehrsprachig**: `config.php` + Wortpools pro Locale duplizieren, anhand `Accept-Language`-Header ausliefern
- **Themenpassend**: Topics und Wortpools an deine echten Seiteninhalte anpassen — je glaubwürdiger, desto effektiver
- **Analytics-Event**: Bei jedem Trap-Hit einen Server-seitigen Event feuern (Umami, Plausible, Matomo)
- **fail2ban-Integration**: `BOT_MONITOR:`-Logzeilen in ein fail2ban-Jail füttern — jeder Treffer ist ein nachgewiesener robots.txt-Verstoß

---

## Lizenz

MIT — siehe [LICENSE](LICENSE)

---

## Credits

Konzept inspiriert von [Cloudflare AI Labyrinth](https://blog.cloudflare.com/ai-labyrinth/) (2025).  
PHP-Implementierung von [Philipp Gabriel](https://github.com/PhilGabriel).
