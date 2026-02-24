# Schema-Suite v2 — Design-Dokument
**Datum:** 2026-02-24
**Version:** zielt auf BRE 1.2.0 (MINOR — neues Feature)
**Status:** Approved

---

## Ziel

Erweiterung des SchemaEnhancers um eine vollständige Blogger-Schema-Suite.
Abdeckung: alle gängigen Blog-Typen (Tech, Review, Rezepte, Videos, How-To,
Events). FAQ-Schema wird automatisch aus dem vorhandenen GEO Quick Overview
gezogen — kein Doppelaufwand für den Nutzer.

---

## Architektur

Keine neue Feature-Klasse. Alle Schema-Builder bleiben in `SchemaEnhancer.php`.
Neue Metabox-Klasse für Post-Editor-Eingaben.

```
bre-dev/includes/
├── Features/
│   └── SchemaEnhancer.php          ← neue Builder-Methoden
└── Admin/
    ├── SchemaMetaBox.php            ← NEU
    └── views/
        └── schema-meta-box.php     ← NEU
```

**Neue Post-Meta-Keys:**
- `_bre_schema_type` — `'howto'|'review'|'recipe'|'event'|''`
- `_bre_schema_data` — JSON mit typ-spezifischen Feldern

---

## Automatische Schema-Typen (kein UI)

### FAQPage
- Datenquelle: `GeoBlock::getMeta($post_id)['faq']`
- Nur ausgeben wenn `count(faq) > 0`
- Struktur:
  ```json
  { "@type": "FAQPage", "mainEntity": [
    { "@type": "Question", "name": "…",
      "acceptedAnswer": { "@type": "Answer", "text": "…" } }
  ]}
  ```

### BlogPosting
- Ersetzt/ergänzt `article_about`
- `get_post_type() === 'post'` → `@type: BlogPosting`, sonst `Article`
- Author wird embedded als `Person`-Objekt (stärkeres Signal als separates Schema)
- `image` Property aus Featured Image wenn vorhanden

### ImageObject
- Nur wenn Featured Image gesetzt
- Felder: `contentUrl`, `width`, `height`, `name` (alt-text)

### VideoObject
- Regex auf `post_content` für YouTube (`youtu.be`, `youtube.com/embed`) und Vimeo
- YouTube Thumbnail: `https://i.ytimg.com/vi/{VIDEO_ID}/hqdefault.jpg`
- Felder: `embedUrl`, `thumbnailUrl`, `name` (Post-Titel), `uploadDate`

---

## Metabox-Typen (UI im Post-Editor)

### Metabox-Klasse: SchemaMetaBox
- Erscheint auf: alle `post` und `page` Edit-Screens
- Dropdown "Schema-Typ" → blendet per JS passende Felder ein
- Speichern: `save_post`-Hook, Nonce-gesichert, alle Felder sanitized

### HowTo
**UI:**
- Name des How-Tos (text)
- Schritte (textarea, eine Zeile = ein Schritt)

**Schema-Output:**
- Jede Zeile → `HowToStep` mit `name`
- `@type: HowTo`, `name`, `step: [HowToStep, …]`

### Review
**UI:**
- Bewertetes Produkt/Dienst (text)
- Bewertung 1–5 (number input)

**Schema-Output:**
- `@type: Review`
- `itemReviewed: { @type: Thing, name: … }`
- `reviewRating: { @type: Rating, ratingValue: X, bestRating: 5 }`
- `author: { @type: Person, name: … }` (aus Post-Author)

### Recipe
**UI:**
- Zubereitungszeit in Minuten (number)
- Kochzeit in Minuten (number)
- Portionen (text)
- Zutaten (textarea, eine Zeile = eine Zutat)
- Anleitung (textarea, eine Zeile = ein Schritt)

**Schema-Output:**
- Zeiten werden zu ISO 8601 Duration konvertiert (`PT30M`)
- `recipeIngredient: [string, …]`
- `recipeInstructions: [{ @type: HowToStep, text: … }]`

### Event
**UI:**
- Event-Name (text)
- Startdatum (date input)
- Enddatum (date input)
- Ort / URL (text)
- Online-Event (checkbox)

**Schema-Output:**
- Online: `@type: OnlineBusiness` location, sonst `@type: Place`
- `eventStatus: EventScheduled`

---

## Settings-Integration

Neue Checkboxen in `SettingsPage` (Schema-Sektion), identisch zur bestehenden Logik:

```
[✓] FAQPage (aus GEO Quick Overview)
[✓] BlogPosting (statt Article für Posts)
[✓] ImageObject (Featured Image)
[ ] VideoObject (YouTube/Vimeo Auto-Erkennung)
[ ] HowTo (Metabox)
[ ] Review mit Bewertung (Metabox)
[ ] Recipe (Metabox)
[ ] Event (Metabox)
```

Bestehende Typen (`organization`, `author`, `speakable`, `breadcrumb`, `ai_meta_tags`)
bleiben unverändert. Keine Breaking Change.

---

## Registrierung

`SchemaMetaBox` wird in `Core.php` registriert — nur wenn mindestens einer der
Metabox-Typen (`howto`, `review`, `recipe`, `event`) in `schema_enabled` aktiv ist.

---

## Versionierung

**BRE 1.2.0** — MINOR (neues Feature, kein Breaking Change)
