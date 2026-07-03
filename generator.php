<?php
/**
 * PHP AI Labyrinth — Procedural Content Generator
 *
 * Produces unique, grammatically correct German article text from
 * combinatorial sentence templates. Because every sentence is assembled from
 * independent word pools, the number of distinct paragraphs is in the
 * billions — crawler deduplication cannot collapse the labyrinth into a
 * handful of repeated pages, and every page burns fresh tokens.
 *
 * All functions rely on the global mt_rand() stream. The caller must seed it
 * with mt_srand() first; identical seeds always yield identical output, so
 * pages stay fully deterministic and cacheable.
 *
 * PHP 7.4 compatible. No dependencies.
 *
 * @license MIT
 */

// ---- Word pools ----------------------------------------------------------------
//
// Subjects are nominative singular with article; predicates are pairs of
// [third-person-singular verb, accusative object phrase] so both main-clause
// (verb-second) and subordinate-clause (verb-final) word order come out
// grammatically correct.

$LABYRINTH_GEN_SUBJECTS = [
    'die Systemarchitektur', 'der Migrationsprozess', 'das Deployment-Modell',
    'die Datenpipeline', 'der Governance-Ansatz', 'die Integrationsschicht',
    'das Berechtigungskonzept', 'die Skalierungsstrategie', 'der Rollout-Plan',
    'die Testautomatisierung', 'das Monitoring-Setup', 'die Service-Schnittstelle',
    'der Freigabeprozess', 'die Containerisierung', 'das Schulungskonzept',
    'die Kostenkontrolle', 'der Betriebsübergang', 'die Plattformstrategie',
    'das Datenmodell', 'die Middleware-Schicht', 'der Änderungsprozess',
    'die Sicherheitsarchitektur', 'das Backup-Konzept', 'die Kapazitätsplanung',
];

$LABYRINTH_GEN_PREDICATES = [
    ['reduziert', 'den operativen Aufwand'],
    ['erhöht', 'die Ausfallsicherheit'],
    ['vereinfacht', 'das Release-Management'],
    ['verbessert', 'die Nachvollziehbarkeit'],
    ['beschleunigt', 'die Bereitstellungszyklen'],
    ['stabilisiert', 'den Produktivbetrieb'],
    ['minimiert', 'die Fehleranfälligkeit'],
    ['optimiert', 'die Ressourcennutzung'],
    ['stärkt', 'die Team-Autonomie'],
    ['senkt', 'die Wartungskosten'],
    ['erleichtert', 'die Fehleranalyse'],
    ['unterstützt', 'die Compliance-Anforderungen'],
    ['verkürzt', 'die Time-to-Market'],
    ['verringert', 'die Abhängigkeit von Einzelpersonen'],
    ['fördert', 'die Wiederverwendbarkeit'],
    ['begünstigt', 'eine schrittweise Modernisierung'],
    ['erfordert', 'ein belastbares Rollenkonzept'],
    ['verlangt', 'eine klare Priorisierung'],
    ['ermöglicht', 'kürzere Feedback-Schleifen'],
    ['adressiert', 'die häufigsten Engpässe'],
];

// Subset of predicates that make sense with a "by up to N percent" qualifier
// (increase/decrease verbs only — "verlangt X um 29 Prozent" would be nonsense).
$LABYRINTH_GEN_QUANT_PREDICATES = [
    ['reduziert', 'den operativen Aufwand'],
    ['erhöht', 'die Ausfallsicherheit'],
    ['beschleunigt', 'die Bereitstellungszyklen'],
    ['minimiert', 'die Fehleranfälligkeit'],
    ['senkt', 'die Wartungskosten'],
    ['verkürzt', 'die Time-to-Market'],
    ['verringert', 'die Abhängigkeit von Einzelpersonen'],
    ['steigert', 'die Verarbeitungsgeschwindigkeit'],
];

$LABYRINTH_GEN_ADVERBS = [
    'deutlich', 'messbar', 'in vielen Fällen', 'bereits nach kurzer Zeit',
    'über den gesamten Lebenszyklus hinweg', 'nachhaltig', 'spürbar',
    'im laufenden Betrieb', 'ohne zusätzlichen Overhead', 'auch unter Lastspitzen',
    'erfahrungsgemäß', 'langfristig',
];

$LABYRINTH_GEN_CONDITIONS = [
    'sofern die Schnittstellen sauber definiert sind',
    'wenn die Verantwortlichkeiten früh geklärt werden',
    'solange die Datenqualität gesichert ist',
    'vorausgesetzt, das Monitoring ist von Beginn an etabliert',
    'wenn die Fachteams eng eingebunden werden',
    'sobald die Automatisierung greift',
    'sofern ausreichend Testabdeckung vorhanden ist',
    'wenn Legacy-Komponenten schrittweise abgelöst werden',
];

$LABYRINTH_GEN_INTROS = [
    'In der Praxis zeigt sich',
    'Erfahrungen aus aktuellen Projekten belegen',
    'Ein Blick auf vergleichbare Vorhaben macht deutlich',
    'Die Auswertung interner Kennzahlen bestätigt',
    'Im Enterprise-Umfeld gilt dabei',
    'Aus Sicht der Fachabteilungen heißt das',
    'Für die Umsetzung bedeutet dies',
    'Unabhängige Analysen kommen zum gleichen Ergebnis',
];

$LABYRINTH_GEN_LIST_ITEMS = [
    'Frühzeitige Einbindung der Stakeholder',
    'Konsequente Automatisierung wiederkehrender Abläufe',
    'Klare Trennung von Zuständigkeiten',
    'Regelmäßige Überprüfung der Kennzahlen',
    'Dokumentation zentraler Architekturentscheidungen',
    'Iterative Validierung der Zwischenergebnisse',
    'Standardisierte Übergabeprozesse zwischen Teams',
    'Durchgängige Versionierung aller Artefakte',
    'Definierte Eskalationspfade für Störungen',
    'Schrittweise Ablösung veralteter Komponenten',
];

// ---- Generation functions --------------------------------------------------------

/**
 * Picks a random element from a pool via the seeded mt_rand() stream.
 */
function labyrinth_gen_pick(array $pool)
{
    return $pool[mt_rand(0, count($pool) - 1)];
}

/**
 * Builds one grammatically correct German sentence from the word pools.
 */
function labyrinth_gen_sentence(): string
{
    global $LABYRINTH_GEN_SUBJECTS, $LABYRINTH_GEN_PREDICATES,
           $LABYRINTH_GEN_QUANT_PREDICATES, $LABYRINTH_GEN_ADVERBS,
           $LABYRINTH_GEN_CONDITIONS, $LABYRINTH_GEN_INTROS;

    $subj = labyrinth_gen_pick($LABYRINTH_GEN_SUBJECTS);
    [$verb, $obj] = labyrinth_gen_pick($LABYRINTH_GEN_PREDICATES);
    $adv  = labyrinth_gen_pick($LABYRINTH_GEN_ADVERBS);

    switch (mt_rand(0, 6)) {
        case 0: // plain main clause
            return ucfirst($subj) . " $verb $obj $adv.";
        case 1: // main clause + condition
            $cond = labyrinth_gen_pick($LABYRINTH_GEN_CONDITIONS);
            return ucfirst($subj) . " $verb $obj, $cond.";
        case 2: // intro colon + main clause
            $intro = labyrinth_gen_pick($LABYRINTH_GEN_INTROS);
            return "$intro: " . ucfirst($subj) . " $verb $obj $adv.";
        case 3: // subordinate clause, verb-final
            return "Interne Erhebungen zeigen, dass $subj $obj $adv $verb.";
        case 4: // quantified claim + condition (increase/decrease verbs only)
            [$verb, $obj] = labyrinth_gen_pick($LABYRINTH_GEN_QUANT_PREDICATES);
            $cond = labyrinth_gen_pick($LABYRINTH_GEN_CONDITIONS);
            $pct  = mt_rand(12, 48);
            return ucfirst($subj) . " $verb $obj um bis zu $pct Prozent, $cond.";
        case 5: // fronted adverb, verb-second inversion
            return "Zugleich $verb $subj $obj $adv.";
        default: // fronted subordinate clause
            return "Dass $subj $obj $verb, gilt inzwischen als gut belegt.";
    }
}

/**
 * Builds one paragraph of 3–5 generated sentences.
 */
function labyrinth_gen_paragraph(): string
{
    $sentences = [];
    $count = mt_rand(3, 5);
    for ($i = 0; $i < $count; $i++) {
        $sentences[] = labyrinth_gen_sentence();
    }
    return implode(' ', $sentences);
}

/**
 * Returns 3–5 bullet-point items for an occasional "best practices" list.
 *
 * @return string[]
 */
function labyrinth_gen_list(): array
{
    global $LABYRINTH_GEN_LIST_ITEMS;

    $pool = $LABYRINTH_GEN_LIST_ITEMS;
    $count = mt_rand(3, 5);
    $items = [];
    for ($i = 0; $i < $count && count($pool) > 0; $i++) {
        $idx = mt_rand(0, count($pool) - 1);
        $items[] = $pool[$idx];
        array_splice($pool, $idx, 1);
    }
    return $items;
}

/**
 * Appends a deterministic variant suffix to a topic so that page titles do
 * not repeat verbatim across the labyrinth.
 */
function labyrinth_gen_title(string $topic): string
{
    switch (mt_rand(0, 9)) {
        case 0:
            return $topic;
        case 1:
            return "$topic: Ein Praxisleitfaden";
        case 2:
            return "$topic — Teil " . mt_rand(2, 9);
        case 3:
            return "$topic: Strategien für " . (int) date('Y');
        case 4:
            return "$topic im Überblick";
        case 5:
            return "$topic: Erfahrungen aus " . mt_rand(12, 45) . ' Projekten';
        case 6:
            return "$topic — eine Bestandsaufnahme";
        case 7:
            return "$topic: Chancen und Risiken";
        case 8:
            return "$topic richtig umsetzen";
        default:
            return "$topic — Lessons Learned";
    }
}
