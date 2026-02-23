# Design: Schema Modul (JSON-LD Graph) + Gutenberg-Sidebar

**Datum:** 2026-02-23
**Version:** BRE 1.2.0 (MINOR — neues Feature)
**Status:** Genehmigt

---

## Ziel

Ein eigenständiges Schema-Modul für Bavarian Rank Engine, das:
- Umfangreiche Schema-Typen als einen einzigen JSON-LD `@graph` ausgibt
- Bestehende BRE-Funktionen integriert (GEO-FAQ → FAQPage, KI-Meta → description)
- Eine vollständige Gutenberg-Sidebar mit drei Tabs ersetzt alle bisherigen Meta Boxes (Meta, GEO, Schema)

---

## Architektur-Entscheidungen

- **PHP-Architektur:** Sources → Builder → Validator → Renderer (Ansatz A)
- **Editor:** `@wordpress/scripts` Build-Step, ein Entry-Point → `assets/editor.js`
- **Migration:** Sauberer Neustart (keine Migrations-Logik für alte `schema_enabled`-Settings)
- **Gutenberg-Migration:** GeoEditorBox + MetaEditorBox → vollständig in React-Sidebar

---

## Sektion 1: Dateistruktur

### Neu

```
bre-dev/
├── package.json
├── webpack.config.js
├── src/
│   └── editor/
│       ├── index.js
│       ├── sidebar/
│       │   ├── BRESidebar.js
│       │   ├── MetaTab.js
│       │   ├── GeoTab.js
│       │   └── SchemaTab.js
│       └── schema/
│           ├── SchemaTypeSelector.js
│           ├── builders/
│           │   ├── FaqBuilder.js
│           │   ├── HowToBuilder.js
│           │   └── RecipeBuilder.js
│           └── AutoSuggestions.js
│
├── includes/
│   ├── Features/
│   │   └── Schema/
│   │       ├── SchemaOptions.php
│   │       ├── SchemaRenderer.php
│   │       ├── SchemaGraphBuilder.php
│   │       ├── SchemaValidator.php
│   │       ├── Sources/
│   │       │   ├── WordPressSource.php
│   │       │   └── GeoSource.php
│   │       └── Types/
│   │           ├── BlogPosting.php
│   │           ├── WebPage.php
│   │           ├── FAQPage.php
│   │           ├── HowTo.php
│   │           ├── Recipe.php
│   │           └── Event.php
│   └── Admin/
│       ├── SchemaPage.php
│       └── EditorSidebar.php
```

### Entfernt / Ersetzt

| Alt | Neu |
|-----|-----|
| `Features/SchemaEnhancer.php` | `Features/Schema/SchemaRenderer.php` + `SchemaGraphBuilder.php` |
| `Admin/MetaEditorBox.php` | React `MetaTab.js` |
| `Admin/GeoEditorBox.php` | React `GeoTab.js` |
| `assets/editor-meta.js` | `src/editor/` → Build → `assets/editor.js` |
| `assets/geo-editor.js` | (idem) |
| Schema-Felder in `SettingsPage.php` | `SchemaPage.php` + `SchemaOptions.php` |

---

## Sektion 2: Datenmodell

### Global Options (`wp_options`)

```
// Modul
bre_schema_enabled               bool       default: true
bre_schema_render_location       string     'head' | 'footer'
bre_schema_post_types            array      ['post', 'page']
bre_schema_default_type_post     string     'BlogPosting'
bre_schema_default_type_page     string     'WebPage'

// Publisher
bre_schema_publisher_type        string     'Organization' | 'Person'
bre_schema_publisher_name        string
bre_schema_publisher_logo_id     int        Attachment ID
bre_schema_publisher_sameas      array      URLs

// WebSite
bre_schema_website_name          string     Fallback: get_bloginfo('name')
bre_schema_enable_searchaction   bool       default: false

// Breadcrumb
bre_schema_breadcrumb_enabled    bool       default: true

// GEO-Integrationen
bre_schema_use_geo_faq           bool       default: true
bre_schema_use_geo_summary       bool       default: false

// Konflikt-Warnung
bre_schema_conflict_notice       bool       default: true
```

### Post Meta

```
_bre_schema_enabled    bool|null   null = global erben
_bre_schema_type       string      'Auto' | 'BlogPosting' | 'FAQPage' | …
_bre_schema_faq_items  JSON        [{q, a}, …]
_bre_schema_howto      JSON        {name, steps:[{name,text}], tool[], supply[]}
_bre_schema_recipe     JSON        {name, ingredients[], instructions[], prepTime, …}
_bre_schema_event      JSON        {name, startDate, endDate, location}
```

### Integrations-Datenfluss

```
WordPressSource::collect($post_id)
  ├── get_the_title()            → headline
  ├── _bre_meta_description      → description (Priorität 1)
  ├── get_the_excerpt()          → description (Fallback 2)
  ├── _bre_geo_summary           → description (Fallback 3, wenn opt-in + GEO sichtbar)
  ├── get_post_thumbnail_url()   → image
  ├── get_the_author()           → author.name
  └── get_the_date('c')          → datePublished / dateModified

GeoSource::collect($post_id)
  ├── _bre_geo_faq     → faqItems[]    → FAQPage Schema
  ├── _bre_geo_summary → geoSummary    → description Fallback
  └── _bre_geo_enabled → isGeoActive   → Sichtbarkeits-Guard

FAQPage-Priorität:
  1. _bre_schema_faq_items (manueller Builder)
  2. _bre_geo_faq (KI-generiert, wenn bre_schema_use_geo_faq = true + GEO sichtbar)
```

---

## Sektion 3: Admin-Seite

### Menü-Eintrag
`bre-schema` zwischen GEO Block und robots.txt.

### Tabs

**Allgemein**
- Master Toggle (Schema-Modul aktiv)
- Post Types mit Standard-Typ (auto-detect CPTs)
- Ausgabe-Position (head/footer)
- Breadcrumb-Liste
- Konflikt-Warnung

**Publisher**
- Publisher-Typ (Organization / Person)
- Name, Logo (Media Uploader), URL
- SameAs-URLs (dynamische Liste)
- WebSite Entity: Name, SearchAction Toggle

**Integrationen**
- GEO-FAQ → FAQPage Toggle (default: ON)
- GEO-Summary als description-Fallback Toggle (default: OFF)
- Info: `_bre_meta_description` wird automatisch als description verwendet

**Debug**
- Post-ID / URL Eingabe → JSON-LD Graph Vorschau
- Copy-to-clipboard
- Validierungs-Hinweise pro Node (✅ vollständig / ⚠️ fehlende Felder)

### Frontend-Debug
`?bre_schema_debug=1` — Ausgabe als HTML-Kommentar, nur für `manage_options`-User.

### Konflikt-Warnung
`admin_notices` wenn Yoast / RankMath / AIOSEO aktiv und `bre_schema_conflict_notice = true`.

---

## Sektion 4: Gutenberg-Sidebar

### Build
```json
{
  "scripts": {
    "build": "wp-scripts build src/editor/index.js --output-path=assets",
    "start": "wp-scripts start src/editor/index.js --output-path=assets"
  },
  "devDependencies": {
    "@wordpress/scripts": "^30.0.0"
  }
}
```

### Sidebar-Shell
`registerPlugin('bre-sidebar')` mit `PluginSidebar` — ein `<TabPanel>` mit drei Tabs.
Aktiver Tab wird in `localStorage` gespeichert.

### Tab 1: Meta
- Textarea (_bre_meta_description), max 160 Zeichen, Zeichenzähler
- Quelle-Badge (KI-generiert / Manuell / Fallback)
- Button: „Mit KI neu generieren" (apiFetch → wp_ajax_bre_regen_meta)
- State via `useEntityProp` (speichert mit Post)

### Tab 2: GEO
- Toggle (GEO-Block aktiv), Lock-Toggle
- Button: Generieren / Löschen (apiFetch → wp_ajax_bre_geo_generate)
- Felder: Zusammenfassung, Kernpunkte, FAQ
- Hinweis: „GEO-FAQ wird als FAQPage-Schema verwendet"

### Tab 3: Schema
- Toggle (_bre_schema_enabled override)
- Typ-Dropdown (_bre_schema_type)
- Auto-Vorschlag: „FAQ-Daten erkannt → FAQPage möglich"
- Builder (dynamisch je Typ): FAQ-Items, HowTo-Schritte, Recipe-Felder, Event-Felder

### PHP: EditorSidebar.php
- `enqueue_block_editor_assets`: lädt `assets/editor.js` mit auto-generierten Dependencies
- `register_post_meta`: alle `_bre_schema_*` und `_bre_geo_*` Keys mit `show_in_rest: true`
- AJAX-Handler bleiben als `wp_ajax_*` (werden aus React via `apiFetch` aufgerufen)

---

## Sektion 5: Schema-Output & Validierung

### Ausgabe-Format
```json
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    { "@type": "WebSite",        "@id": "…/#bre-website"      },
    { "@type": "Organization",   "@id": "…/#bre-publisher"    },
    { "@type": "BreadcrumbList", "@id": "…/#bre-breadcrumbs"  },
    { "@type": "BlogPosting",    "@id": "…/#bre-blogposting"  }
  ]
}
</script>
```

Stabile `@id`-Muster mit `#bre-*` Suffix.

### Render-Reihenfolge im @graph
1. WebSite
2. Publisher
3. BreadcrumbList
4. Haupt-Typ (BlogPosting / WebPage / FAQPage / HowTo / Recipe / Event)

### Publisher-Referenz
BlogPosting referenziert Publisher per `@id` statt Inline-Objekt.

### Breadcrumb-Logik
```
is_front_page() → keine Breadcrumbs
is_singular()   → Home → [primäre Kategorie →] Post-Titel
is_page()       → Home → [Elternseite →] Seiten-Titel
is_category()   → Home → Kategorie-Name
```

### Validator — Guardrails
```
BlogPosting: headline + datePublished (pflicht)
FAQPage:     mainEntity >= 2 (pflicht)
HowTo:       step >= 2 (pflicht)
Recipe:      ingredient >= 2 + instruction >= 2 (pflicht)
Event:       startDate + location (pflicht)

Nie ausgeben:
- Review/Rating ohne echte Review-Daten
- Product ohne price + currency + availability
- FAQPage wenn GEO-Block nicht sichtbar (store_only mode)
```

### Output-Entscheidungsbaum
```
SchemaRenderer::output()
  ├── bre_schema_enabled?            → nein: abort
  ├── post_type in post_types?       → nein: abort
  ├── _bre_schema_enabled override?  → false: abort
  ├── GraphBuilder::build()
  │     ├── collect Sources
  │     ├── build Nodes
  │     └── Validator::filter()
  ├── $graph leer?                   → abort
  └── echo <script>…@graph…</script>
```

---

## V2 (außerhalb dieses Sprints)

- Product Schema (WooCommerce)
- VideoObject-Erkennung
- Content-Parsing (FAQ-Import aus Gutenberg-Blocks)
- Bessere Duplikat-Erkennung
- Bulk-Schema-Check

---

## Versionierung

Dieses Feature ergibt **v1.2.0** (MINOR — neues Feature, kein Breaking Change für Nutzer).
