=== Bavarian Rank Engine ===
Contributors: donau2space
Tags: seo, ai, meta description, llms.txt, schema.org
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered meta descriptions, Schema.org structured data, llms.txt, and AI-bot management for WordPress.

== Description ==

**Bavarian Rank Engine** is an all-in-one SEO and GEO (Generative Engine Optimization) plugin for WordPress. It uses leading AI providers to write meta descriptions automatically, enriches your pages with structured data that helps AI systems understand and cite your content, and gives you full control over which AI crawlers are allowed to index your site.

= Key Features =

**AI Meta Generator**
Automatically generates a 150–160 character, SEO-optimized meta description the moment a post is published. Supports a fully customizable prompt with `{title}`, `{content}`, `{excerpt}`, and `{language}` placeholders. Language is detected automatically from Polylang, WPML, or the WordPress site locale. If no API key is available or the AI request fails, a clean fallback excerpt is extracted from the post content — so you always get a description.

**Bulk Generator**
Batch-process your entire back-catalogue. The Bulk Generator finds all published posts that still have no meta description (including descriptions set by Rank Math, Yoast, AIOSEO, or SEOPress) and generates them in configurable batches with a rate-limiting delay. A live progress log and per-batch cost estimate keep you informed throughout the run.

**Multi-Provider AI Support**
Choose from four leading AI providers and switch at any time without losing your settings:

* OpenAI (GPT-4.1, GPT-4o, GPT-4o mini, and more)
* Anthropic Claude (Claude 3.5 Sonnet, Claude 3 Haiku, and more)
* Google Gemini (Gemini 2.0 Flash, Gemini 1.5 Pro, and more)
* xAI Grok (Grok 3, Grok 3 mini, and more)

**Schema.org Enhancer (GEO)**
Injects JSON-LD structured data and meta tags optimized for both traditional search engines and AI retrieval systems:

* Organization — site name, URL, logo, and social `sameAs` links
* Article — headline, dates, description, and publisher
* Author — person name, author URL, Twitter link
* Speakable — marks up your H1 and first paragraph for voice/AI assistants
* BreadcrumbList — skipped automatically when Rank Math or Yoast is active
* AI Meta Tags — `max-snippet:-1, max-image-preview:large, max-video-preview:-1` directives

**llms.txt**
Serves a machine-readable index of your published content at `/llms.txt`, following the emerging llms.txt convention used by AI systems to discover and index web content. Supports custom title, description sections, featured resource links, pagination for large sites, and HTTP ETag / Last-Modified caching for efficient crawler access.

**robots.txt Manager**
Block individual AI training and data-harvesting bots directly from the WordPress admin — no manual robots.txt editing required. Supports GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, Applebot-Extended, Bytespider, DataForSeoBot, ImagesiftBot, Omgili, Diffbot, FacebookBot, and Amazonbot.

**Crawler Log**
Automatically logs visits from known AI bots to a private database table. Stores the bot name, a hashed (SHA-256) IP address, and the requested URL. Entries older than 90 days are purged automatically. A 30-day summary is shown on the plugin dashboard.

**Post Editor Integration**
A "Meta Description" meta box on every post and page editor shows the current description, its source (AI / Fallback / Manual), a live character counter, and a one-click "Regenerate with AI" button. A sidebar SEO widget displays word count, reading time, heading structure, and link counts with live warnings.

**Link Analysis Dashboard**
Identifies posts without internal links, posts with an unusually high number of external links, and your top pillar pages by inbound internal link count — all loaded on demand with a one-hour cache.

**Secure API Key Storage**
API keys are obfuscated before database storage using XOR with a key derived from your WordPress authentication salts. Keys never appear in plain text in database dumps or export files. No OpenSSL extension required.

= Compatibility =

Bavarian Rank Engine works standalone or alongside any major SEO plugin. When Rank Math, Yoast SEO, AIOSEO, or SEOPress is active, generated descriptions are written to that plugin's own meta field automatically. Existing descriptions set by those plugins are always respected and never overwritten.

= Screenshots =

1. Dashboard — provider status, meta coverage stats, crawler log summary
2. AI Provider page — provider selector, API key entry, connection test, model picker, cost configuration
3. Meta Generator settings — post type selection, token limit, prompt editor, Schema.org toggles
4. Bulk Generator — batch controls, live progress log, cost estimate
5. llms.txt configuration — enable toggle, custom sections, post types, pagination settings
6. robots.txt / AI Bots — per-bot block checkboxes
7. Post editor — Meta Description meta box with source badge and AI regeneration button
8. Post editor — SEO Analysis sidebar widget with live stats and warnings

== Installation ==

1. Download the plugin zip and go to **Plugins → Add New → Upload Plugin** in your WordPress admin.
2. Upload the zip file and click **Install Now**, then **Activate**.
3. Go to **Bavarian Rank → AI Provider**.
4. Select your preferred AI provider, paste your API key, and click **Test connection**.
5. Choose the model you want to use and optionally enter token costs for cost estimation.
6. Go to **Bavarian Rank → Meta Generator** to select which post types should receive auto-generated descriptions and which Schema.org types to enable.
7. To publish a machine-readable content index, go to **Bavarian Rank → llms.txt**, enable it, and save.
8. To block AI training crawlers, go to **Bavarian Rank → robots.txt** and check the bots you want to block.

No configuration is required to use the fallback meta extraction — it works without an API key.

== Frequently Asked Questions ==

= Do I need an API key to use this plugin? =

An API key is required for AI-generated meta descriptions. Without a key the plugin automatically falls back to extracting a clean 150–160 character excerpt from the post content, so you still get a usable description for every post. All other features (Schema.org, llms.txt, robots.txt manager, crawler log) work without an API key.

= How much does it cost to generate meta descriptions? =

Cost depends entirely on the AI provider and model you choose. A single meta description typically consumes fewer than 1,500 tokens (input + output combined). For example, with GPT-4o mini at $0.60 per million input tokens, generating 1,000 meta descriptions costs roughly $0.50–$1.00. The Bulk Generator shows a cost estimate before and during a batch run. Exact pricing is linked directly from the AI Provider settings page.

= Are my API keys stored securely? =

API keys are obfuscated using XOR encryption with a key derived from your WordPress authentication salts before being written to the database. This means keys do not appear in plain text in database dumps or export files. For the highest level of protection, you can define your API keys as constants in `wp-config.php` and leave the admin fields empty — the plugin will use the constants automatically.

= What is llms.txt and why does my site need it? =

`llms.txt` is an emerging open standard (similar to `robots.txt` or `sitemap.xml`) that provides AI language models and retrieval-augmented generation (RAG) systems with a structured, machine-readable index of your site's content. Having a well-formatted `llms.txt` makes it easier for AI assistants like ChatGPT, Claude, Gemini, and Perplexity to discover, understand, and accurately cite your content. The plugin serves it at `yourdomain.com/llms.txt` with proper HTTP caching headers.

= Is this plugin compatible with Rank Math / Yoast SEO / AIOSEO / SEOPress? =

Yes. When any of these plugins is detected, Bavarian Rank Engine writes the generated meta description directly into that plugin's own post meta field so the description is picked up correctly. The plugin also detects existing descriptions from all four plugins before generating, and skips posts that already have a description set by any of them. Breadcrumb and standalone meta description output is automatically suppressed to avoid conflicts.

= Can I use this plugin with Polylang or WPML? =

Yes. The meta generator detects the post language from Polylang (`pll_get_post_language()`), WPML (`ICL_LANGUAGE_CODE`), or the WordPress site locale and includes it in the prompt so the AI writes the description in the correct language.

= How does the Bulk Generator handle rate limits? =

The Bulk Generator processes posts in configurable batches (1–20 per batch) with a 6-second pause between batches. Each post is attempted up to three times with a 1-second delay between retries. A transient-based lock prevents two bulk runs from happening simultaneously (even across multiple browser tabs or users). If a run is interrupted, the lock expires automatically after 15 minutes and can also be released manually from the Bulk Generator page.

= Does the Crawler Log store personal data? =

No. The IP address of each crawler visit is hashed with SHA-256 before storage. The original IP address is never saved. The logged URL is the requested path on your site. Entries are purged automatically after 90 days.

= Will the plugin slow down my site? =

The plugin adds no overhead on the front end beyond the JSON-LD output and the optional meta tags in `wp_head` (which are lightweight inline text). The llms.txt response is fully cached via WordPress transients and served with HTTP 304 Not Modified when the ETag matches. No external HTTP requests are made during normal page loads — AI API calls only happen when a post is published or when explicitly triggered from the admin.

= Can I add a custom AI provider? =

Yes. Implement the `BavarianRankEngine\Providers\ProviderInterface` interface (four methods: `getId`, `getName`, `getModels`, `testConnection`, `generateText`), place the file in `includes/Providers/`, and register it in `Core::register_hooks()`. It will appear in all admin dropdowns automatically.

== Screenshots ==

1. Dashboard with provider status, meta coverage percentages, and crawler activity summary.
2. AI Provider settings: provider tabs, masked API key input, connection test, model selection, and cost per 1M tokens.
3. Meta Generator settings: auto-generate toggle, post type checkboxes, token limit slider, prompt textarea with variable reference, Schema.org type toggles.
4. Bulk Generator: stats per post type, batch size selector, live results log with success/failure per post, running cost display.
5. llms.txt settings: enable toggle, title field, description sections, custom featured links, post type selector, max links per page, live preview URL, cache clear button.
6. robots.txt / AI Bots: list of 13 known AI crawlers with individual block checkboxes and a preview of the rules that will be appended.
7. Post editor Meta Description meta box: source badge, editable textarea with character counter, "Regenerate with AI" button.
8. Post editor SEO Analysis widget: title length, word count, reading time, heading list, link summary, and inline warnings.

== Changelog ==

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

= 1.0.0 =
Initial release. No upgrade steps required.
