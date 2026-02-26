# Bavarian Rank Engine

**Version 1.2.2** — KI-generierte Meta-Beschreibungen, GEO-Strukturdaten, llms.txt und Crawler-Verwaltung für WordPress.

Entwickelt von [noschmarrn](https://github.com/noschmarrn) · [Plugin-Website](https://bavarianrankengine.com/de/)

---

## Features

### KI-Meta-Generator
Generiert beim Veröffentlichen eines Beitrags automatisch eine SEO-optimierte Meta-Beschreibung — mit dem KI-Anbieter deiner Wahl. Der Prompt ist frei konfigurierbar und unterstützt die Platzhalter `{title}`, `{content}`, `{excerpt}` und `{language}`. Die Sprache wird automatisch aus Polylang, WPML oder dem WordPress-Locale ermittelt. Wenn kein API-Key konfiguriert ist oder der KI-Aufruf fehlschlägt, wird ein sauberer 150–160-Zeichen-Auszug aus dem Beitragsinhalt als Fallback verwendet.

Meta-Beschreibungen werden sowohl in BREs eigenem `_bre_meta_description` Post-Meta-Key gespeichert als auch in das native Feld des aktiven SEO-Plugins geschrieben (Rank Math, Yoast SEO, AIOSEO, SEOPress). Bereits vorhandene Beschreibungen aus einem dieser Plugins werden erkannt und übersprungen — manuell geschriebene Texte werden nie überschrieben.

### Massen-Generator
Verarbeitet alle veröffentlichten Beiträge ohne Meta-Beschreibung in einem Batch-Prozess. Läuft im Browser über wiederholte AJAX-Aufrufe mit konfigurierbarer Batch-Größe (1–20 Beiträge pro Anfrage) und einem festen 6-Sekunden-Delay zur Rate-Limitierung. Ein Transient-basierter Lock (`bre_bulk_running`, TTL 15 Minuten) verhindert parallele Durchläufe. Jeder Beitrag wird bis zu dreimal versucht, bevor er als fehlgeschlagen markiert wird. Fortschritt, Einzelergebnisse und eine laufende Kostenschätzung werden live im Admin angezeigt.

### Schema.org (GEO)
Fügt JSON-LD-Strukturdaten und Meta-Tags in `wp_head` ein. Einzeln aktivierbare Typen:

| Typ | Beschreibung |
|---|---|
| `organization` | Website-Name, URL, Logo und optionale `sameAs`-Social-Links |
| `article_about` | Article-Schema mit Überschrift, Datum, Beschreibung und Herausgeber |
| `author` | Person-Schema mit Autorenname, URL und optionalem Twitter-`sameAs` |
| `speakable` | SpeakableSpecification für `h1` und ersten Absatz-Selektor |
| `breadcrumb` | BreadcrumbList (wird übersprungen, wenn Rank Math oder Yoast aktiv sind) |
| `ai_meta_tags` | `<meta name="robots">` und `<meta name="googlebot">` mit `max-snippet:-1, max-image-preview:large` |

Die eigenständige `<meta name="description">`-Ausgabe wird unterdrückt, wenn Rank Math, Yoast oder AIOSEO aktiv sind.

### llms.txt
Stellt einen maschinenlesbaren Index aller veröffentlichten Inhalte unter `/llms.txt` (sowie paginiert `/llms-2.txt`, `/llms-3.txt`, ...) bereit — gemäß der sich etablierenden llms.txt-Konvention für KI-Trainingssysteme. Funktionsumfang:

- Konfigurierbarer Titel, Beschreibungsblöcke (vor, nach, Footer) und ein eigener Featured-Links-Bereich
- Auswählbare Post-Types
- Konfigurierbares Limit für Links pro Seite (mindestens 50, Standard 500)
- Transient-basiertes Caching mit manuellem Cache-Leer-Button im Admin
- HTTP-Caching-Header: `ETag`, `Last-Modified`, `Cache-Control: public, max-age=3600`
- HTTP 304 Not Modified bei übereinstimmendem ETag
- Admin-Hinweis bei aktiven Rank Math (BRE hat über `parse_request` bei Priorität 1 Vorrang)

### robots.txt-Verwaltung
Hängt `User-agent` / `Disallow: /` Blöcke an das virtuelle `robots.txt` von WordPress via `robots_txt`-Filter. Unterstützt 13 bekannte KI- und Daten-Crawler:

GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, Applebot-Extended, Bytespider, DataForSeoBot, ImagesiftBot, omgili, Diffbot, FacebookBot, Amazonbot.

Jeder Bot kann im Admin einzeln aktiviert oder deaktiviert werden.

### Crawler-Log
Protokolliert Besuche bekannter KI-Bots in einer eigenen Datenbanktabelle (`{prefix}bre_crawler_log`). Gespeichert werden Bot-Name, SHA-256-Hash der Besucher-IP (datenschutzkonform), angeforderte URL (auf 512 Zeichen begrenzt) und Zeitstempel. Einträge älter als 90 Tage werden automatisch per wöchentlichem WP-Cron-Job bereinigt. Das Dashboard zeigt eine 30-Tage-Übersicht je Bot.

### Meta-Editor-Box
Fügt jedem konfigurierten Post-Type eine „Meta Description (BRE)"-Meta-Box im Beitragseditor hinzu. Zeigt die aktuelle Beschreibung (mit Quellen-Badge: KI / Fallback / Manuell / Noch nicht generiert), einen Zeichenzähler mit Zielwert 160 und einen „Mit KI neu generieren"-Button, der die API direkt im Editor aufruft.

### SEO-Analyse-Widget
Eine Sidebar-Meta-Box im Beitragseditor mit Live-Inhaltsstatistiken: Titel-Zeichenzahl (Ziel: 60), Wortanzahl, geschätzte Lesezeit, Überschriftenstruktur und Anzahl interner/externer Links. Zeigt auch Inline-Warnungen (z. B. fehlendes H2, keine internen Links). Die Statistiken aktualisieren sich in Echtzeit beim Schreiben.

### Link-Analyse (Dashboard)
Ein per AJAX geladenes Dashboard-Panel, das Beiträge ohne interne Links, Beiträge mit ungewöhnlich vielen externen Links (konfigurierbarer Schwellenwert) und die Top-Pillar-Pages nach eingehenden internen Links identifiziert. Ergebnisse werden eine Stunde gecacht.

### Multi-Provider-KI-Backend
Vier Anbieter sind direkt eingebunden:

| Anbieter | Klasse |
|---|---|
| OpenAI | `OpenAIProvider` |
| Anthropic (Claude) | `AnthropicProvider` |
| Google Gemini | `GeminiProvider` |
| xAI Grok | `GrokProvider` |

Aktiver Anbieter und Modell werden pro Website ausgewählt. API-Keys können auch über `wp-config.php`-Konstanten gesetzt werden (siehe API-Key-Sicherheit).

---

## Voraussetzungen

- WordPress 6.0 oder neuer
- PHP 8.0 oder neuer
- Mindestens ein API-Key eines KI-Anbieters (optional — Fallback-Meta-Extraktion funktioniert auch ohne)

---

## Installation

1. Plugin-Ordner nach `wp-content/plugins/` hochladen.
2. Plugin unter **Plugins → Installierte Plugins** aktivieren.
3. Zu **Bavarian Rank → KI-Anbieter** navigieren.
4. Anbieter auswählen, API-Key eingeben, Modell wählen und **Verbindung testen** klicken.
5. Unter **Bavarian Rank → Meta-Generator** Post-Types, Token-Limit und Prompt konfigurieren.
6. Optional Schema.org-Typen unter **Bavarian Rank → Schema.org** aktivieren.
7. Für `/llms.txt` unter **Bavarian Rank → llms.txt** aktivieren und speichern.

Bei der ersten Aktivierung legt das Plugin die Tabelle `{prefix}bre_crawler_log` an und registriert die `llms.txt`-Rewrite-Rule (gefolgt von `flush_rewrite_rules()`).

---

## Admin-Menüstruktur

Das Plugin registriert ein Top-Level-Menü **Bavarian Rank** (Slug `bavarian-rank`) mit folgenden Unterseiten:

| Unterseite | Slug | Klasse | Zweck |
|---|---|---|---|
| Dashboard | `bavarian-rank` | `AdminMenu` | Übersicht: aktiver Anbieter, Meta-Coverage-Statistiken, Crawler-Log-Zusammenfassung, Link-Analyse, Token-/Kostenauswertung |
| KI-Anbieter | `bre-provider` | `ProviderPage` | Anbieter wählen, API-Key eingeben/testen, Modell wählen, Token-Kosten konfigurieren, KI ein-/ausschalten |
| Meta-Generator | `bre-meta` | `MetaPage` | Auto-Generierung ein-/ausschalten, Post-Types wählen, Token-Limit setzen, Prompt bearbeiten |
| Schema.org | `bre-schema` | `SchemaPage` | JSON-LD-Strukturdaten-Typen aktivieren und konfigurieren |
| llms.txt | `bre-llms` | `LlmsPage` | llms.txt aktivieren/konfigurieren, Post-Types, Max-Links, benutzerdefinierte Abschnitte |
| Massen-Generator | `bre-bulk` | `BulkPage` | Meta-Beschreibungen für alle Beiträge ohne Beschreibung batchweise generieren |
| robots.txt | `bre-robots` | `RobotsPage` | Auswahl, welche KI-Bots in der robots.txt gesperrt werden |
| Einstellungen | `bre-settings` | `SettingsPage` | Globale Plugin-Einstellungen |

Alle Seiten erfordern die Berechtigung `manage_options`.

---

## API-Key-Sicherheit (KeyVault)

API-Keys werden vor dem Schreiben in die WordPress-Optionstabelle verschleiert, mithilfe von `BavarianRankEngine\Helpers\KeyVault`.

**Funktionsweise:**

1. Ein 64-stelliger Hex-Salt wird aus den WordPress-Konstanten `AUTH_KEY` und `SECURE_AUTH_KEY` abgeleitet via `hash('sha256', AUTH_KEY . SECURE_AUTH_KEY)`.
2. Der Klartext-Key wird byteweise XOR-verschlüsselt mit dem Salt (Wrap-Around bei kürzerem Salt).
3. Das Ergebnis wird Base64-kodiert und mit einem `bre1:`-Präfix gespeichert.

Gespeichertes Format: `bre1:<base64(xor(klartext, salt))>`

Außer der PHP-Standardbibliothek ist keine OpenSSL-Extension erforderlich.

**Einschränkung:** XOR mit einem statisch abgeleiteten Key ist Verschleierung, keine kryptografische Verschlüsselung. Es verhindert, dass API-Keys im Klartext in Datenbank-Dumps erscheinen, schützt aber nicht gegen Angreifer mit Zugriff auf Datenbank und `wp-config.php`. Für mehr Sicherheit den API-Key direkt in der `wp-config.php` definieren:

```php
define( 'BRE_OPENAI_KEY',    'sk-...' );
define( 'BRE_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'BRE_GEMINI_KEY',    'AI...' );
define( 'BRE_GROK_KEY',      'xai-...' );
```

Wenn eine Konstante definiert und das Datenbankfeld leer ist, wird automatisch der Konstantenwert verwendet.

Im Admin werden Keys immer maskiert angezeigt: `••••••Ab3c9` (letzte 5 Zeichen sichtbar).

---

## Plugin erweitern

### Neuen KI-Anbieter hinzufügen

`includes/Providers/MeinAnbieter.php` erstellen, das `ProviderInterface` implementieren:

```php
<?php
namespace BavarianRankEngine\Providers;

class MeinAnbieter implements ProviderInterface {

    public function getId(): string {
        return 'meinanbieter';
    }

    public function getName(): string {
        return 'Mein Anbieter';
    }

    public function getModels(): array {
        return [
            'model-v1'      => 'Modell V1 (Smart)',
            'model-v1-mini' => 'Modell V1 Mini (Schnell)',
        ];
    }

    public function testConnection( string $api_key ): array {
        // Minimaler API-Aufruf zum Prüfen des Keys.
        // Rückgabe: ['success' => true, 'message' => 'Verbunden mit ...']
        // oder:     ['success' => false, 'message' => 'Fehler: ...']
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        // API-Endpoint aufrufen.
        // Rückgabe: generierter Text als String bei Erfolg.
        // \RuntimeException bei API- oder HTTP-Fehler werfen.
    }
}
```

Dann den Anbieter in `includes/Core.php` innerhalb von `register_hooks()` registrieren:

```php
$registry->register( new Providers\MeinAnbieter() );
```

Der neue Anbieter erscheint automatisch in allen Admin-Dropdowns.

### Neues Feature hinzufügen

1. `includes/Features/MeinFeature.php` mit einer öffentlichen `register()`-Methode erstellen, die WordPress-Hooks registriert.
2. `require_once BRE_DIR . 'includes/Features/MeinFeature.php';` in `Core::load_dependencies()` hinzufügen.
3. `( new Features\MeinFeature() )->register();` in `Core::register_hooks()` hinzufügen.

### Verfügbare Hooks

**`bre_prompt` (Filter)**

Wird in `MetaGenerator::buildPrompt()` nach allen Platzhalter-Ersetzungen aufgerufen. Damit lassen sich Keywords anhängen, die Sprach-Anweisung ändern oder dynamischer Kontext injizieren.

```php
add_filter( 'bre_prompt', function( string $prompt, \WP_Post $post ): string {
    $keyword = get_post_meta( $post->ID, 'focus_keyword', true );
    if ( $keyword ) {
        $prompt .= "\nFokus-Keyword: " . $keyword;
    }
    return $prompt;
}, 10, 2 );
```

**`bre_meta_saved` (Action)**

Wird am Ende von `MetaGenerator::saveMeta()` ausgelöst, nachdem die Beschreibung in alle relevanten Post-Meta-Keys geschrieben wurde.

```php
add_action( 'bre_meta_saved', function( int $post_id, string $description ): void {
    meine_sync_funktion( $post_id, $description );
}, 10, 2 );
```

---

## Option-Keys

| Option-Key | Inhalt |
|---|---|
| `bre_settings` | Anbieter-ID, verschlüsselte API-Keys, gewählte Modelle, Token-Kosten |
| `bre_meta_settings` | Auto-Generierung, Post-Types, Token-Modus/-Limit, Prompt |
| `bre_schema_settings` | Schema.org-Konfiguration (seit 1.2.1) |
| `bre_llms_settings` | llms.txt-Aktivierungsflag, Titel, Beschreibungsblöcke, Post-Types, Max-Links |
| `bre_robots_settings` | Array der gesperrten Bot-User-Agent-Strings |

Post-Level-Meta-Keys:

| Meta-Key | Inhalt |
|---|---|
| `_bre_meta_description` | Generierte oder manuell eingegebene Meta-Beschreibung |
| `_bre_meta_source` | Quelle: `ai`, `fallback` oder `manual` |
| `_bre_bulk_failed` | Letzte Fehlermeldung, wenn die Bulk-Generierung für diesen Beitrag fehlschlug |

---

## Entwicklung

```bash
# Dev-Dependencies installieren (PHPUnit usw.)
php composer.phar install

# Testsuite ausführen
php composer.phar exec phpunit

# WordPress Coding Standards prüfen
php composer.phar exec phpcs -- --standard=WordPress includes/
```

Das Plugin hat keinen JavaScript-Build-Schritt. Die Assets in `assets/` sind einfache JavaScript-Dateien, die je Admin-Seite bedingt geladen werden.

---

## Changelog

### 1.2.2 (2026-02)

- **Dashboard-UX** — Progress Bars für Meta-Coverage, gestaltete Quick-Links, KI-Crawler-Dot-Indikatoren
- **Welcome-Notice** — Dismissibler Bavarian-Gag, 24h Auto-Expiry, pro Benutzer (User-Meta)
- **Status-Widget** — Geschätzter Token-Verbrauch und USD-Kosten im Provider-Status-Widget
- **KI-Aktivierungstoggle** — Checkbox + Kostenwarnung auf der Anbieterseite; KI ohne API-Key-Löschung deaktivierbar
- **Token-Usage-Tracking** — `MetaGenerator::record_usage()` akkumuliert in `bre_usage_stats`
- **Transient-Caching** — Dashboard-DB-Queries 5 Minuten gecacht via `bre_meta_stats` + `bre_crawler_summary`
- **i18n** — Alle zuvor hartkodierten deutschen Strings in `admin.js` in `breAdmin.*`-Lokalisierung überführt
- **de_DE-Übersetzung** — 14 neue Strings in `.po`/`.mo` ergänzt
- **82 Tests, 160 Assertions** — alle grün

### 1.2.1 (2026-02)

- **Schema.org-Unterseite** — Eigene Admin-Seite (`SchemaPage`) mit eigenem Option-Key `bre_schema_settings`; abwärtskompatibel mit bestehenden `bre_meta_settings`-Werten
- **Admin-Menü** — Neuer Eintrag „Schema.org" nach dem Meta-Generator
- **Settings-Konsolidierung** — `SettingsPage::getSettings()` merged alle drei Option-Keys
- **80 Tests, 154 Assertions** — alle grün

### 1.0.0 (2025)

- Erstveröffentlichung
- KI-Meta-Generator: Auto-Generierung beim Veröffentlichen, benutzerdefinierter Prompt mit `{title}`, `{content}`, `{excerpt}`, `{language}`, Polylang/WPML-Spracherkennung
- Massen-Generator: Batch-AJAX-Verarbeitung, Rate-Limiting (6 s Delay), Transient-Lock, bis zu 3 Versuche pro Beitrag, Live-Fortschrittslog, Kostenschätzung
- Schema.org: Organization, Article, Author, Speakable, BreadcrumbList JSON-LD; KI-Meta-Tags; eigenständige Meta-Description-Ausgabe
- llms.txt: paginiert, ETag/Last-Modified-Caching, benutzerdefinierte Abschnitte, manuelles Cache-Leeren
- robots.txt-Verwaltung: 13 bekannte KI-Bot-User-Agents einzeln konfigurierbar
- Crawler-Log: Datenbanktabelle, SHA-256-IP-Hashing, wöchentlicher Cron-Purge, Dashboard-Zusammenfassung
- Meta-Editor-Box: Inline-Quellen-Badge, Zeichenzähler, Einzel-Beitrag-KI-Regenerierung
- SEO-Analyse-Widget: Live-Wortanzahl, Lesezeit, Überschriftenstruktur, Link-Counts, Inline-Warnungen
- Link-Analyse: Beiträge ohne interne Links, externe-Link-Ausreißer, Top-Pillar-Pages (1-Stunden-Cache)
- KeyVault: XOR-Verschleierung gespeicherter API-Keys via WP-Salts, keine OpenSSL-Abhängigkeit
- FallbackMeta: Satzgrenzen-bewusste 150–160-Zeichen-Extraktion
- Multi-Provider: OpenAI, Anthropic Claude, Google Gemini, xAI Grok
- Kompatibel mit Rank Math, Yoast SEO, AIOSEO, SEOPress oder keinem SEO-Plugin
