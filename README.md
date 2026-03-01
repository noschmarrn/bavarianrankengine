# Bavarian Rank Engine

![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-blue)
![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![License: GPL-2.0](https://img.shields.io/badge/License-GPL--2.0--or--later-green)
![Version](https://img.shields.io/badge/Version-1.3.0-orange)
![Tests](https://img.shields.io/badge/Tests-102%20passing-brightgreen)

🇩🇪 [Deutsche Version → README.de.md](README.de.md)

**Website:** [bavarianrankengine.com](https://bavarianrankengine.com) &nbsp;·&nbsp; [How To](https://bavarianrankengine.com/howto.html) &nbsp;·&nbsp; [FAQ](https://bavarianrankengine.com/faq.html) &nbsp;·&nbsp; [Changelog](https://bavarianrankengine.com/changelog.html)

---

Bavarian Rank Engine is a lightweight SEO & GEO plugin for WordPress. It generates AI-powered meta descriptions, outputs Schema.org structured data, creates GEO content blocks for AI engines, and manages crawler access via robots.txt and llms.txt — all in one plugin, nothing hidden behind a paywall.

It works with or without an AI key. It integrates without conflicts into Rank Math, Yoast, AIOSEO, and SEOPress. No SaaS. No telemetry. No upsells.

---

## Why This Plugin Exists

Most WordPress SEO plugins have evolved in the same direction: bloated feature sets, dashboards full of metrics nobody needed, and a pricing model that locks the useful functionality behind a monthly subscription.

The AI wave made it worse. Plugins started offering "AI-powered" features — but as a proxy service. You pay a monthly fee, your content goes through their servers, they call the AI API on your behalf and add a margin on top.

BRE takes a different approach:

- **Direct API access.** You store your own key from OpenAI, Anthropic, Google, or xAI. BRE calls the API directly. No middleman, no margin, no data passing through third-party servers.
- **Clear output, not noise.** Meta descriptions, structured data, GEO content blocks, bot management. No readability scores, no keyword density meters, no upsell banners.
- **No subscription.** GPL-2.0. Free to use on any number of sites. The only costs are API usage — typically fractions of a cent per post.
- **No telemetry.** BRE sends no data home. No usage tracking, no remote logging, no analytics leaving your server.
- **Works without AI.** No API key? The fallback extractor generates a usable meta description from post content using sentence boundary detection. Every post gets a description.

Built in Passau, Bavaria — for [Donau2Space](https://donau2space.de), a personal AI blog, where exactly this was needed — and nothing more.

---

## Table of Contents

- [Why This Plugin Exists](#why-this-plugin-exists)
- [Directory Structure](#directory-structure)
- [Features](#features)
- [Data Storage](#data-storage)
- [Security](#security)
- [AI Providers](#ai-providers)
- [Hooks & Extensibility](#hooks--extensibility)
- [AJAX Endpoints](#ajax-endpoints)
- [Installation](#installation)
- [Tech Stack](#tech-stack)
- [License](#license)

---

## Directory Structure

```
bavarian-rank-engine/
├── bavarian-rank-engine.php      # Plugin header, constants (BRE_VERSION, BRE_DIR, BRE_URL)
├── uninstall.php                 # Cleanup on plugin deletion
├── assets/
│   ├── admin.css                 # Shared admin stylesheet
│   ├── admin.js                  # Provider selector, connection test
│   ├── bulk.js                   # Bulk generator AJAX loop + progress UI
│   ├── editor-meta.js            # Meta editor box: live counter, AI regen button
│   ├── geo-editor.js             # GEO block editor: generate / clear button
│   ├── geo-frontend.css          # Minimal stylesheet for .bre-geo on frontend
│   ├── link-suggest.js           # Internal link suggestions: trigger, UI, apply (Gutenberg + Classic)
│   └── seo-widget.js             # SEO analysis widget: live evaluation in editor
├── includes/
│   ├── Core.php                  # Singleton bootstrap, loads all dependencies
│   ├── Admin/
│   │   ├── AdminMenu.php         # Menu structure + dashboard render
│   │   ├── BulkPage.php          # Bulk generator admin page
│   │   ├── GeoEditorBox.php      # GEO block meta box in post editor
│   │   ├── GeoPage.php           # GEO block settings page
│   │   ├── LinkAnalysis.php      # AJAX handler for link analysis dashboard
│   │   ├── LinkSuggestPage.php   # Internal link suggestions settings page
│   │   ├── MetaEditorBox.php     # Meta description meta box in post editor
│   │   ├── MetaPage.php          # Meta generator settings page
│   │   ├── ProviderPage.php      # AI provider settings page
│   │   ├── SchemaMetaBox.php     # Schema.org per-post meta box
│   │   ├── TxtPage.php           # TXT Files page: llms.txt + robots.txt (tabbed)
│   │   ├── SchemaPage.php        # Schema.org settings page
│   │   ├── SeoWidget.php         # SEO analysis sidebar widget
│   │   ├── SettingsPage.php      # Central getSettings() — merges all option keys
│   │   └── views/                # PHP templates for all admin pages
│   ├── Features/
│   │   ├── CrawlerLog.php        # Log AI bot visits (own DB table)
│   │   ├── GeoBlock.php          # GEO Quick Overview block (frontend output)
│   │   ├── LlmsTxt.php           # /llms.txt endpoint with ETag/cache
│   │   ├── LinkSuggest.php       # Internal link suggestions: matching engine + AJAX handler + meta box
│   │   ├── MetaGenerator.php     # Core logic: AI call, save, bulk, AJAX
│   │   ├── RobotsTxt.php         # robots.txt bot blocking via WP filter
│   │   └── SchemaEnhancer.php    # JSON-LD Schema.org output in wp_head
│   ├── Helpers/
│   │   ├── BulkQueue.php         # Mutex lock for bulk processes (transient-based)
│   │   ├── FallbackMeta.php      # Meta extraction from post content without AI
│   │   ├── KeyVault.php          # API key obfuscation before writing to DB
│   │   └── TokenEstimator.php    # Rough token estimate for cost preview in bulk
│   └── Providers/
│       ├── ProviderInterface.php # Interface: getId, getName, getModels, testConnection, generateText
│       ├── ProviderRegistry.php  # Registry pattern: register and retrieve providers
│       ├── AnthropicProvider.php # Claude API (Messages API)
│       ├── GeminiProvider.php    # Google Gemini (generateContent API)
│       ├── GrokProvider.php      # xAI Grok (OpenAI-compatible endpoint)
│       └── OpenAIProvider.php    # OpenAI GPT (Chat Completions API)
└── vendor/                       # Composer dependencies (production only)
```

---

## Features

### AI Meta Generator

Generates SEO-optimized meta descriptions (150–160 characters) automatically when a post is published. The prompt is fully customizable; supported placeholders: `{title}`, `{content}`, `{excerpt}`, `{language}`.

**Language detection:** The target language is automatically detected from Polylang, WPML, or the WordPress locale and passed in the prompt — no configuration needed.

**SEO plugin integration:** Generated descriptions are written not only to `_bre_meta_description` but also directly to the native field of the active SEO plugin:

| SEO Plugin | Meta Field |
|---|---|
| Rank Math | `rank_math_description` |
| Yoast SEO | `_yoast_wpseo_metadesc` |
| AIOSEO | `_aioseo_description` |
| SEOPress | `_seopress_titles_desc` |
| (none active) | BRE outputs `<meta name="description">` itself |

**Token mode:** Either the full post content is sent (`full`) or it is trimmed to a configurable token count (100–8000) (`limit`). Trimming is handled by `TokenEstimator` — a word-based estimate without external libraries, using a ratio of ~0.75 words per token.

**Fallback without AI:** `FallbackMeta::extract()` always delivers a usable description — even without an API key or on error. Extraction prefers sentence boundaries (`. `, `! `, `? `), falls back to word boundaries, and only uses a hard cut with `…` as a last resort. Fully multibyte-safe via `mb_substr` / `mb_strrpos`.

---

### GEO Block (Quick Overview)

Generates AI-powered content blocks directly in post text for Generative Engine Optimization:

- **Summary** — brief overview of the post
- **Key Points** — bullet list of the most important points
- **FAQ** — question-answer pairs (only above a configurable word threshold, default: 350 words)

**Insert position** (configurable): after the first paragraph (default), top, bottom.

**Display modes:**

| Mode | Behavior |
|---|---|
| `details_collapsible` | Native HTML `<details>` — collapsed, no JavaScript needed |
| `open_always` | Block always visible |
| `store_only_no_frontend` | Store in DB only, no frontend output (e.g. for FAQPage schema) |

All labels (title, summary, key points, FAQ), the accent color, color scheme (auto/light/dark), and custom CSS are configurable via the admin page — no coding required.

**Per-post prompt add-on:** Authors can enter a custom prompt addition via a meta box in the post editor that is appended to the base prompt. Can be enabled/disabled globally.

---

### Schema.org Enhancer

Outputs JSON-LD structured data and meta tags in `<head>`. Settings under **Bavarian Rank → Schema.org**. Each type is individually toggleable:

| Type | Schema.org Type | Notes |
|---|---|---|
| `organization` | `Organization` | Name, URL, logo, `sameAs` links (social profiles) |
| `author` | `Person` | Author name, profile URL, optional Twitter `sameAs` |
| `speakable` | `WebPage` + `SpeakableSpecification` | CSS selectors on H1 and first paragraph |
| `article_about` | `Article` | Headline, publish/modified, description, publisher |
| `breadcrumb` | `BreadcrumbList` | Automatically suppressed when Rank Math or Yoast is active |
| `ai_meta_tags` | — | `<meta name="robots">` + `<meta name="googlebot">` with `max-snippet:-1` |
| `faq_schema` | `FAQPage` | Automatically populated from GEO block data |
| `blog_posting` | `BlogPosting` / `Article` | With embedded `author` and featured image |
| `image_object` | `ImageObject` | Featured image with dimensions and caption |
| `video_object` | `VideoObject` | YouTube/Vimeo automatically detected and embedded |
| `howto` | `HowTo` | Step-by-step guide — data via meta box in post editor |
| `review` | `Review` | Rating with `ratingValue` — data via meta box |
| `recipe` | `Recipe` | Ingredients, times, nutritional values — data via meta box |
| `event` | `Event` | Date, location, organizer — data via meta box |

---

### llms.txt

Serves `/llms.txt` and paginated follow-up files (`/llms-2.txt`, `/llms-3.txt` …) via a `parse_request` hook at priority 1 — before WordPress routing, no rewrite rule flush needed.

**File structure:**
```
# Site Title
> Description block

## Featured Links
- [Title](URL): Description

## Content
- [Title](URL): Date

---
## More
/llms-2.txt
```

**HTTP caching:**
- `ETag: "md5(content)"` → HTTP 304 on `If-None-Match`
- `Last-Modified` based on the most recent `post_modified_gmt` in the database
- `Cache-Control: public, max-age=3600`
- Transient cache is automatically invalidated on every settings change

**Rank Math conflict notice:** If Rank Math also wants to serve an llms.txt, BRE shows an admin notice — BRE takes precedence automatically due to priority 1.

---

### robots.txt Manager

Appends `Disallow` blocks via the WordPress filter `robots_txt` — WordPress's own robots.txt is preserved; BRE only extends it.

Supported AI bots (all individually toggleable):

| User-Agent | Operator |
|---|---|
| `GPTBot` | OpenAI |
| `ClaudeBot` | Anthropic |
| `Google-Extended` | Google (Bard/Gemini training) |
| `PerplexityBot` | Perplexity AI |
| `CCBot` | Common Crawl |
| `Applebot-Extended` | Apple AI |
| `Bytespider` | ByteDance |
| `DataForSeoBot` | DataForSEO |
| `ImagesiftBot` | Imagesift |
| `omgili` | Omgili |
| `Diffbot` | Diffbot |
| `FacebookBot` | Meta |
| `Amazonbot` | Amazon |

---

### Bulk Generator

Batch processing of all published posts without a meta description. The process runs as a repeated AJAX request in the browser — no WP-Cron, no CLI required.

**Technical details:**
- 1–20 posts per batch (configurable)
- 6-second delay between batches (rate limiting against API limits)
- Up to 3 attempts per post
- Live progress log and running cost estimate in the admin UI
- **Mutex lock via transient** (`bre_bulk_running`, TTL 15 minutes): prevents parallel runs across multiple browser tabs or admin users. The lock is set at start, automatically released after the last batch — or manually via button.

---

### Crawler Log

Logs visits from known AI bots in the dedicated database table `{prefix}bre_crawler_log`:

| Column | Type | Content |
|---|---|---|
| `bot_name` | VARCHAR | Name of the bot (e.g. `GPTBot`) |
| `ip_hash` | CHAR(64) | SHA-256 hash of the visitor IP |
| `url` | VARCHAR(512) | Requested URL |
| `visited_at` | DATETIME | Timestamp |

**Why SHA-256 instead of plain-text IP?** The original IP is never stored. The hash satisfies the GDPR requirement of data minimization: bot patterns are identifiable (same hash = same IP), but tracing back to a person without the plain-text value is practically impossible.

Entries older than 90 days are automatically cleaned up via weekly cron (`bre_cleanup_crawler_log`). The dashboard shows a 30-day summary per bot.

---

### Meta Editor Box

Meta box in the post editor (Classic and Block Editor):
- Source badge: `AI generated` / `Fallback` / `Manual` / `Not generated`
- Text field with `maxlength="160"` and live character counter (JavaScript, no save needed)
- "Regenerate with AI" button (only when API key is configured) — generates inline without page reload

---

### SEO Analysis Widget

Sidebar meta box in the post editor with live evaluation while writing:
- Title length (target: ≤ 60 characters)
- Word count and estimated reading time
- Heading hierarchy (H1–H6 tree)
- Counter for internal and external links
- Inline warnings: no H2, no internal link, title too long

All stats are updated live via `MutationObserver`, no manual save needed.

---

### Link Analysis (Dashboard)

AJAX widget on the plugin dashboard:
- Posts with no internal links at all
- Posts with an above-average number of external links
- Top-5 pillar pages by number of incoming internal links

Results are cached for 1 hour in the transient cache (`bre_link_analysis`).

---

## Data Storage

### WordPress Options (wp_options)

| Option Key | Content |
|---|---|
| `bre_settings` | Active provider, API keys (obfuscated), model selection, token costs, `ai_enabled` flag |
| `bre_meta_settings` | Meta generator: auto mode, post types, token mode, prompt |
| `bre_schema_settings` | Schema.org: enabled types, organization sameAs URLs |
| `bre_geo_settings` | GEO block: mode, position, labels, CSS, prompt, color scheme |
| `bre_robots_settings` | robots.txt: blocked bots |
| `bre_llms_settings` | llms.txt: title, description, featured links, footer, page count |
| `bre_usage_stats` | Accumulated token usage: `tokens_in`, `tokens_out`, `count` |
| `bre_first_activated` | Unix timestamp of first activation (used by welcome notice) |

### Post Meta (wp_postmeta)

| Meta Key | Content |
|---|---|
| `_bre_meta_description` | Generated meta description |
| `_bre_meta_source` | Source: `ai` / `fallback` / `manual` |
| `_bre_bulk_failed` | Last error during bulk attempt |
| `_bre_geo_summary` | GEO block summary |
| `_bre_geo_bullets` | GEO block key points (JSON array) |
| `_bre_geo_faq` | GEO block FAQ (JSON array) |

### Custom Database Table

| Table | Purpose |
|---|---|
| `{prefix}bre_crawler_log` | AI bot visits (bot_name, ip_hash, url, visited_at) |

### Transients

| Transient | TTL | Purpose |
|---|---|---|
| `bre_llms_cache_{n}` | 1 hour | Cached llms.txt content per page |
| `bre_link_analysis` | 1 hour | Dashboard link analysis result |
| `bre_bulk_running` | 15 minutes | Mutex lock for bulk generator |
| `bre_meta_stats` | 5 minutes | Dashboard meta coverage query result |
| `bre_crawler_summary` | 5 minutes | Dashboard crawler summary (last 30 days) |

### Uninstall cleanup

`uninstall.php` removes on plugin deletion:
- Option `bre_settings`
- Post meta `_bre_meta_description` for all posts

> Note: The remaining option keys and the `bre_crawler_log` table are not automatically removed. For full cleanup, delete these manually.

---

## Security

### API Key Obfuscation (KeyVault)

```
Plaintext key  →  XOR(key, sha256(AUTH_KEY . SECURE_AUTH_KEY))  →  base64  →  "bre1:<base64>"
```

`BavarianRankEngine\Helpers\KeyVault` obfuscates API keys before writing to `wp_options`:

1. A 64-byte salt is derived from the WordPress constants `AUTH_KEY` and `SECURE_AUTH_KEY` via `hash('sha256', ...)`.
2. The plaintext is XOR'd byte-by-byte (salt is repeated as needed).
3. The result is base64-encoded and prefixed with `bre1:`.

**Why XOR and not AES?** No `openssl_*` or external extension required — the code runs on any PHP 8.0+ installation without configuration. The `bre1:` prefix allows future migration to stronger encryption without a breaking change.

**Security boundary:** XOR with a static salt is obfuscation, not cryptographic encryption. An attacker with access to **both** the database **and** `wp-config.php` can reconstruct the key. For maximum security, keys can be defined as `wp-config.php` constants — these take precedence over the database version:

```php
define( 'BRE_OPENAI_KEY',    'sk-...' );
define( 'BRE_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'BRE_GEMINI_KEY',    'AI...' );
define( 'BRE_GROK_KEY',      'xai-...' );
```

In the admin UI, keys are always displayed masked: `••••••Ab3c9` (only the last 5 characters visible).

### CSRF Protection and Capability Checks

Every AJAX handler follows the same pattern — without exception:

```php
check_ajax_referer( 'bre_admin', 'nonce' );          // CSRF
if ( ! current_user_can( 'manage_options' ) ) {      // Authorization
    wp_send_json_error( 'Unauthorized', 403 );
}
```

The nonce `bre_admin` is passed to the frontend via `wp_localize_script` and validated server-side on every request. There are no `wp_ajax_nopriv_` handlers — all AJAX endpoints are exclusively accessible to logged-in users with `manage_options` capability.

### Input Validation and Output Escaping

- All `$_POST` values are processed via `wp_unslash()` + specific sanitizers (`sanitize_text_field`, `absint`, `wp_kses_post` depending on context).
- All output in admin views is escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`).
- SQL queries exclusively via `$wpdb->prepare()`.

### Privacy (GDPR)

The crawler log stores IP addresses exclusively as SHA-256 hashes. The original value is never persisted. Entries are automatically deleted after 90 days.

---

## AI Providers

BRE supports four providers, all implementing the same `ProviderInterface`:

| Provider | Class | API Base URL |
|---|---|---|
| OpenAI | `OpenAIProvider` | `https://api.openai.com/v1/chat/completions` |
| Anthropic | `AnthropicProvider` | `https://api.anthropic.com/v1/messages` |
| Google Gemini | `GeminiProvider` | `https://generativelanguage.googleapis.com/...` |
| xAI Grok | `GrokProvider` | `https://api.x.ai/v1/chat/completions` |

### Adding a New Provider

```php
// includes/Providers/YourProvider.php
namespace BavarianRankEngine\Providers;

class YourProvider implements ProviderInterface {
    public function getId(): string    { return 'yourprovider'; }
    public function getName(): string  { return 'Your Provider'; }
    public function getModels(): array { return [ 'model-id' => 'Model Name' ]; }

    public function testConnection( string $api_key ): array {
        // Minimal API call — returns ['success' => bool, 'message' => string]
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        // Call API, return plain text — throw \RuntimeException on error
    }
}
```

Register in `includes/Core.php` → `register_hooks()`:

```php
$registry->register( new Providers\YourProvider() );
```

The provider automatically appears in all admin dropdowns, the provider settings page, and the cost overview of the bulk generator.

---

## Hooks & Extensibility

### `bre_prompt` (Filter)

Allows modifying the final prompt immediately before the API call.

```php
add_filter( 'bre_prompt', function( string $prompt, WP_Post $post ): string {
    $keyword = get_post_meta( $post->ID, 'focus_keyword', true );
    return $keyword ? $prompt . "\nFocus keyword: {$keyword}" : $prompt;
}, 10, 2 );
```

### `bre_meta_saved` (Action)

Fired after a meta description is successfully saved — both on automatic generation at publish and on manual regen in the editor.

```php
add_action( 'bre_meta_saved', function( int $post_id, string $description ): void {
    // e.g. sync with external system or cache invalidation
    my_cdn_purge( get_permalink( $post_id ) );
}, 10, 2 );
```

### Adding a New Feature

1. Create `includes/Features/YourFeature.php` with a `register()` method that registers WordPress hooks.
2. In `includes/Core.php` → `load_dependencies()`: `require_once BRE_DIR . 'includes/Features/YourFeature.php';`
3. In `includes/Core.php` → `register_hooks()`: `( new Features\YourFeature() )->register();`

---

## AJAX Endpoints

All endpoints are exclusively accessible to logged-in users with `manage_options` (no `nopriv`).

| Action | Handler | Description |
|---|---|---|
| `bre_regen_meta` | `MetaEditorBox::ajax_regen` | Regenerate meta description for a single post |
| `bre_test_connection` | `ProviderPage::ajax_test_connection` | Test API key and connection |
| `bre_get_default_prompt` | `ProviderPage::ajax_get_default_prompt` | Reset to default prompt |
| `bre_link_analysis` | `LinkAnalysis::ajax_analyse` | Run link analysis for the dashboard |
| `bre_link_suggestions` | `LinkSuggest::ajax_suggest` | Return top-10 internal link suggestions for current post |
| `bre_geo_generate` | `GeoEditorBox::ajax_generate` | Generate GEO block for a single post |
| `bre_geo_clear` | `GeoEditorBox::ajax_clear` | Clear GEO block data for a single post |
| `bre_llms_clear_cache` | `TxtPage::ajax_clear_cache` | Clear llms.txt transient cache |
| `bre_dismiss_llms_notice` | `LlmsTxt::ajax_dismiss_notice` | Dismiss Rank Math conflict admin notice |
| `bre_dismiss_welcome` | `AdminMenu::ajax_dismiss_welcome` | Dismiss the welcome notice per user |
| `bre_bulk_generate` | `MetaGenerator::ajaxBulkGenerate` | Process next batch in bulk generator |
| `bre_bulk_stats` | `MetaGenerator::ajaxBulkStats` | Retrieve progress and stats of running bulk |
| `bre_bulk_release` | `MetaGenerator::ajaxBulkRelease` | Manually release bulk mutex lock |
| `bre_bulk_status` | `MetaGenerator::ajaxBulkStatus` | Check bulk lock status |

---

## Installation

**Via GitHub Release (recommended):**
1. Download `bavarian-rank-engine.zip` from the [latest release](https://github.com/noschmarrn/bavarianrankengine/releases/latest)
2. In WordPress go to *Plugins → Add New → Upload Plugin*

**Manual (clone):**
```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/noschmarrn/bavarianrankengine.git bavarian-rank-engine
wp plugin activate bavarian-rank-engine
```

**After activation:**
1. Go to *Bavarian Rank → AI Provider*, select your provider and enter your API key
2. Run the connection test
3. Go to *Meta Generator*, enable auto mode and select post types

The plugin has no JavaScript build step. All assets under `assets/` are direct JS/CSS files without transpiling or bundling.

---

## Tech Stack

| Component | Technology |
|---|---|
| Backend | PHP 8.0+, WordPress Plugin API |
| Namespace | `BavarianRankEngine\` |
| Architecture | Singleton core, registry pattern (providers), feature classes with `register()` |
| Database | WordPress Options API, `wpdb` (custom table for CrawlerLog) |
| Caching | WordPress transients (llms.txt, link analysis, bulk lock) |
| Frontend | Vanilla JS + jQuery (WordPress-bundled), no build step |
| i18n | `.pot` file, text domain `bavarian-rank-engine` |
| Tests | PHPUnit (102 tests, 216 assertions) |
| Coding standard | WordPress PHPCS |
| License | GPL-2.0-or-later |

---

## License

GPL-2.0-or-later — [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Copyright (c) 2025–2026 [Donau2Space](https://donau2space.de)
