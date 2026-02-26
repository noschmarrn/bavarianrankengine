# Bavarian Rank Engine

**Version 1.2.2** — AI-powered meta descriptions, GEO structured data, llms.txt, and crawler management for WordPress.

Developed by [noschmarrn](https://github.com/noschmarrn) · [Plugin website](https://bavarianrankengine.com)

---

## Features

### AI Meta Generator
Automatically generates SEO-optimized meta descriptions using your chosen AI provider when a post is published. Supports a fully customizable prompt with `{title}`, `{content}`, `{excerpt}`, and `{language}` placeholders. Language is auto-detected from Polylang, WPML, or the WordPress locale. If no API key is configured or the AI call fails, a clean 150–160 character excerpt is extracted from post content as a fallback.

Meta descriptions are written both to BRE's own `_bre_meta_description` post meta key and to the active SEO plugin's native field (Rank Math, Yoast SEO, AIOSEO, SEOPress). Existing descriptions from any of these plugins are detected and skipped, so the generator never overwrites human-written copy.

### Bulk Generator
Batch-processes all published posts that have no meta description yet. Runs in the browser via repeated AJAX calls with a configurable batch size (1–20 posts per request) and a fixed 6-second inter-batch delay for rate limiting. A transient-based lock (`bre_bulk_running`, TTL 15 minutes) prevents concurrent runs. Each post is attempted up to three times before being marked as failed. Live progress, per-post results, and a running cost estimate are shown in the admin UI.

### Schema.org Enhancer (GEO)
Injects JSON-LD structured data and meta tags into `wp_head`. Individually toggleable types:

| Type | Description |
|---|---|
| `organization` | Site name, URL, logo, and optional `sameAs` social links |
| `article_about` | Article schema with headline, dates, description, and publisher |
| `author` | Person schema with author name, URL, and optional Twitter `sameAs` |
| `speakable` | SpeakableSpecification pointing at `h1` and first paragraph selectors |
| `breadcrumb` | BreadcrumbList (skipped when Rank Math or Yoast is active to avoid duplicates) |
| `ai_meta_tags` | `<meta name="robots">` and `<meta name="googlebot">` tags with `max-snippet:-1, max-image-preview:large` directives |

The standalone `<meta name="description">` output is suppressed when Rank Math, Yoast, or AIOSEO is active.

### llms.txt
Serves a machine-readable index of published content at `/llms.txt` (and paginated `/llms-2.txt`, `/llms-3.txt`, ...) following the emerging llms.txt convention for AI training and retrieval systems. Features:

- Configurable title, description blocks (before, after, footer), and custom featured links section
- Selectable post types
- Configurable maximum links per page (minimum 50, default 500)
- Transient-based caching with manual cache-clear button in the admin
- HTTP caching headers: `ETag`, `Last-Modified`, `Cache-Control: public, max-age=3600`
- HTTP 304 Not Modified responses when the ETag matches
- Admin notice when Rank Math is also active (BRE takes priority via `parse_request` at priority 1)

### robots.txt Manager
Appends `User-agent` / `Disallow: /` blocks to WordPress's virtual `robots.txt` via the `robots_txt` filter. Supports 13 known AI and data-harvesting crawlers:

GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, Applebot-Extended, Bytespider, DataForSeoBot, ImagesiftBot, omgili, Diffbot, FacebookBot, Amazonbot.

Each bot can be individually enabled or disabled in the admin UI.

### Crawler Log
Logs visits from known AI bots to a dedicated database table (`{prefix}bre_crawler_log`). Stores bot name, SHA-256 hash of the visitor IP (privacy-safe), requested URL (truncated to 512 characters), and timestamp. Entries older than 90 days are purged automatically via a weekly WP-Cron job. The dashboard shows a 30-day summary per bot.

### Meta Editor Box
Adds a "Meta Description (BRE)" meta box to the post editor for every configured post type. Displays the current description (with a source badge: AI / Fallback / Manual / Not generated yet), a character counter targeting 160 characters, and a "Regenerate with AI" button that calls the API inline without leaving the editor.

### SEO Analysis Widget
A sidebar meta box on the post editor showing live content stats: title character count (target 60), word count, estimated reading time, heading structure, and internal/external link counts. Also displays inline warnings (e.g. missing H2, no internal links). Stats update in real time as content changes.

### Link Analysis (Dashboard)
An AJAX-loaded dashboard panel that identifies posts without any internal links, posts with an unusually high number of external links (configurable threshold), and the top pillar pages by inbound internal link count. Results are cached for one hour.

### Multi-Provider AI Backend
Four providers are registered out of the box:

| Provider | Class |
|---|---|
| OpenAI | `OpenAIProvider` |
| Anthropic (Claude) | `AnthropicProvider` |
| Google Gemini | `GeminiProvider` |
| xAI Grok | `GrokProvider` |

The active provider and model are selected per-site. API keys can also be set via `wp-config.php` constants (see API Key Security below).

---

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- At least one AI provider API key (optional — fallback meta extraction works without one)

---

## Installation

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. Go to **Bavarian Rank → AI Provider**.
4. Select your provider, enter the API key, pick a model, and click **Test connection**.
5. Go to **Bavarian Rank → Meta Generator** to configure post types, token limits, and the prompt.
6. Optionally enable Schema.org types on the same page.
7. To serve `llms.txt`, go to **Bavarian Rank → llms.txt**, enable it, and save.

On first activation the plugin creates the `{prefix}bre_crawler_log` table and registers the `llms.txt` rewrite rule (followed by a `flush_rewrite_rules()` call).

---

## Admin Menu Structure

The plugin registers a top-level menu **Bavarian Rank** (slug `bavarian-rank`) with the following sub-pages:

| Sub-page | Slug | Class | Purpose |
|---|---|---|---|
| Dashboard | `bavarian-rank` | `AdminMenu` | Overview: active provider, meta coverage stats per post type, crawler log summary, link analysis, token/cost usage |
| AI Provider | `bre-provider` | `ProviderPage` | Select provider, enter/test API key, choose model, set optional token costs, enable/disable AI |
| Meta Generator | `bre-meta` | `MetaPage` | Toggle auto-generation, select post types, set token limit, edit prompt |
| Schema.org | `bre-schema` | `SchemaPage` | Toggle and configure JSON-LD structured data types |
| llms.txt | `bre-llms` | `LlmsPage` | Enable/configure llms.txt, set post types, max links, custom sections |
| Bulk Generator | `bre-bulk` | `BulkPage` | Batch-generate meta for all posts without descriptions |
| robots.txt | `bre-robots` | `RobotsPage` | Select which AI bots to block in robots.txt |
| Settings | `bre-settings` | `SettingsPage` | Global plugin settings |

All pages require the `manage_options` capability.

---

## API Key Security (KeyVault)

API keys are obfuscated before being written to the WordPress options table using `BavarianRankEngine\Helpers\KeyVault`.

**How it works:**

1. A 64-character hex salt is derived from the WordPress `AUTH_KEY` and `SECURE_AUTH_KEY` constants via `hash('sha256', AUTH_KEY . SECURE_AUTH_KEY)`.
2. The plaintext key is XOR-encrypted byte-by-byte with the salt (wrapping when the salt is shorter than the key).
3. The result is base64-encoded and stored with a `bre1:` prefix.

Stored format: `bre1:<base64(xor(plaintext, salt))>`

No OpenSSL or any PHP extension beyond the standard library is required.

**Limitations:** XOR with a static derived key is obfuscation, not cryptographic encryption. It prevents API keys from appearing in plain text in database dumps and export files, but does not protect against an attacker who has access to both the database and `wp-config.php`. For stronger protection, define the API key directly in `wp-config.php`:

```php
define( 'BRE_OPENAI_KEY',    'sk-...' );
define( 'BRE_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'BRE_GEMINI_KEY',    'AI...' );
define( 'BRE_GROK_KEY',      'xai-...' );
```

When a constant is defined and the database field is left empty, the constant value is used automatically.

The admin UI always displays keys masked: `••••••Ab3c9` (last 5 characters visible).

---

## Extending the Plugin

### Adding a New AI Provider

Create `includes/Providers/YourProvider.php` implementing `ProviderInterface`:

```php
<?php
namespace BavarianRankEngine\Providers;

class YourProvider implements ProviderInterface {

    public function getId(): string {
        return 'yourprovider';
    }

    public function getName(): string {
        return 'Your Provider Name';
    }

    public function getModels(): array {
        return [
            'model-v1'      => 'Model V1 (Smart)',
            'model-v1-mini' => 'Model V1 Mini (Fast)',
        ];
    }

    public function testConnection( string $api_key ): array {
        // Make a minimal, low-cost API call to verify the key.
        // Return ['success' => true, 'message' => 'Connected to ...']
        // or     ['success' => false, 'message' => 'Error: ...']
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        // Call your API endpoint.
        // Return the generated text string on success.
        // Throw \RuntimeException on API or HTTP error.
    }
}
```

Then register the provider in `includes/Core.php` inside `register_hooks()`:

```php
$registry->register( new Providers\YourProvider() );
```

The new provider appears automatically in all admin dropdowns (AI Provider page, Bulk Generator) without any further changes.

### Adding a New Feature

1. Create `includes/Features/YourFeature.php` with a public `register()` method that attaches WordPress hooks.
2. Add `require_once BRE_DIR . 'includes/Features/YourFeature.php';` in `Core::load_dependencies()`.
3. Add `( new Features\YourFeature() )->register();` in `Core::register_hooks()`.

### Available Hooks

**`bre_prompt` (filter)**

Fired inside `MetaGenerator::buildPrompt()` after all placeholder substitutions. Use it to append keywords, change the language instruction, or inject dynamic context.

```php
add_filter( 'bre_prompt', function( string $prompt, \WP_Post $post ): string {
    $keyword = get_post_meta( $post->ID, 'focus_keyword', true );
    if ( $keyword ) {
        $prompt .= "\nFokus-Keyword: " . $keyword;
    }
    return $prompt;
}, 10, 2 );
```

**`bre_meta_saved` (action)**

Fired at the end of `MetaGenerator::saveMeta()` after the description has been written to all relevant post meta keys. Use it to sync descriptions to external systems, send notifications, or log results.

```php
add_action( 'bre_meta_saved', function( int $post_id, string $description ): void {
    // $description is the sanitized, saved meta description
    my_sync_function( $post_id, $description );
}, 10, 2 );
```

---

## Option Keys

| Option key | Content |
|---|---|
| `bre_settings` | Provider ID, encrypted API keys, selected models, token costs |
| `bre_meta_settings` | Auto-generate toggle, post types, token mode/limit, prompt, Schema.org config |
| `bre_llms_settings` | llms.txt enable flag, title, description blocks, post types, max links |
| `bre_robots_settings` | Array of blocked bot user-agent strings |

Post-level meta keys written by the plugin:

| Meta key | Content |
|---|---|
| `_bre_meta_description` | The generated or manually entered meta description |
| `_bre_meta_source` | Source tag: `ai`, `fallback`, or `manual` |
| `_bre_bulk_failed` | Last error message if bulk generation failed for this post |

---

## Development

```bash
# Install dev dependencies (PHPUnit, etc.)
php composer.phar install

# Run the test suite
php composer.phar exec phpunit

# WordPress Coding Standards check
php composer.phar exec phpcs -- --standard=WordPress includes/
```

The plugin has no JavaScript build step. Assets in `assets/` are plain JavaScript files loaded conditionally per admin page.

---

## Changelog

### 1.2.2 (2026-02)

- **Dashboard UX** — Progress bars for meta coverage, styled quick links, AI-crawler dot indicators
- **Welcome Notice** — Dismissible Bavarian-flavored notice with 24 h auto-expiry (per-user meta)
- **Status Widget** — Estimated token usage and USD cost in the provider status widget
- **AI Enable Toggle** — Checkbox + cost warning on the provider page; AI can be disabled without deleting the API key
- **Token Usage Tracking** — `MetaGenerator::record_usage()` accumulates stats in `bre_usage_stats`
- **Transient Caching** — Dashboard DB queries cached for 5 minutes via `bre_meta_stats` + `bre_crawler_summary`
- **i18n** — All previously hard-coded German strings in `admin.js` moved to `breAdmin.*` localisation
- **de_DE Translation** — 14 new strings added to `bavarian-rank-engine-de_DE.po/.mo`
- **82 tests, 160 assertions** — all green

### 1.2.1 (2026-02)

- **Schema.org sub-page** — Dedicated admin page (`SchemaPage`) with its own option key `bre_schema_settings`; backward compatible with existing `bre_meta_settings` values
- **Admin menu** — New "Schema.org" submenu entry after Meta Generator
- **Settings consolidation** — `SettingsPage::getSettings()` merges all three option keys
- **80 tests, 154 assertions** — all green

### 1.0.0 (2025)

- Initial release
- AI Meta Generator: auto-generate on publish, custom prompt with `{title}`, `{content}`, `{excerpt}`, `{language}` placeholders, Polylang/WPML language detection
- Bulk Generator: batched AJAX processing, rate limiting (6 s delay), transient lock, up to 3 retries per post, live progress log, cost estimation
- Schema.org Enhancer: Organization, Article, Author, Speakable, BreadcrumbList JSON-LD; AI meta tags; standalone meta description output
- llms.txt: paginated, ETag/Last-Modified caching, custom sections, manual cache clear
- robots.txt Manager: 13 known AI bot user-agents individually configurable
- Crawler Log: database table, SHA-256 IP hashing, weekly purge cron, dashboard summary
- Meta Editor Box: inline source badge, character counter, single-post AI regeneration button
- SEO Analysis Widget: live word count, reading time, heading structure, link counts, inline warnings
- Link Analysis: posts without internal links, external link outliers, top pillar pages (1-hour cache)
- KeyVault: XOR obfuscation of stored API keys using WP salts, no OpenSSL dependency
- FallbackMeta: sentence-boundary-aware 150–160 character excerpt extraction
- Multi-provider: OpenAI, Anthropic, Google Gemini, xAI Grok
- Compatible with Rank Math, Yoast SEO, AIOSEO, SEOPress, or no SEO plugin
