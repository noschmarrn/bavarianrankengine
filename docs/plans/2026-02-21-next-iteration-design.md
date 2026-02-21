# Bavarian Rank Engine — Next Iteration Design

**Date:** 2026-02-21
**Status:** Approved
**Working dir:** `bre-dev` (renamed from `seo-geo`)
**Deploy target:** `bavarian-rank-engine/`

---

## Directory Rename

`seo-geo` → `bre-dev` (dev source)
`bavarian-rank-engine` stays as built/deployed plugin.
`bin/build.sh` PLUGIN_SRC path updated accordingly.

---

## 1. Bulk Generator — Bug Fix + Queue

### Root Cause
`getPostsWithoutMeta()` fetches newest X posts via `ORDER BY ID DESC LIMIT` then PHP-filters.
After first run, newest posts all have meta → returns empty.
`countPostsWithoutMeta()` correctly uses NOT EXISTS SQL — inconsistency causes "fertig" with 0 results.

### Fix
Replace PHP-loop filtering with a proper SQL `NOT EXISTS` subquery (same pattern as `countPostsWithoutMeta`).

### Queue Improvements
- **Lock:** WP-Transient `bre_bulk_running` (TTL 15 min). UI shows warning if lock active.
- **Rate Limit:** 6 s JS delay between batches = max 10 posts/min. Configurable in settings (default 10/min, max 30/min).
- **Retries:** PHP retries each post up to 3× with 1 s sleep. On final failure: saves `_bre_bulk_failed` post-meta with error text.
- **Logging:** Every step logged to UI console: lock acquired, post ID, retry count, error message, lock released.
- **Stop:** Explicit lock release on user cancel.

### UI Additions
- Lock warning banner when another run is active.
- "Failed Posts" summary after run (count + list with error reasons).
- Separate "Clear Failed Flags" button to reset `_bre_bulk_failed` meta.

---

## 2. Provider Cost Settings

### Storage
Per-provider, per-model cost stored in `bre_settings['costs'][provider_id][model_id]`:
```php
'costs' => [
    'openai' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ],
]
```

### Provider Settings UI
Each model row gets two extra fields: Input $/1M and Output $/1M.
Pricing link shown next to provider selector:
- OpenAI → `https://openai.com/de-DE/api/pricing`
- Claude → `https://platform.claude.com/docs/en/about-claude/pricing`
- Gemini → `https://ai.google.dev/gemini-api/docs/pricing?hl=de`
- Grok → `https://docs.x.ai/developers/models`

### Bulk View Cost Estimate
- If costs configured: `~$0.0034 geschätzt (800 Input + 50 Output Token × Preis)`
- If no costs: `~800 Input + 50 Output Token` (existing behaviour)

---

## 3. llms.txt Improvements

### Rank Math Conflict — parse_request Hook
Hook into `parse_request` (fires before `template_redirect`).
Check `$_SERVER['REQUEST_URI']` for `/llms.txt` or `/llms-{n}.txt`.
If matched and BRE llms enabled: serve + exit. Rank Math never fires.
Admin notice when Rank Math active: "BRE bedient llms.txt — kein Handlungsbedarf."

### HTTP Caching Headers
- `ETag: "<md5 of content>"`
- `Last-Modified: <date of newest post or last settings save>`
- Respond 304 Not Modified when `If-None-Match` / `If-Modified-Since` match.

### Transient Cache
- Content stored in `bre_llms_cache` (no expiry — manual or auto invalidation).
- Invalidated on: settings save, new published post (optional toggle).
- Admin UI: "Cache leeren" button on llms.txt page.

### Pagination
- User sets `max_links` (default 500, min 50).
- If total posts > max_links: split into pages.
- `llms.txt` = header + first N posts + `## More\n- [llms-2.txt](…)` links.
- `llms-2.txt`, `llms-3.txt` etc. served via same parse_request handler.
- Rewrite rules: `^llms-(\d+)\.txt$` → `index.php?bre_llms_page=$matches[1]`

---

## 4. Meta Fallback + Post Editor Widget

### FallbackMeta Helper
`FallbackMeta::extract(WP_Post $post): string`
1. Take `post_content`, strip all HTML.
2. Split into sentences (regex on `.!?`).
3. Accumulate sentences until 150–160 chars.
4. If first sentence already >160: truncate at last space ≤157 + "…".
5. Return clean UTF-8 string, no trailing partial words.

Activated when: no API key configured, or provider throws exception, or "Fallback aktiviert" setting.

### Post Editor Meta Box
`add_meta_box('bre_meta', 'Meta Description (BRE)', ...)` — registers for all configured post types.
Renders in classic editor sidebar + Gutenberg sidebar (PHP meta box works in both).

Contents:
- Current meta description (editable textarea, maxlength 160)
- Live character counter (0 / 160) — JS
- Source badge: "KI generiert" / "Fallback" / "Manuell" — stored in `_bre_meta_source`
- Button "Mit KI neu generieren" (AJAX, requires API key)
- Save on post save via `save_post` hook

---

## 5. Dashboard — Link Analysis (no AI)

New dashboard card: "Interne Link-Analyse". Loaded via AJAX on page load.

- **Posts ohne interne Links:** SQL query on `post_content` using `NOT LIKE '%href="%site_url%'`.
- **Posts mit zu vielen externen Links:** Count `href="http` occurrences in content. Threshold configurable (default: 5). Lists post titles + counts.
- **Pillar Pages (Top 5):** Count how often each post URL appears in `post_content` of other posts. Shows most-linked-to posts.

All queries run on `wp_posts` — no extra tables. Cached in transient (1h).

---

## 6. Post Editor SEO Widget (no AI)

Second meta box `bre_seo_widget` — read-only analysis panel.

```
SEO Analyse
──────────────────────────────
Titel:        45 / 60 Zeichen ████████░░
──────────────────────────────
Wörter:       1.234
Lesbarkeit:   ~5 Min. Lesezeit
──────────────────────────────
Überschriften:
  ✓ 1× H1
  ⚠ 0× H2  ← Warnung
  ✓ 3× H3
──────────────────────────────
Links:
  ✓ 3 interne Links
  ⚠ 0 externe Links
──────────────────────────────
```

Warnings:
- 0× H1 → "Keine H1-Überschrift"
- >1× H1 → "Mehrere H1-Überschriften"
- 0 interne Links → "Keine internen Links"

Updated live on post content changes via JS (debounced 500ms) in block editor.
In classic editor: updates on focus-out of content area.

---

## 7. robots.txt + AI Crawler (no AI)

### robots.txt Integration
Hook: `robots_txt` filter (WordPress built-in).
UI: new admin page "Bavarian Rank → robots.txt / AI Bots".

Known bots list (toggles per bot):
```
GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot,
Applebot-Extended, Bytespider, DataForSeoBot, ImagesiftBot,
omgili, Diffbot, FacebookBot, Amazonbot
```

Each toggle adds:
```
User-agent: GPTBot
Disallow: /
```

UI note: "Bots müssen sich nicht daran halten."

### AI Crawler Log
Custom DB table `{prefix}bre_crawler_log` created on plugin activation:
```sql
id, bot_name, ip_hash, url, visited_at
```

Hook: `init` (priority 1) — check `HTTP_USER_AGENT` against bot list. If match: insert row (non-blocking).
Dashboard card: "AI Crawler — letzte 30 Tage" showing bot name, count, last seen.
Log auto-purge: entries older than 90 days deleted weekly via WP Cron.

---

## File Changelist

### New Files
```
includes/Helpers/BulkQueue.php         Lock + rate-limit helpers
includes/Helpers/FallbackMeta.php      First-paragraph extraction
includes/Features/RobotsTxt.php        robots.txt filter + bot list
includes/Features/CrawlerLog.php       AI crawler tracking
includes/Admin/MetaEditorBox.php       Post-editor meta description box
includes/Admin/SeoWidget.php           Post-editor SEO analysis widget
includes/Admin/RobotsPage.php          robots.txt admin page
includes/Admin/views/robots.php        robots.txt view
assets/editor-meta.js                  Char counter + AJAX regen in meta box
assets/seo-widget.js                   Live SEO analysis in editor
```

### Modified Files
```
includes/Features/MetaGenerator.php    Fallback, retries, _bre_meta_source
includes/Features/LlmsTxt.php          parse_request, ETag, cache, pagination
includes/Admin/BulkPage.php            Lock status enqueue
includes/Admin/ProviderPage.php        Cost fields
includes/Admin/LlmsPage.php            Cache-clear button, pagination settings
includes/Admin/SettingsPage.php        Cost + crawlerlog schema
includes/Admin/AdminMenu.php           Register robots page
includes/Admin/views/bulk.php          Lock warning, failed posts, cost estimate
includes/Admin/views/provider.php      Cost fields per model
includes/Admin/views/llms.php          Cache-clear, limit/pagination
assets/bulk.js                         Rate-limit delay, retries, lock check
Core.php                               Register new features + DB install
seo-geo.php (main file)                Activation hook for DB table
bin/build.sh                           Updated PLUGIN_SRC path
```
