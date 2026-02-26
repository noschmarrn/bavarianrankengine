=== Bavarian Rank Engine ===
Contributors: mifupadev
Tags: seo, ai, meta description, schema, llms.txt
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.2.2
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI meta descriptions, Schema.org structured data, llms.txt, and AI-crawler management for WordPress. No subscription.

== Description ==

**Bavarian Rank Engine** is a WordPress plugin for SEO and GEO (Generative Engine Optimization). It generates meta descriptions with AI, adds Schema.org structured data, serves a machine-readable content index, and lets you control which AI bots are allowed on your site — all without a subscription.

**At a glance:**

* Generates meta descriptions automatically on publish (OpenAI, Anthropic, Google Gemini, xAI Grok)
* Bulk-generates descriptions for existing posts that have none
* Adds Schema.org JSON-LD to help search engines and AI systems understand your content
* Serves `/llms.txt` — a machine-readable content index for AI discovery
* Manages AI crawler access per-bot via the robots.txt manager, directly from the admin
* Logs AI bot visits with hashed IPs — no plain text stored
* Free. No subscription. API costs go directly to your provider.

= AI Meta Generator =

Generates a 150–160 character meta description the moment a post is published. The prompt is fully customizable using `{title}`, `{content}`, `{excerpt}`, and `{language}` placeholders. Language is detected automatically from Polylang, WPML, or the WordPress site locale.

If no API key is configured or the AI request fails, a clean fallback excerpt is extracted from the post content — no description is left empty.

= Bulk Generator =

Finds all published posts without a meta description (including descriptions set by Rank Math, Yoast, AIOSEO, or SEOPress) and generates them in configurable batches with rate-limiting between batches. A live progress log and per-batch cost estimate are shown during the run.

= Multi-Provider AI Support =

Choose from four AI providers and switch at any time without losing your settings:

* OpenAI (GPT-4.1, GPT-4o, GPT-4o mini, and more)
* Anthropic Claude (Claude 3.5 Sonnet, Claude 3 Haiku, and more)
* Google Gemini (Gemini 2.0 Flash, Gemini 1.5 Pro, and more)
* xAI Grok (Grok 3, Grok 3 mini, and more)

= Schema.org Enhancer (GEO) =

Injects JSON-LD structured data for search engines and AI retrieval systems:

* Organization — site name, URL, logo, and social `sameAs` links
* Article — headline, dates, description, and publisher
* Author — person name, author URL, Twitter link
* Speakable — marks up your H1 and first paragraph for voice and AI assistants
* BreadcrumbList — skipped automatically when Rank Math or Yoast is active
* AI Meta Tags — `max-snippet:-1, max-image-preview:large, max-video-preview:-1` directives

= llms.txt =

Serves a machine-readable index of your published content at `/llms.txt`, following the emerging llms.txt convention increasingly supported by AI indexing tools. Supports custom title, description sections, featured resource links, pagination for large sites, and HTTP ETag / Last-Modified caching.

= robots.txt Manager =

Block individual AI training and data-harvesting bots directly from the WordPress admin — no manual file editing. Supports 13 known bots: GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, Applebot-Extended, Bytespider, DataForSeoBot, ImagesiftBot, Omgili, Diffbot, FacebookBot, and Amazonbot.

= Crawler Log =

Automatically logs visits from known AI bots. Stores the bot name, a SHA-256-hashed IP address, and the requested URL. Entries older than 90 days are purged automatically. A 30-day summary is shown on the plugin dashboard.

= Post Editor Integration =

A "Meta Description" meta box in the post and page editor shows the current description, its source (AI / Fallback / Manual), a live character counter, and a "Regenerate with AI" button. A sidebar SEO widget displays word count, reading time, heading structure, and link counts with live warnings.

= Link Analysis Dashboard =

Identifies posts without internal links, posts with an unusually high external-link count, and your top pillar pages by inbound internal link count — loaded on demand with a one-hour cache.

= Secure API Key Storage =

API keys are obfuscated using XOR with a key derived from your WordPress authentication salts before being written to the database. Keys never appear in plain text in database dumps or export files. No OpenSSL extension required.

= Compatibility =

Works standalone or alongside any major SEO plugin. When Rank Math, Yoast SEO, AIOSEO, or SEOPress is active, generated descriptions are written to that plugin's own meta field. Existing descriptions set by those plugins are always respected and never overwritten.

== Installation ==

1. Download the plugin zip and go to **Plugins → Add New → Upload Plugin** in your WordPress admin.
2. Upload the zip file and click **Install Now**, then **Activate**.
3. Go to **Bavarian Rank → AI Provider**.
4. Select your preferred AI provider, paste your API key, and click **Test connection**.
5. Choose a model and optionally enter token costs for cost estimation.
6. Go to **Bavarian Rank → Meta Generator** to select post types and configure Schema.org types.
7. To serve a content index, go to **Bavarian Rank → llms.txt**, enable it, and save.
8. To manage AI crawler access, go to **Bavarian Rank → robots.txt** and select the bots to block.

The plugin works without an API key — fallback meta extraction runs automatically on publish.

== Frequently Asked Questions ==

= Do I need an API key? =

An API key is required for AI-generated meta descriptions. Without one, the plugin automatically falls back to extracting a clean 150–160 character excerpt from the post content. All other features (Schema.org, llms.txt, robots.txt manager, crawler log) work without an API key.

= How much does it cost to generate meta descriptions? =

Cost depends on the AI provider and model you choose. A single meta description typically uses fewer than 1,500 tokens (input + output combined). As a rough reference, 1,000 descriptions with GPT-4o mini has cost around $0.50–$1.00 at recent rates — but AI provider pricing changes over time. The AI Provider settings page links directly to the current pricing page for each supported provider.

= Are my API keys stored securely? =

Keys are obfuscated using XOR encryption with a key derived from your WordPress authentication salts before being written to the database. They do not appear in plain text in database dumps or export files. For the highest level of protection, define your API keys as constants in `wp-config.php` — the plugin will use them automatically and nothing is stored in the database.

= What is llms.txt? =

`llms.txt` is an emerging convention (similar in spirit to `robots.txt` or `sitemap.xml`) that gives AI language models and retrieval-augmented generation (RAG) tools a structured, machine-readable index of a site's content. Support varies by tool and is still evolving. The plugin serves it at `yourdomain.com/llms.txt` with proper HTTP caching headers.

= Is this compatible with Rank Math / Yoast SEO / AIOSEO / SEOPress? =

Yes. When one of these plugins is active, Bavarian Rank Engine writes generated descriptions directly into that plugin's meta field. It also checks for existing descriptions from all four plugins before generating, and skips posts that already have one. Breadcrumb and standalone meta description output is suppressed automatically to avoid conflicts.

= Does it work with Polylang or WPML? =

Yes. The meta generator detects the post language from Polylang (`pll_get_post_language()`), WPML (`ICL_LANGUAGE_CODE`), or the WordPress site locale, and includes it in the prompt so the AI writes in the correct language.

= How does the Bulk Generator handle rate limits? =

Posts are processed in configurable batches (1–20 per batch) with a 6-second pause between batches. Each post is retried up to three times with a 1-second delay between attempts. A transient-based lock prevents simultaneous runs. The lock expires automatically after 15 minutes and can also be released manually from the Bulk Generator page.

= Does the Crawler Log store personal data? =

No. IP addresses are hashed with SHA-256 before storage — the original IP is never saved. Entries are purged automatically after 90 days.

= Will it slow down my site? =

No front-end overhead beyond the inline JSON-LD and optional meta tags in `wp_head`. The llms.txt response is cached via WordPress transients and served with HTTP 304 when the ETag matches. No external HTTP requests are made during normal page loads — AI API calls only happen on post publish or when explicitly triggered from the admin.

= Can I add a custom AI provider? =

Yes. Implement the `BavarianRankEngine\Providers\ProviderInterface` interface (five methods: `getId`, `getName`, `getModels`, `testConnection`, `generateText`), place the class in `includes/Providers/`, and register it in `Core::register_hooks()`. It will appear in all admin dropdowns automatically.

== Screenshots ==

1. Dashboard — provider status, meta coverage stats, crawler log summary.
2. AI Provider settings — provider selector, API key entry, connection test, model picker, cost configuration.
3. Meta Generator settings — post type selection, token limit, prompt editor, Schema.org toggles.
4. Bulk Generator — batch controls, live progress log, cost estimate.
5. llms.txt configuration — enable toggle, custom sections, post types, pagination settings.
6. robots.txt / AI Bots — per-bot block checkboxes.
7. Post editor — Meta Description meta box with source badge and AI regeneration button.
8. Post editor — SEO Analysis sidebar widget with live stats and warnings.

== Changelog ==

= 1.2.2 =
* New: Dismissible welcome notice with 24 h auto-expiry and Bavarian flavour
* New: AI enable toggle with cost warning on AI Provider page
* New: Estimated token usage and cost in Status widget
* Improved: Dashboard UI — progress bars for meta coverage, styled quick links, crawler dot indicators
* Fix: Plugin Check warnings (variable definitions in template moved to controller)
* Fix: Hardcoded German strings in admin.js replaced with localized equivalents
* Perf: 5-minute transient caching for dashboard DB queries

= 1.2.1 =
* New: Dedicated "Schema.org" admin menu item under Bavarian Rank — schema settings moved out of Meta Generator into their own page with a separate option key

= 1.2.0 =
* New: Schema Suite v2 — FAQPage (auto-generated from GEO Quick Overview data), BlogPosting/Article (with embedded author and featured image), ImageObject, and VideoObject (YouTube/Vimeo auto-detected from post content)
* New: Post editor meta box for HowTo, Review (star rating 1–5), Recipe, and Event schema types — per-post data entry, saved as post meta, output as JSON-LD automatically

= 1.1.0 =
* New: GEO Schnellüberblick block — AI-generated per-post summary with short summary, key bullet points, and optional FAQ.
* New: Rendered as a native `<details>` element; configurable as collapsible (default), always open, or store-only (no frontend output).
* New: Three generation modes — auto on publish, hybrid (auto only when fields are empty), manual only.
* New: Configurable insertion position: after first paragraph (default), top, or bottom of content.
* New: Quality gate suppresses FAQ generation on posts below a configurable word-count threshold (default: 350).
* New: Post editor meta box with live AJAX generate/clear buttons, per-post enable toggle, and auto-lock on manual edit.
* New: Optional per-post prompt add-on field for author-level customization.
* New: Dedicated admin settings page under Bavarian Rank → GEO Block.
* New: Bundled minimal CSS scoped to `.bre-geo`; custom CSS field for theme-level overrides.

= 1.0.0 =
* Initial release.
* AI Meta Generator with auto-publish trigger, customizable prompt, and Polylang/WPML language detection.
* Fallback meta extraction (sentence-boundary-aware, 150–160 characters) for use without an API key or on API failure.
* Bulk Generator with batched AJAX processing, rate limiting, transient lock, per-post retry logic, and cost estimation.
* Schema.org Enhancer: Organization, Article, Author, Speakable, BreadcrumbList JSON-LD; AI indexing meta tags.
* Standalone meta description output with automatic suppression when Rank Math, Yoast, or AIOSEO is active.
* Native field write-through for Rank Math, Yoast SEO, AIOSEO, and SEOPress.
* llms.txt with pagination, ETag/Last-Modified HTTP caching, custom sections, and manual cache clear.
* robots.txt manager for 13 known AI and data-harvesting crawlers.
* Crawler Log database table with SHA-256 IP hashing and weekly auto-purge.
* Meta Description meta box with source badge, character counter, and inline AI regeneration.
* SEO Analysis sidebar widget with live content statistics and warnings.
* Link Analysis dashboard panel: no-internal-links report, external-link outliers, pillar page ranking.
* KeyVault API key obfuscation using XOR with WP salts (no OpenSSL dependency).
* Multi-provider support: OpenAI, Anthropic, Google Gemini, xAI Grok.
* `bre_prompt` filter and `bre_meta_saved` action hooks for developers.

== Upgrade Notice ==

= 1.1.0 =
No database changes. Deactivate and reactivate the plugin after updating to register the new GEO Block rewrite rules.

= 1.0.0 =
Initial release. No upgrade steps required.
