# SEO & GEO Tools

A WordPress plugin that extends any site with AI-powered meta descriptions and GEO-optimized structured data — helping blogs get cited by ChatGPT, Claude, Grok, and Gemini.

**Developed by [Donau2Space.de](https://donau2space.de)**

---

## Features

- **AI Meta Generator** — Automatically writes SEO-optimized, German-first meta descriptions on publish
- **Bulk Generator** — Process all existing posts without meta descriptions, with live progress log and cost estimate
- **Schema.org Enhancer** — GEO-optimized structured data: Organization, Author, Speakable, Article, BreadcrumbList
- **Multi-Provider** — OpenAI, Anthropic (Claude), Google Gemini, xAI Grok — switch anytime
- **Model Picker** — Choose the exact model per provider (e.g. GPT-4.1 vs GPT-4o-mini)
- **Custom Prompt** — Fully editable prompt with `{title}`, `{content}`, `{excerpt}`, `{language}` variables
- **Standalone** — Works with Rank Math, Yoast, AIOSEO, SEOPress, or no SEO plugin

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- At least one AI provider API key

---

## Installation

1. Upload the `seo-geo` folder to `wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Go to **Einstellungen → SEO & GEO Tools**
4. Select your AI provider, enter your API key, click **Verbindung testen**
5. Configure which post types to auto-generate for
6. Enable the Schema.org types you want active

---

## Settings

| Location | Purpose |
|---|---|
| `Einstellungen → SEO & GEO Tools` | Provider, API key, model, prompt, schema types |
| `Tools → GEO Bulk Meta` | Bulk generate meta for existing posts |

---

## Extending the Plugin

### Adding a New AI Provider

1. Create `includes/Providers/YourProvider.php`:

```php
<?php
namespace SeoGeo\Providers;

class YourProvider implements ProviderInterface {
    public function getId(): string    { return 'yourprovider'; }
    public function getName(): string  { return 'Your Provider'; }
    public function getModels(): array { return [ 'model-id' => 'Model Name' ]; }

    public function testConnection( string $api_key ): array {
        // Make a minimal API call, return ['success' => bool, 'message' => string]
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        // Call your API, return the generated text string
        // Throw \RuntimeException on error
    }
}
```

2. Register it in `includes/Core.php` → `register_hooks()`:

```php
$registry->register( new Providers\YourProvider() );
```

That's it — the provider appears automatically in all dropdowns.

---

### Adding a New Feature

1. Create `includes/Features/YourFeature.php` with a `register()` method
2. Add `require_once SEO_GEO_DIR . 'includes/Features/YourFeature.php';` in `Core::load_dependencies()`
3. Add `( new Features\YourFeature() )->register();` in `Core::register_hooks()`

---

### Available WordPress Hooks

```php
// Filter the prompt before it is sent to the AI provider
// Use to add custom context, inject keywords, or change format
add_filter( 'seo_geo_prompt', function( string $prompt, \WP_Post $post ): string {
    return $prompt . "\nFokus-Keyword: " . get_post_meta( $post->ID, 'focus_keyword', true );
}, 10, 2 );

// Action fired after a meta description is saved
// Use to sync to other systems, send notifications, log results
add_action( 'seo_geo_meta_saved', function( int $post_id, string $description ): void {
    // Your code here
}, 10, 2 );
```

---

## Development

```bash
# Install dev dependencies (PHPUnit)
composer install

# Run tests
php vendor/bin/phpunit --testdox
```

---

## Changelog

### 1.0.0
- Initial release
- OpenAI, Anthropic, Gemini, Grok providers
- AI Meta Generator with auto-publish and bulk mode
- Schema.org Enhancer (Organization, Author, Speakable, Article, Breadcrumb)
- Customizable German-first prompt system
- Cost estimation before bulk runs
