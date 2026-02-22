# SEO & GEO Tools — Plugin Design Doc
**Datum:** 2026-02-20
**Status:** Approved
**Credit:** Konzept & Entwicklung via Donau2Space.de

---

## 1. Ziel & Positionierung

Ein generisches, wiederverwendbares WordPress-Plugin das klassische SEO um **GEO (Generative Engine Optimization)** erweitert — damit Blogs von KI-Assistenten wie ChatGPT, Claude, Grok und Gemini als Quellen erkannt und zitiert werden.

- **Kein Dependency auf Rank Math, Yoast oder andere SEO-Plugins** — funktioniert standalone oder als Ergänzung
- **Zielmarkt:** Primär deutschsprachiger Raum (DE/AT/CH)
- **Im Admin:** Diskreter "Powered by Donau2Space.de"-Link als Credit

---

## 2. Architektur — Ansatz B: Provider-Abstraktion

```
seo-geo/
├── seo-geo.php                    # Plugin-Header, Bootstrap
├── README.md                      # Nutzerdoku + Erweiterungsguide
├── uninstall.php
├── includes/
│   ├── Core.php                   # Plugin-Lifecycle, Hook-Registration
│   ├── Admin/
│   │   ├── SettingsPage.php       # Haupt-Einstellungen
│   │   └── BulkPage.php          # Bulk-Meta-Generator Seite
│   ├── Providers/
│   │   ├── ProviderInterface.php  # Interface: getModels(), generateText()
│   │   ├── ProviderRegistry.php   # Registrierung + Auflösung von Providern
│   │   ├── OpenAIProvider.php
│   │   ├── AnthropicProvider.php
│   │   ├── GeminiProvider.php
│   │   └── GrokProvider.php
│   ├── Features/
│   │   ├── MetaGenerator.php      # Publish-Hook + Bulk-Logik
│   │   └── SchemaEnhancer.php     # JSON-LD Output
│   └── Helpers/
│       └── TokenEstimator.php     # Kostenschätzung vor Bulk-Run
└── assets/
    ├── admin.css
    └── admin.js                   # Bulk-Page AJAX
```

### Erweiterbarkeit (dokumentiert für zukünftige Entwicklung)

- **Neuer AI-Provider:** Klasse anlegen die `ProviderInterface` implementiert → in `ProviderRegistry` registrieren → fertig. Kein Core-File anfassen.
- **Neues Feature:** Eigene Klasse in `Features/` anlegen, per WordPress-Hook selbst registrieren.
- **Alles via Hooks:** Einstellungen, Provider-Liste, Schema-Typen — alles filterbar über `seo_geo_*` Filter/Actions.

---

## 3. Feature 1 — AI Meta-Generator

### Auto-Modus (Publish-Hook)
- Trigger: `publish_post`, `publish_page` (und optional Custom Post Types)
- Guard: Prüft ob bereits eine Meta-Beschreibung existiert (kompatibel mit Rank Math `rank_math_description`, Yoast `_yoast_wpseo_metadesc`, AIOSEO, natives Custom Field `_meta_description`) — wenn ja: nichts tun
- Holt Post-Inhalt, kürzt je nach Token-Modus, sendet an aktiven Provider
- Speichert Ergebnis in `_seo_geo_meta_description` (eigenes Field, plus Kompatibilitäts-Write für erkanntes SEO-Plugin)

### Bulk-Modus (manuelle Seite)
- Zeigt Statistik: Anzahl Posts ohne Meta-Beschreibung, nach Post-Type aufgeteilt
- Einstellbar: Limit (Anzahl Artikel pro Run), Provider + Modell wählbar
- **Kostenschätzung** vor dem Start (Token-Schätzung × Preis/Token des gewählten Modells)
- Verarbeitung per AJAX-Batches (5 Artikel gleichzeitig)
- Fortschrittsbalken + Log-Ausgabe (welcher Artikel, Ergebnis)
- Abbruch-Button während dem Run

### Token-Modus
- Option A: **Ganzen Artikel senden** (kein Limit)
- Option B: **Auf X Token kürzen** (Default: 1000, einstellbar)

### Prompt-System
- **Vollständig editierbar** im Admin, mit Reset-auf-Default-Button
- Unterstützt Variablen: `{title}`, `{excerpt}`, `{content}`, `{language}`
- **`{language}`** wird automatisch ermittelt: WordPress-Locale → Polylang → WPML → Fallback `de`
- **Default-Prompt (Deutsch-fokussiert):**
  ```
  Schreibe eine SEO-optimierte Meta-Beschreibung für den folgenden Artikel.
  Die Beschreibung soll für menschliche Leser verständlich und hilfreich sein,
  den Inhalt treffend zusammenfassen und zwischen 150 und 160 Zeichen lang sein.
  Schreibe die Meta-Beschreibung auf {language}.
  Antworte ausschließlich mit der Meta-Beschreibung, ohne Erklärung.

  Titel: {title}
  Inhalt: {content}
  ```

### API-Key Validierung
- "Verbindung testen"-Button neben jedem API-Key-Feld
- Macht einen minimalen Test-API-Call (günstigstes Modell, 1 Token)
- Zeigt: ✓ Verbunden (grün) oder ✗ Fehler: [Fehlermeldung] (rot)
- Status-Indikator auch im Bulk-Run-Log bei fehlgeschlagenen Calls

---

## 4. Feature 2 — Schema.org Enhancer (GEO-fokussiert)

Ergänzt was Rank Math/Yoast typischerweise nicht oder unvollständig liefert.
Alle Typen **einzeln ein-/ausschaltbar** in den Einstellungen.

| Schema-Typ | GEO-Zweck |
|---|---|
| `Organization` mit `sameAs` | Verknüpft Domain mit Social-Profilen → AI erkennt Entität |
| `Author` mit `sameAs` | Autor als zitierfähige Entität für AI-Assistenten |
| `Speakable` | Markiert Abschnitte die AI-Assistenten zitieren/vorlesen sollen |
| `Article` → `about` / `mentions` | Themen-Entitäten explizit verknüpfen |
| `BreadcrumbList` | Falls kein anderes Plugin das bereits ausgibt |
| AI-Meta-Tags | `<meta name="robots" content="max-snippet:-1">` etc. |

Ausgabe als `<script type="application/ld+json">` im `<head>`.
Vor Ausgabe prüfen ob dasselbe Schema bereits von einem anderen Plugin ausgegeben wird (via `wp_head` Output-Buffer Check), um Duplikate zu vermeiden.

---

## 5. AI-Provider System

### Interface (ProviderInterface.php)
```php
interface ProviderInterface {
    public function getName(): string;
    public function getModels(): array;        // ['id' => 'label', ...]
    public function testConnection(): bool;
    public function generateText(string $prompt, string $model, int $maxTokens): string;
}
```

### Unterstützte Provider V1
| Provider | Default-Modell | Modell-Auswahl |
|---|---|---|
| OpenAI | gpt-4.1 | gpt-4.1, gpt-4o, gpt-3.5-turbo |
| Anthropic | claude-sonnet-4-6 | claude-opus-4-6, claude-sonnet-4-6, claude-haiku-4-5 |
| Google Gemini | gemini-2.0-flash | gemini-2.0-pro, gemini-2.0-flash |
| xAI Grok | grok-3 | grok-3, grok-3-mini |

---

## 6. Einstellungsseiten

### Haupt-Einstellungen (`Einstellungen → SEO & GEO Tools`)

| Bereich | Felder |
|---|---|
| **Provider** | Aktiver Provider (Dropdown), API-Key (+ Test-Button), Modell (Dropdown dynamisch) |
| **Meta-Generator** | An/Aus Auto-Modus, Post-Types (Checkboxen), Token-Modus, Max-Token, Prompt (Textarea + Reset) |
| **Schema Enhancer** | Checkboxen pro Schema-Typ, sameAs-URLs für Organization + Authors |
| **Info** | Version, Doku-Link, "Powered by Donau2Space.de" |

### Bulk-Seite (`Tools → GEO Bulk Meta`)

1. **Statistik-Block:** X Posts ohne Meta (nach Post-Type)
2. **Konfiguration:** Provider, Modell, Limit (Anzahl)
3. **Kostenschätzung:** "Geschätzte Kosten: ~X Token = ca. X Cent"
4. **Start-Button** → AJAX-Run mit Fortschrittsbalken
5. **Live-Log:** Artikel-Titel, Status (✓ / ✗), generierte Beschreibung (klappbar)
6. **Abbrechen-Button** während Run

---

## 7. Nicht in V1 (bewusst ausgelassen)

- Extension-API für Drittentwickler (kann in V2 ergänzt werden)
- Automatischer Cron für alte Artikel (nur manueller Bulk in V1)
- Mehrsprachige Prompts pro Post (Polylang-Deep-Integration)
- Keyword-Analyse oder Content-Scoring

---

## 8. Technische Entscheidungen

- **PHP 8.0+**, WordPress 6.0+
- **Keine externen Composer-Dependencies** in V1 — alle API-Calls via `wp_remote_post()`
- **Nonces + Capability-Checks** auf allen Admin-Actions (`manage_options`)
- **Eigene Options-Tabelle:** nein — `get_option('seo_geo_settings', [])` als serialisiertes Array
- **Logging:** Einfaches `error_log()` mit `[SEO-GEO]` Prefix, optional erweiterbar

