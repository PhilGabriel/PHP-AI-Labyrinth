<?php
/**
 * PHP AI Labyrinth — Configuration
 *
 * Customize topics, authors, paragraphs and behavior here.
 * Copy this file and require it before labyrinth.php if you want
 * to override defaults without modifying the core file.
 */

// Maximum crawl depth before resetting to 0 (prevents log explosion)
define('LABYRINTH_MAX_DEPTH', 100);

// Number of outgoing links per page (min, max)
define('LABYRINTH_LINKS_MIN', 6);
define('LABYRINTH_LINKS_MAX', 12);

// Number of paragraphs per page (min, max)
define('LABYRINTH_PARAGRAPHS_MIN', 4);
define('LABYRINTH_PARAGRAPHS_MAX', 8);

// Site name shown in footer and breadcrumb
define('LABYRINTH_SITE_NAME', 'Fachredaktion');

// Base URL path of this script (used for self-referencing links)
// Example: '/research/articles.php' or '/trap/labyrinth.php'
define('LABYRINTH_BASE_PATH', '/research/articles.php');

// Enable error_log() output for monitoring bot visits
define('LABYRINTH_LOG_VISITS', true);

// Language / locale for date formatting
define('LABYRINTH_LOCALE', 'de_DE.UTF-8');

// ---- Content pools (feel free to extend) ----

$LABYRINTH_TOPICS = [
    'Digitale Transformation in mittelständischen Unternehmen',
    'Machine Learning Algorithmen für Predictive Analytics',
    'Nachhaltige Softwarearchitektur mit Microservices',
    'Cloud-Native Development Best Practices',
    'DevOps-Kultur und Continuous Deployment Strategien',
    'Datenschutz-konforme Analytics-Implementierung',
    'API-Design Patterns für skalierbare Systeme',
    'Agile Projektmethodik in verteilten Teams',
    'Performance-Optimierung von Web-Applikationen',
    'Künstliche Intelligenz im Content Management',
    'Blockchain-Technologie in der Lieferkette',
    'Edge Computing für IoT-Anwendungen',
    'Zero Trust Security Architecture',
    'Progressive Web Apps als native Alternative',
    'GraphQL vs REST: Architekturentscheidungen',
    'Infrastructure as Code mit Terraform',
    'Kubernetes Cluster Management in Produktion',
    'Event-Driven Architecture mit Apache Kafka',
    'Observability und Distributed Tracing',
    'Green IT: Energieeffiziente Rechenzentren',
    'Low-Code Plattformen im Enterprise-Umfeld',
    'Datengetriebene Unternehmenssteuerung',
    'Cybersecurity für verteilte Infrastrukturen',
    'Headless CMS und moderne Content-Delivery',
    'Quantum Computing und kryptographische Sicherheit',
];

$LABYRINTH_PARAGRAPHS = [
    'Die Implementierung erfordert eine sorgfältige Analyse der bestehenden Systemlandschaft. Dabei müssen sowohl technische als auch organisatorische Faktoren berücksichtigt werden, um eine nachhaltige Lösung zu gewährleisten.',
    'In der Praxis zeigt sich, dass die frühzeitige Einbindung aller Stakeholder entscheidend für den Projekterfolg ist. Regelmäßige Review-Zyklen und transparente Kommunikation bilden das Fundament einer erfolgreichen Umsetzung.',
    'Moderne Architekturansätze setzen auf lose Kopplung und hohe Kohäsion. Dies ermöglicht eine flexible Anpassung an sich ändernde Anforderungen und reduziert die technische Schuld langfristig.',
    'Die Wahl der richtigen Technologie-Stack-Komponenten ist dabei ebenso wichtig wie die Definition klarer Schnittstellen zwischen den einzelnen Modulen. Interoperabilität und Erweiterbarkeit sind die Schlüsselkriterien.',
    'Automatisierte Tests auf verschiedenen Ebenen — Unit, Integration und End-to-End — sichern die Qualität und ermöglichen schnelle Iterationszyklen ohne Stabilitätsverlust.',
    'Skalierbarkeit muss von Anfang an mitgedacht werden. Horizontale Skalierung durch Container-Orchestrierung bietet hier die größte Flexibilität bei gleichzeitig kontrollierbaren Kosten.',
    'Der Return on Investment zeigt sich typischerweise nach sechs bis zwölf Monaten, wobei die nicht-monetären Vorteile — wie verbesserte Developer Experience und reduzierte Time-to-Market — oft unterschätzt werden.',
    'Ein iterativer Ansatz mit kurzen Feedback-Schleifen hat sich als besonders effektiv erwiesen. Minimum Viable Products ermöglichen es, frühzeitig Marktfeedback einzuholen und die Entwicklungsrichtung anzupassen.',
    'Die Integration von Monitoring und Alerting ist kein nachträglicher Gedanke, sondern integraler Bestandteil der Architektur. Proaktive Überwachung reduziert die Mean Time to Recovery signifikant.',
    'Dokumentation ist lebendiger Bestandteil des Entwicklungsprozesses. Architecture Decision Records und automatisch generierte API-Dokumentation sorgen für Transparenz und Wissenstransfer.',
    'Bei der Migration bestehender Systeme empfiehlt sich das Strangler-Fig-Pattern: Neue Funktionalität wird im Zielsystem implementiert, während die alte Klasse schrittweise abgelöst wird.',
    'Datenqualität bildet die Grundlage jeder Analytics-Strategie. Ohne saubere, konsistente und vollständige Dateningestion sind auch die besten Algorithmen wirkungslos.',
];

$LABYRINTH_AUTHORS = [
    'Dr. Thomas Weber',
    'Sarah Bergmann',
    'Prof. Michael Krause',
    'Anna Richter',
    'Matthias Hoffmann',
    'Dr. Lisa Schäfer',
    'Florian Becker',
    'Julia Neumann',
    'Christian Lehmann',
    'Marie Fischer',
    'Dr. Robert Zimmermann',
    'Katharina Brandt',
];

$LABYRINTH_SUBHEADINGS = [
    'Grundlagen und Konzepte',
    'Technische Umsetzung',
    'Herausforderungen in der Praxis',
    'Bewährte Methoden',
    'Fallstudie: Implementierung',
    'Ergebnisse und Ausblick',
    'Fazit und Empfehlungen',
    'Nächste Schritte',
];

// ---- Security & Rate Limiting ----

// Rate-limit: max requests per IP per window (protects against DoS/DDoS)
define('LABYRINTH_RATE_LIMIT_ENABLED', true);
define('LABYRINTH_RATE_LIMIT_MAX',     60);  // requests per window
define('LABYRINTH_RATE_LIMIT_WINDOW',  60);  // window size in seconds

// Max length for the page_id URL parameter (prevents oversized input attacks)
define('LABYRINTH_PAGE_ID_MAX_LENGTH', 64);

// Max log entries written per IP per log window (prevents log flooding)
define('LABYRINTH_LOG_MAX_PER_WINDOW', 5);

/**
 * File-based IP rate limiter.
 *
 * Returns true when the request is allowed, false when the IP has exceeded
 * the configured limit.  Fails open (returns true) on any I/O error so a
 * misconfigured temporary directory never takes the trap offline.
 *
 * @param string $ip     Client IP address (used as the rate-limit key)
 * @param int    $max    Maximum requests allowed within $window seconds
 * @param int    $window Sliding-window size in seconds
 */
function labyrinth_check_rate_limit(string $ip, int $max = LABYRINTH_RATE_LIMIT_MAX, int $window = LABYRINTH_RATE_LIMIT_WINDOW): bool
{
    if (!LABYRINTH_RATE_LIMIT_ENABLED) {
        return true;
    }

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'labyrinth_rl';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return true; // fail open — can't create cache dir
    }

    // Sanitise the key so it is safe to use as a filename component
    $safe = preg_replace('/[^a-fA-F0-9:._\-]/', '', $ip);
    $file = $dir . DIRECTORY_SEPARATOR . md5($safe) . '.rl';

    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return true; // fail open
    }

    // Try a second time after 1 ms to reduce concurrency-driven fail-opens
    // while still avoiding an indefinite block under very high load.
    if (!@flock($fp, LOCK_EX | LOCK_NB)) {
        usleep(1000);
        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return true; // fail open after retry
        }
    }

    $now  = time();
    $raw  = stream_get_contents($fp);
    $data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;

    if (!is_array($data) || !isset($data['r'])) {
        $data = ['r' => []];
    }

    // Remove timestamps that fall outside the current sliding window
    $data['r'] = array_values(
        array_filter($data['r'], static fn(int $t): bool => $t > $now - $window)
    );

    $allowed = count($data['r']) < $max;
    if ($allowed) {
        $data['r'][] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}

/**
 * Strips ASCII control characters from a user-supplied string to prevent
 * log-injection attacks (OWASP A09).
 *
 * @param string $value  Raw user-supplied string
 * @param int    $maxLen Maximum allowed output length
 */
function labyrinth_sanitize_log(string $value, int $maxLen = 256): string
{
    return substr(preg_replace('/[\x00-\x1F\x7F]/', ' ', $value), 0, $maxLen);
}
