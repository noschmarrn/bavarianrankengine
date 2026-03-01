# Internal Link Suggestions — Design

**Date:** 2026-03-01
**Version target:** 1.3.x (MINOR)
**Status:** Approved

---

## Problem

Bloggers write good articles but miss opportunities to strengthen their site with internal links. Existing BRE tools (SeoWidget counter, Dashboard analysis) show the problem but offer no actionable help while writing.

## Goal

Show contextual internal link suggestions below the editor while writing. The blogger reviews, selects, and confirms — nothing is inserted automatically without explicit approval.

Works **without AI** (text-based matching). AI is an optional quality upgrade.

---

## Architecture

```
WordPress Editor (Gutenberg + Classic)
    │
    │  Trigger: Button / Save / Interval (configurable)
    ▼
link-suggest.js
  • Extracts content from editor (Gutenberg + Classic, same pattern as seo-widget.js)
  • Trigger logic
  • Suggestion list UI with checkboxes
  • Preview modal before apply
  • Apply logic via official editor APIs (wp.blocks / tinyMCE)
    │
    │  AJAX (post_content, post_id)
    ▼
LinkSuggest.php (new AJAX handler)
  1. Sanitise + tokenise content
  2. Candidate pool from DB (title, tags, categories, excerpt)
     └── Transient cache (invalidated on save_post), limit 500 posts DESC date
  3. Apply exclusions (excluded_posts filter)
  4. Score: (title-overlap × 3) + (tag-overlap × 2) + (category-overlap × 1)
  5. Apply boost: FinalScore = Score × boost_factor
     (boost only amplifies, never creates relevance from zero)
  6. Top-20 → find best anchor phrase (N-grams 2–6 words, skip existing <a> tags)
  7. [optional] Top-N candidates + content → AI provider (if connected)
  8. Return top-10 suggestions as JSON
```

**New files:**
```
includes/Features/LinkSuggest.php          AJAX handler + matching algorithm
includes/Admin/LinkSuggestPage.php         Settings page + sanitize
includes/Admin/views/link-suggest-settings.php
assets/link-suggest.js
```

**Unchanged:** `SeoWidget.php`, `LinkAnalysis.php`, all existing features.

---

## PHP: Matching Algorithm

### Candidate pool (Transient, 1h TTL)
- All published posts + pages, excluding current post
- Fields: `id, title, url, tags[], categories[], excerpt`
- Max 500 posts ordered by `post_date DESC`
- Invalidated via `add_action('save_post', ...)`

### Pipeline per AJAX request
1. Strip HTML, remove stop-words (de + en wordlist, ~150 words each)
2. Build content token set
3. For each candidate: `Score = (title_overlap × 3) + (tag_overlap × 2) + (category_overlap × 1)`
   - `title_overlap = shared_tokens / title_token_count`
4. Remove `excluded_posts`
5. Apply `boost_factor`: `FinalScore = Score × boost` (default boost = 1.0)
6. Take top-20 by FinalScore
7. For each: find best N-gram (2–6 words) in raw content overlapping candidate title
8. Return top-10 as `[{phrase, post_id, post_title, url, score, boosted}]`

### AI upgrade (optional)
- Replaces steps 7–8 when AI provider is connected + `ai_enabled`
- Input: current post content + top-20 candidates list
- Prompt: structured, asks for `{phrase, post_id, reason}` per match
- Candidate count and max output tokens are configurable in settings
- Falls back to non-AI matching if AI call fails

---

## JavaScript: Editor Integration + UI

### Trigger modes
```js
breLinkSuggest.triggerMode = 'manual' | 'save' | 'interval'
breLinkSuggest.intervalMs  = 120000

// Gutenberg save hook
wp.data.subscribe(() => {
    if (isSaving && mode === 'save') triggerAnalysis();
});

// Interval
if (mode === 'interval') setInterval(triggerAnalysis, intervalMs);

// Manual button always available regardless of mode
```

### UI states
```
[Initial / empty]
    → Trigger fires
[Loading ⟳]
    → AJAX response
    ├─ no results → "No suggestions found"
    └─ results    → [Suggestion list]
                        → [Preview modal]
                            → [Applied — X links set ✓]
```

### Suggestion list
```
┌────────────────────────────────────────────────────────┐
│ 🔗 Internal Link Suggestions        [Analyse] [⚙]      │
├────────────────────────────────────────────────────────┤
│ ☐  "Bavarian Alps"   →  Alpen Wandern Guide     [↗]    │
│ ☑  "mountain trail"  →  10 Trails Bayern        [↗]    │
│ ☑  "Wanderweg"       →  Wandern Tipps        ★  [↗]    │
├────────────────────────────────────────────────────────┤
│ [All] [None]               [ Apply (2 Links) ]         │
└────────────────────────────────────────────────────────┘
[↗] = open target in new tab
★   = boosted post (visual indicator only)
```

### Preview modal
Shows each phrase in sentence context with the link applied, before confirming. Cancel returns to list without changes.

### Apply logic
- Find first occurrence of phrase in content not already inside `<a>`
- **Gutenberg:** modify block attributes via `wp.blocks` API
- **Classic Editor:** `tinyMCE.activeEditor.setContent(modifiedHtml)`
- No DOM manipulation — official editor APIs only

---

## Settings: Own Admin Page

New menu entry in BRE admin menu: **Link-Vorschläge / Link Suggestions**

```
Bavarian Rank Engine
  ├── Dashboard
  ├── AI Provider
  ├── Meta Generator
  ├── Schema
  ├── Bulk Generator
  ├── Link Suggestions     ← NEW
  └── llms.txt / Robots
```

### Settings fields (stored in `bre_link_suggest_settings` option)
```php
[
    'trigger'        => 'manual',  // 'manual' | 'save' | 'interval'
    'interval_min'   => 2,
    'excluded_posts' => [],        // [int, ...]
    'boosted_posts'  => [],        // [['id' => int, 'boost' => float], ...]
    'ai_candidates'  => 20,        // max 50
    'ai_max_tokens'  => 400,
]
```

### Settings UI
- **Trigger section:** radio buttons + interval input (shown only when interval selected)
- **Exclude section:** WordPress post search (REST `wp/v2/search`), tag list with remove button
- **Boost section:** same search, each entry has a boost input (float, default 1.5, min 1.1)
- **AI options section:** only rendered when `$has_ai === true` (same condition as Bulk Generator)

### Data flow to editor
`LinkSuggestPage::enqueue()` → `wp_localize_script()` passes only:
`triggerMode, intervalMs, ajaxUrl, nonce, postId`

Excluded/boosted post IDs stay server-side — not exposed to the browser.

---

## Localization

All PHP strings use `__()` / `esc_html_e()` with textdomain `bavarian-rank-engine`.
All JS strings passed via `wp_localize_script()` `i18n` array (same pattern as `bulk.js`).

Files to update:
- `languages/bavarian-rank-engine.pot`
- `languages/bavarian-rank-engine-de_DE.po` + recompile `.mo`
- `languages/bavarian-rank-engine-en_US.po`

---

## Performance (Shared Hosting)

- Candidate pool: Transient with 1h TTL → single DB query cached
- Matching: runs only on trigger, never on keystroke
- Manual mode is the default → zero background load
- Interval mode: one lightweight AJAX call per N minutes
- AI call: only when explicitly triggered + AI connected
- No cron jobs, no persistent background processes

---

## Non-Goals (explicitly out of scope)

- Automatic link insertion without user confirmation
- External link suggestions
- Broken link checking
- Sitemap or link graph visualization
