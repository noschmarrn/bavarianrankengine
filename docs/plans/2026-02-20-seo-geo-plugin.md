# SEO & GEO Tools — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a standalone WordPress plugin that adds AI-powered meta description generation and GEO-optimized Schema.org structured data to any WordPress installation.

**Architecture:** Provider-abstraction pattern — each AI provider (OpenAI, Anthropic, Gemini, Grok) is a separate class implementing `ProviderInterface`. Features (MetaGenerator, SchemaEnhancer) are decoupled from providers and use the registry. Settings stored in a single `seo_geo_settings` option. No Composer dependencies — all HTTP via `wp_remote_post()`.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, vanilla JS (AJAX), PHPUnit for pure-PHP unit tests

**Design Doc:** `docs/plans/2026-02-20-seo-geo-design.md`

---

## Prerequisites

- WordPress installed and accessible (Docker environment)
- Plugin directory: `wp-content/plugins/seo-geo/` (symlink or copy from `/var/www/dev/plugins/seo-geo/`)
- PHPUnit available: `composer require --dev phpunit/phpunit` in plugin root
- At least one AI provider API key for manual testing

---

## Task 1: Plugin Scaffold & Bootstrap

**Files:**
- Create: `seo-geo.php`
- Create: `uninstall.php`
- Create: `includes/Core.php`
- Create: `composer.json` (dev-only, for PHPUnit)

**Step 1: Create the main plugin file**

```php
<?php
/**
 * Plugin Name: SEO & GEO Tools
 * Plugin URI:  https://donau2space.de
 * Description: AI-powered meta descriptions and GEO-optimized structured data for any WordPress site.
 * Version:     1.0.0
 * Author:      Donau2Space
 * Author URI:  https://donau2space.de
 * License:     GPL-2.0-or-later
 * Text Domain: seo-geo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SEO_GEO_VERSION', '1.0.0' );
define( 'SEO_GEO_FILE',    __FILE__ );
define( 'SEO_GEO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SEO_GEO_URL',     plugin_dir_url( __FILE__ ) );

require_once SEO_GEO_DIR . 'includes/Core.php';

function seo_geo_init(): void {
    \SeoGeo\Core::instance()->init();
}
add_action( 'plugins_loaded', 'seo_geo_init' );
```

**Step 2: Create Core.php**

```php
<?php
namespace SeoGeo;

class Core {
    private static ?Core $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies(): void {
        require_once SEO_GEO_DIR . 'includes/Providers/ProviderInterface.php';
        require_once SEO_GEO_DIR . 'includes/Providers/ProviderRegistry.php';
        require_once SEO_GEO_DIR . 'includes/Providers/OpenAIProvider.php';
        require_once SEO_GEO_DIR . 'includes/Providers/AnthropicProvider.php';
        require_once SEO_GEO_DIR . 'includes/Providers/GeminiProvider.php';
        require_once SEO_GEO_DIR . 'includes/Providers/GrokProvider.php';
        require_once SEO_GEO_DIR . 'includes/Helpers/TokenEstimator.php';
        require_once SEO_GEO_DIR . 'includes/Features/MetaGenerator.php';
        require_once SEO_GEO_DIR . 'includes/Features/SchemaEnhancer.php';
        require_once SEO_GEO_DIR . 'includes/Admin/SettingsPage.php';
        require_once SEO_GEO_DIR . 'includes/Admin/BulkPage.php';
    }

    private function register_hooks(): void {
        // Register all providers
        $registry = ProviderRegistry::instance();
        $registry->register( new Providers\OpenAIProvider() );
        $registry->register( new Providers\AnthropicProvider() );
        $registry->register( new Providers\GeminiProvider() );
        $registry->register( new Providers\GrokProvider() );

        // Boot features
        ( new Features\MetaGenerator() )->register();
        ( new Features\SchemaEnhancer() )->register();

        // Boot admin
        if ( is_admin() ) {
            ( new Admin\SettingsPage() )->register();
            ( new Admin\BulkPage() )->register();
        }
    }
}
```

**Step 3: Create uninstall.php**

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
delete_option( 'seo_geo_settings' );
// Remove all post meta written by this plugin
delete_post_meta_by_key( '_seo_geo_meta_description' );
```

**Step 4: Create composer.json**

```json
{
    "name": "donau2space/seo-geo",
    "description": "SEO & GEO Tools WordPress Plugin",
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload-dev": {
        "psr-4": {
            "SeoGeo\\Tests\\": "tests/"
        }
    }
}
```

**Step 5: Create directory structure**

```bash
mkdir -p includes/Providers includes/Features includes/Admin includes/Helpers assets tests
```

**Step 6: Activate plugin in WordPress admin and verify no PHP errors**

Navigate to `Plugins → Installed Plugins` → activate `SEO & GEO Tools`.
Expected: Plugin activates without error (white screen = fatal error, check `wp-content/debug.log`).

**Step 7: Commit**

```bash
git init
git add seo-geo.php uninstall.php includes/Core.php composer.json
git commit -m "feat: plugin scaffold and bootstrap"
```

---

## Task 2: ProviderInterface & ProviderRegistry

**Files:**
- Create: `includes/Providers/ProviderInterface.php`
- Create: `includes/Providers/ProviderRegistry.php`
- Create: `tests/Providers/ProviderRegistryTest.php`

**Step 1: Create ProviderInterface**

```php
<?php
namespace SeoGeo\Providers;

interface ProviderInterface {
    /** Unique machine-readable ID, e.g. 'openai' */
    public function getId(): string;

    /** Human-readable label for dropdowns */
    public function getName(): string;

    /**
     * Available models as ['model-id' => 'Human Label']
     * Ordered from most capable to least expensive
     */
    public function getModels(): array;

    /**
     * Test API connectivity with minimal cost.
     * Returns ['success' => bool, 'message' => string]
     */
    public function testConnection( string $api_key ): array;

    /**
     * Generate text from a prompt.
     *
     * @param string $prompt    The full prompt to send
     * @param string $api_key   Provider API key
     * @param string $model     Model ID from getModels()
     * @param int    $max_tokens Maximum tokens in response (0 = provider default)
     * @return string           Generated text or empty string on failure
     * @throws \RuntimeException on API error
     */
    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string;
}
```

**Step 2: Create ProviderRegistry**

```php
<?php
namespace SeoGeo;

use SeoGeo\Providers\ProviderInterface;

class ProviderRegistry {
    private static ?ProviderRegistry $instance = null;
    private array $providers = [];

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register( ProviderInterface $provider ): void {
        $this->providers[ $provider->getId() ] = $provider;
    }

    public function get( string $id ): ?ProviderInterface {
        return $this->providers[ $id ] ?? null;
    }

    /** @return ProviderInterface[] */
    public function all(): array {
        return $this->providers;
    }

    /** Returns ['id' => 'Name'] for dropdowns */
    public function getSelectOptions(): array {
        $options = [];
        foreach ( $this->providers as $id => $provider ) {
            $options[ $id ] = $provider->getName();
        }
        return $options;
    }
}
```

**Step 3: Write failing test**

```php
<?php
// tests/Providers/ProviderRegistryTest.php
namespace SeoGeo\Tests\Providers;

use PHPUnit\Framework\TestCase;
use SeoGeo\ProviderRegistry;
use SeoGeo\Providers\ProviderInterface;

class ProviderRegistryTest extends TestCase {
    public function test_register_and_get_provider(): void {
        $registry = new ProviderRegistry();
        $mock = $this->createMock( ProviderInterface::class );
        $mock->method('getId')->willReturn('test');
        $mock->method('getName')->willReturn('Test Provider');

        // Access private providers via register method
        $registry->register( $mock );

        $this->assertSame( $mock, $registry->get('test') );
    }

    public function test_get_nonexistent_returns_null(): void {
        $registry = new ProviderRegistry();
        $this->assertNull( $registry->get('nonexistent') );
    }

    public function test_get_select_options(): void {
        $registry = new ProviderRegistry();
        $mock = $this->createMock( ProviderInterface::class );
        $mock->method('getId')->willReturn('openai');
        $mock->method('getName')->willReturn('OpenAI');
        $registry->register( $mock );

        $options = $registry->getSelectOptions();
        $this->assertArrayHasKey('openai', $options);
        $this->assertEquals('OpenAI', $options['openai']);
    }
}
```

Note: `ProviderRegistry::instance()` is a singleton for WordPress runtime. For tests, instantiate directly `new ProviderRegistry()`.

**Step 4: Run tests**

```bash
composer install
./vendor/bin/phpunit tests/Providers/ProviderRegistryTest.php --testdox
```
Expected: All 3 tests PASS.

**Step 5: Commit**

```bash
git add includes/Providers/ProviderInterface.php includes/Providers/ProviderRegistry.php tests/
git commit -m "feat: ProviderInterface and ProviderRegistry with tests"
```

---

## Task 3: OpenAI Provider

**Files:**
- Create: `includes/Providers/OpenAIProvider.php`
- Create: `tests/Providers/OpenAIProviderTest.php`

**Step 1: Create OpenAIProvider**

```php
<?php
namespace SeoGeo\Providers;

class OpenAIProvider implements ProviderInterface {
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function getId(): string { return 'openai'; }
    public function getName(): string { return 'OpenAI'; }

    public function getModels(): array {
        return [
            'gpt-4.1'        => 'GPT-4.1 (Empfohlen)',
            'gpt-4o'         => 'GPT-4o',
            'gpt-4o-mini'    => 'GPT-4o Mini (Günstig)',
            'gpt-3.5-turbo'  => 'GPT-3.5 Turbo (Sehr günstig)',
        ];
    }

    public function testConnection( string $api_key ): array {
        try {
            $this->generateText( 'Say "ok"', $api_key, 'gpt-4o-mini', 5 );
            return [ 'success' => true, 'message' => 'Verbindung erfolgreich' ];
        } catch ( \RuntimeException $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => $model,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => $max_tokens,
            ] ),
        ] );

        return $this->parseResponse( $response );
    }

    private function parseResponse( $response ): string {
        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            throw new \RuntimeException( $msg );
        }

        return trim( $body['choices'][0]['message']['content'] ?? '' );
    }
}
```

**Step 2: Write unit test (mocking wp_remote_post)**

```php
<?php
// tests/Providers/OpenAIProviderTest.php
namespace SeoGeo\Tests\Providers;

use PHPUnit\Framework\TestCase;
use SeoGeo\Providers\OpenAIProvider;

class OpenAIProviderTest extends TestCase {
    public function test_get_id(): void {
        $provider = new OpenAIProvider();
        $this->assertEquals('openai', $provider->getId());
    }

    public function test_get_models_returns_array(): void {
        $provider = new OpenAIProvider();
        $models = $provider->getModels();
        $this->assertIsArray($models);
        $this->assertArrayHasKey('gpt-4.1', $models);
    }

    public function test_implements_interface(): void {
        $provider = new OpenAIProvider();
        $this->assertInstanceOf(\SeoGeo\Providers\ProviderInterface::class, $provider);
    }
}
```

Note: Full API integration tests require a live key — do those manually.

**Step 3: Run tests**

```bash
./vendor/bin/phpunit tests/Providers/OpenAIProviderTest.php --testdox
```
Expected: All 3 tests PASS.

**Step 4: Manual test (live API)**
In WordPress with plugin active, temporarily add to `seo-geo.php` bottom:
```php
// TEMP TEST - remove after
add_action('init', function() {
    if ( ! current_user_can('manage_options') || ! isset($_GET['seo_geo_test']) ) return;
    $provider = new \SeoGeo\Providers\OpenAIProvider();
    $result = $provider->testConnection('YOUR_KEY_HERE');
    wp_die( print_r($result, true) );
});
```
Visit `/?seo_geo_test=1` logged in as admin. Expected: `Array ( [success] => 1 [message] => Verbindung erfolgreich )`.
Remove the temp code after testing.

**Step 5: Commit**

```bash
git add includes/Providers/OpenAIProvider.php tests/Providers/OpenAIProviderTest.php
git commit -m "feat: OpenAI provider implementation"
```

---

## Task 4: Anthropic, Gemini & Grok Providers

**Files:**
- Create: `includes/Providers/AnthropicProvider.php`
- Create: `includes/Providers/GeminiProvider.php`
- Create: `includes/Providers/GrokProvider.php`

**Step 1: AnthropicProvider**

```php
<?php
namespace SeoGeo\Providers;

class AnthropicProvider implements ProviderInterface {
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function getId(): string { return 'anthropic'; }
    public function getName(): string { return 'Anthropic (Claude)'; }

    public function getModels(): array {
        return [
            'claude-sonnet-4-6'    => 'Claude Sonnet 4.6 (Empfohlen)',
            'claude-opus-4-6'      => 'Claude Opus 4.6 (Leistungsstark)',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Schnell & günstig)',
        ];
    }

    public function testConnection( string $api_key ): array {
        try {
            $this->generateText( 'Say "ok"', $api_key, 'claude-haiku-4-5-20251001', 5 );
            return [ 'success' => true, 'message' => 'Verbindung erfolgreich' ];
        } catch ( \RuntimeException $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            throw new \RuntimeException( $msg );
        }

        return trim( $body['content'][0]['text'] ?? '' );
    }
}
```

**Step 2: GeminiProvider**

```php
<?php
namespace SeoGeo\Providers;

class GeminiProvider implements ProviderInterface {
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function getId(): string { return 'gemini'; }
    public function getName(): string { return 'Google Gemini'; }

    public function getModels(): array {
        return [
            'gemini-2.0-flash'       => 'Gemini 2.0 Flash (Empfohlen)',
            'gemini-2.0-flash-lite'  => 'Gemini 2.0 Flash Lite (Günstig)',
            'gemini-1.5-pro'         => 'Gemini 1.5 Pro',
        ];
    }

    public function testConnection( string $api_key ): array {
        try {
            $this->generateText( 'Say "ok"', $api_key, 'gemini-2.0-flash-lite', 5 );
            return [ 'success' => true, 'message' => 'Verbindung erfolgreich' ];
        } catch ( \RuntimeException $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        $url      = self::API_BASE . $model . ':generateContent?key=' . $api_key;
        $response = wp_remote_post( $url, [
            'timeout'     => 30,
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( [
                'contents'          => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
                'generationConfig'  => [ 'maxOutputTokens' => $max_tokens ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            throw new \RuntimeException( $msg );
        }

        return trim( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );
    }
}
```

**Step 3: GrokProvider**

```php
<?php
namespace SeoGeo\Providers;

class GrokProvider implements ProviderInterface {
    private const API_URL = 'https://api.x.ai/v1/chat/completions';

    public function getId(): string { return 'grok'; }
    public function getName(): string { return 'xAI Grok'; }

    public function getModels(): array {
        return [
            'grok-3'      => 'Grok 3 (Empfohlen)',
            'grok-3-mini' => 'Grok 3 Mini (Günstig)',
        ];
    }

    public function testConnection( string $api_key ): array {
        try {
            $this->generateText( 'Say "ok"', $api_key, 'grok-3-mini', 5 );
            return [ 'success' => true, 'message' => 'Verbindung erfolgreich' ];
        } catch ( \RuntimeException $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        // xAI uses OpenAI-compatible API format
        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => $model,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => $max_tokens,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            throw new \RuntimeException( $msg );
        }

        return trim( $body['choices'][0]['message']['content'] ?? '' );
    }
}
```

**Step 4: Run all provider tests**

```bash
./vendor/bin/phpunit tests/ --testdox
```
Expected: All tests PASS.

**Step 5: Commit**

```bash
git add includes/Providers/AnthropicProvider.php includes/Providers/GeminiProvider.php includes/Providers/GrokProvider.php
git commit -m "feat: Anthropic, Gemini and Grok providers"
```

---

## Task 5: TokenEstimator Helper

**Files:**
- Create: `includes/Helpers/TokenEstimator.php`
- Create: `tests/Helpers/TokenEstimatorTest.php`

**Step 1: Write failing test**

```php
<?php
// tests/Helpers/TokenEstimatorTest.php
namespace SeoGeo\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use SeoGeo\Helpers\TokenEstimator;

class TokenEstimatorTest extends TestCase {
    public function test_estimate_tokens_approximation(): void {
        // ~4 chars per token is standard approximation
        $text   = str_repeat('a', 400); // 400 chars ≈ 100 tokens
        $tokens = TokenEstimator::estimate( $text );
        $this->assertGreaterThan( 80, $tokens );
        $this->assertLessThan( 120, $tokens );
    }

    public function test_truncate_to_token_limit(): void {
        $text      = str_repeat('word ', 500); // 2500 chars ≈ 625 tokens
        $truncated = TokenEstimator::truncate( $text, 100 );
        $this->assertLessThanOrEqual( 100, TokenEstimator::estimate( $truncated ) );
    }

    public function test_estimate_cost_openai_gpt4(): void {
        // GPT-4.1 input: ~$0.002 per 1k tokens
        $cost = TokenEstimator::estimateCost( 1000, 'openai', 'gpt-4.1', 'input' );
        $this->assertIsFloat( $cost );
        $this->assertGreaterThan( 0, $cost );
    }
}
```

**Step 2: Run test — expect FAIL**

```bash
./vendor/bin/phpunit tests/Helpers/TokenEstimatorTest.php --testdox
```
Expected: FAIL — class not found.

**Step 3: Implement TokenEstimator**

```php
<?php
namespace SeoGeo\Helpers;

class TokenEstimator {
    /**
     * Pricing per 1k tokens [provider][model][input|output]
     * Update these when provider pricing changes.
     */
    private const PRICING = [
        'openai' => [
            'gpt-4.1'       => [ 'input' => 0.002,  'output' => 0.008  ],
            'gpt-4o'        => [ 'input' => 0.0025, 'output' => 0.01   ],
            'gpt-4o-mini'   => [ 'input' => 0.00015,'output' => 0.0006 ],
            'gpt-3.5-turbo' => [ 'input' => 0.0005, 'output' => 0.0015 ],
        ],
        'anthropic' => [
            'claude-sonnet-4-6'         => [ 'input' => 0.003,  'output' => 0.015  ],
            'claude-opus-4-6'           => [ 'input' => 0.015,  'output' => 0.075  ],
            'claude-haiku-4-5-20251001' => [ 'input' => 0.00025,'output' => 0.00125],
        ],
        'gemini' => [
            'gemini-2.0-flash'      => [ 'input' => 0.00010, 'output' => 0.00040 ],
            'gemini-2.0-flash-lite' => [ 'input' => 0.000038,'output' => 0.00015 ],
            'gemini-1.5-pro'        => [ 'input' => 0.00125, 'output' => 0.005   ],
        ],
        'grok' => [
            'grok-3'      => [ 'input' => 0.003, 'output' => 0.015 ],
            'grok-3-mini' => [ 'input' => 0.0003,'output' => 0.0005],
        ],
    ];

    /** Estimate token count (~4 chars per token) */
    public static function estimate( string $text ): int {
        return (int) ceil( mb_strlen( $text ) / 4 );
    }

    /** Truncate text to approximately $max_tokens */
    public static function truncate( string $text, int $max_tokens ): string {
        $max_chars = $max_tokens * 4;
        if ( mb_strlen( $text ) <= $max_chars ) {
            return $text;
        }
        return mb_substr( $text, 0, $max_chars );
    }

    /**
     * Estimate cost in USD.
     * @param int    $tokens    Number of tokens
     * @param string $provider  Provider ID
     * @param string $model     Model ID
     * @param string $type      'input' or 'output'
     */
    public static function estimateCost( int $tokens, string $provider, string $model, string $type = 'input' ): float {
        $price_per_1k = self::PRICING[ $provider ][ $model ][ $type ] ?? 0.002;
        return round( ( $tokens / 1000 ) * $price_per_1k, 6 );
    }

    /** Human-readable cost string e.g. "~0,05 €" */
    public static function formatCost( float $usd ): string {
        // Rough EUR conversion (update as needed, or make configurable)
        $eur = $usd * 0.92;
        if ( $eur < 0.01 ) {
            return '< 0,01 €';
        }
        return '~' . number_format( $eur, 2, ',', '.' ) . ' €';
    }
}
```

**Step 4: Run tests — expect PASS**

```bash
./vendor/bin/phpunit tests/Helpers/TokenEstimatorTest.php --testdox
```
Expected: All 3 tests PASS.

**Step 5: Commit**

```bash
git add includes/Helpers/TokenEstimator.php tests/Helpers/TokenEstimatorTest.php
git commit -m "feat: TokenEstimator with cost estimation"
```

---

## Task 6: Settings Page

**Files:**
- Create: `includes/Admin/SettingsPage.php`
- Create: `assets/admin.css`

**Step 1: Create SettingsPage.php**

```php
<?php
namespace SeoGeo\Admin;

use SeoGeo\ProviderRegistry;
use SeoGeo\Helpers\TokenEstimator;

class SettingsPage {
    private const OPTION_KEY = 'seo_geo_settings';

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_seo_geo_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    public function add_menu(): void {
        add_options_page(
            'SEO & GEO Tools',
            'SEO & GEO Tools',
            'manage_options',
            'seo-geo-settings',
            [ $this, 'render' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'seo_geo', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'settings_page_seo-geo-settings' ) return;
        wp_enqueue_style( 'seo-geo-admin', SEO_GEO_URL . 'assets/admin.css', [], SEO_GEO_VERSION );
        wp_enqueue_script( 'seo-geo-admin', SEO_GEO_URL . 'assets/admin.js', [ 'jquery' ], SEO_GEO_VERSION, true );
        wp_localize_script( 'seo-geo-admin', 'seoGeo', [
            'nonce'   => wp_create_nonce( 'seo_geo_admin' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public static function getSettings(): array {
        $defaults = [
            'provider'           => 'openai',
            'api_keys'           => [],
            'models'             => [],
            'meta_auto_enabled'  => true,
            'meta_post_types'    => [ 'post', 'page' ],
            'token_mode'         => 'limit',  // 'limit' or 'full'
            'token_limit'        => 1000,
            'prompt'             => self::getDefaultPrompt(),
            'schema_enabled'     => [],
            'schema_same_as'     => [],
        ];
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge( $defaults, $saved );
    }

    public static function getDefaultPrompt(): string {
        return 'Schreibe eine SEO-optimierte Meta-Beschreibung für den folgenden Artikel.' . "\n"
             . 'Die Beschreibung soll für menschliche Leser verständlich und hilfreich sein,' . "\n"
             . 'den Inhalt treffend zusammenfassen und zwischen 150 und 160 Zeichen lang sein.' . "\n"
             . 'Schreibe die Meta-Beschreibung auf {language}.' . "\n"
             . 'Antworte ausschließlich mit der Meta-Beschreibung, ohne Erklärung.' . "\n\n"
             . 'Titel: {title}' . "\n"
             . 'Inhalt: {content}';
    }

    public function sanitize_settings( array $input ): array {
        $clean = [];
        $clean['provider']          = sanitize_key( $input['provider'] ?? 'openai' );
        $clean['meta_auto_enabled'] = ! empty( $input['meta_auto_enabled'] );
        $clean['token_mode']        = in_array( $input['token_mode'] ?? '', [ 'limit', 'full' ] ) ? $input['token_mode'] : 'limit';
        $clean['token_limit']       = max( 100, (int) ( $input['token_limit'] ?? 1000 ) );
        $clean['prompt']            = sanitize_textarea_field( $input['prompt'] ?? self::getDefaultPrompt() );

        // API keys — sanitize each
        $clean['api_keys'] = [];
        foreach ( ( $input['api_keys'] ?? [] ) as $provider_id => $key ) {
            $clean['api_keys'][ sanitize_key( $provider_id ) ] = sanitize_text_field( $key );
        }

        // Selected models per provider
        $clean['models'] = [];
        foreach ( ( $input['models'] ?? [] ) as $provider_id => $model ) {
            $clean['models'][ sanitize_key( $provider_id ) ] = sanitize_text_field( $model );
        }

        // Post types
        $all_post_types = array_keys( get_post_types( [ 'public' => true ] ) );
        $clean['meta_post_types'] = array_intersect(
            array_map( 'sanitize_key', (array) ( $input['meta_post_types'] ?? [] ) ),
            $all_post_types
        );

        // Schema toggles
        $schema_types = [ 'organization', 'author', 'speakable', 'article_about', 'breadcrumb', 'ai_meta_tags' ];
        $clean['schema_enabled'] = array_intersect(
            array_map( 'sanitize_key', (array) ( $input['schema_enabled'] ?? [] ) ),
            $schema_types
        );

        // sameAs URLs
        $clean['schema_same_as'] = [
            'organization' => array_filter( array_map( 'esc_url_raw', (array) ( $input['schema_same_as']['organization'] ?? [] ) ) ),
        ];

        return $clean;
    }

    public function ajax_test_connection(): void {
        check_ajax_referer( 'seo_geo_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $provider_id = sanitize_key( $_POST['provider'] ?? '' );
        $api_key     = sanitize_text_field( $_POST['api_key'] ?? '' );

        $registry = ProviderRegistry::instance();
        $provider = $registry->get( $provider_id );

        if ( ! $provider ) {
            wp_send_json_error( 'Unbekannter Provider.' );
        }

        $result = $provider->testConnection( $api_key );
        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings  = self::getSettings();
        $registry  = ProviderRegistry::instance();
        $providers = $registry->all();
        $post_types = get_post_types( [ 'public' => true ], 'objects' );

        $schema_labels = [
            'organization'  => 'Organization (sameAs Social-Profile)',
            'author'        => 'Author (sameAs Profil-Links)',
            'speakable'     => 'Speakable (für AI-Assistenten)',
            'article_about' => 'Article about/mentions',
            'breadcrumb'    => 'BreadcrumbList',
            'ai_meta_tags'  => 'AI-optimierte Meta-Tags (max-snippet etc.)',
        ];

        include SEO_GEO_DIR . 'includes/Admin/views/settings.php';
    }
}
```

**Step 2: Create settings view**

Create `includes/Admin/views/settings.php`:

```php
<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap seo-geo-settings">
    <h1>SEO & GEO Tools</h1>

    <?php settings_errors( 'seo_geo' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'seo_geo' ); ?>

        <!-- PROVIDER SECTION -->
        <h2>AI-Provider</h2>
        <table class="form-table">
            <tr>
                <th>Aktiver Provider</th>
                <td>
                    <select name="seo_geo_settings[provider]" id="seo-geo-provider">
                        <?php foreach ( $providers as $id => $provider ): ?>
                        <option value="<?= esc_attr($id) ?>" <?= selected($settings['provider'], $id, false) ?>>
                            <?= esc_html($provider->getName()) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php foreach ( $providers as $id => $provider ): ?>
            <tr class="seo-geo-provider-row" data-provider="<?= esc_attr($id) ?>">
                <th><?= esc_html($provider->getName()) ?> API Key</th>
                <td>
                    <input type="password"
                           name="seo_geo_settings[api_keys][<?= esc_attr($id) ?>]"
                           value="<?= esc_attr($settings['api_keys'][$id] ?? '') ?>"
                           class="regular-text"
                           autocomplete="off">
                    <button type="button"
                            class="button seo-geo-test-btn"
                            data-provider="<?= esc_attr($id) ?>">
                        Verbindung testen
                    </button>
                    <span class="seo-geo-test-result" id="test-result-<?= esc_attr($id) ?>"></span>

                    <br><br>
                    <label>Modell:</label>
                    <select name="seo_geo_settings[models][<?= esc_attr($id) ?>]">
                        <?php foreach ( $provider->getModels() as $model_id => $model_label ): ?>
                        <option value="<?= esc_attr($model_id) ?>"
                            <?= selected($settings['models'][$id] ?? array_key_first($provider->getModels()), $model_id, false) ?>>
                            <?= esc_html($model_label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- META GENERATOR SECTION -->
        <h2>Meta-Generator</h2>
        <table class="form-table">
            <tr>
                <th>Auto-Modus</th>
                <td>
                    <label>
                        <input type="checkbox" name="seo_geo_settings[meta_auto_enabled]" value="1"
                               <?= checked($settings['meta_auto_enabled'], true, false) ?>>
                        Meta-Beschreibung automatisch beim Veröffentlichen generieren
                    </label>
                </td>
            </tr>
            <tr>
                <th>Post-Types</th>
                <td>
                    <?php foreach ( $post_types as $pt_slug => $pt_obj ): ?>
                    <label style="margin-right:15px;">
                        <input type="checkbox"
                               name="seo_geo_settings[meta_post_types][]"
                               value="<?= esc_attr($pt_slug) ?>"
                               <?= in_array($pt_slug, $settings['meta_post_types']) ? 'checked' : '' ?>>
                        <?= esc_html($pt_obj->labels->singular_name) ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th>Token-Modus</th>
                <td>
                    <label>
                        <input type="radio" name="seo_geo_settings[token_mode]" value="full"
                               <?= checked($settings['token_mode'], 'full', false) ?>>
                        Ganzen Artikel senden
                    </label>
                    &nbsp;&nbsp;
                    <label>
                        <input type="radio" name="seo_geo_settings[token_mode]" value="limit"
                               <?= checked($settings['token_mode'], 'limit', false) ?>>
                        Auf
                        <input type="number"
                               name="seo_geo_settings[token_limit]"
                               value="<?= esc_attr($settings['token_limit']) ?>"
                               min="100" max="8000" style="width:80px;">
                        Token kürzen
                    </label>
                </td>
            </tr>
            <tr>
                <th>Prompt</th>
                <td>
                    <textarea name="seo_geo_settings[prompt]" rows="8" class="large-text code"><?= esc_textarea($settings['prompt']) ?></textarea>
                    <p class="description">
                        Variablen: <code>{title}</code>, <code>{content}</code>, <code>{excerpt}</code>, <code>{language}</code><br>
                        <button type="button" class="button" id="seo-geo-reset-prompt">Prompt zurücksetzen</button>
                    </p>
                </td>
            </tr>
        </table>

        <!-- SCHEMA SECTION -->
        <h2>Schema.org Enhancer (GEO)</h2>
        <table class="form-table">
            <tr>
                <th>Aktivierte Schema-Typen</th>
                <td>
                    <?php foreach ( $schema_labels as $type => $label ): ?>
                    <label style="display:block;margin-bottom:8px;">
                        <input type="checkbox"
                               name="seo_geo_settings[schema_enabled][]"
                               value="<?= esc_attr($type) ?>"
                               <?= in_array($type, $settings['schema_enabled']) ? 'checked' : '' ?>>
                        <?= esc_html($label) ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th>Organization sameAs URLs</th>
                <td>
                    <p class="description">Eine URL pro Zeile (Twitter, LinkedIn, GitHub, Facebook…)</p>
                    <textarea name="seo_geo_settings[schema_same_as][organization]"
                              rows="5" class="large-text">
                        <?= esc_textarea( implode("\n", $settings['schema_same_as']['organization'] ?? []) ) ?>
                    </textarea>
                </td>
            </tr>
        </table>

        <?php submit_button('Einstellungen speichern'); ?>
    </form>

    <hr>
    <p style="color:#999;font-size:12px;">
        SEO & GEO Tools <?= esc_html(SEO_GEO_VERSION) ?> &mdash;
        entwickelt mit ♥ von <a href="https://donau2space.de" target="_blank">Donau2Space.de</a>
    </p>
</div>
```

**Step 3: Create `includes/Admin/views/` directory**

```bash
mkdir -p includes/Admin/views
```

**Step 4: Create basic admin.css**

```css
/* assets/admin.css */
.seo-geo-settings h2 { border-bottom: 1px solid #ddd; padding-bottom: 5px; }
.seo-geo-provider-row { display: none; }
.seo-geo-provider-row.active { display: table-row; }
.seo-geo-test-result { margin-left: 10px; font-weight: bold; }
.seo-geo-test-result.success { color: #46b450; }
.seo-geo-test-result.error { color: #dc3232; }
```

**Step 5: Create admin.js**

```js
/* assets/admin.js */
jQuery(function ($) {
    // Show only active provider rows
    function updateProviderRows() {
        var active = $('#seo-geo-provider').val();
        $('.seo-geo-provider-row').removeClass('active');
        $('.seo-geo-provider-row[data-provider="' + active + '"]').addClass('active');
    }
    updateProviderRows();
    $('#seo-geo-provider').on('change', updateProviderRows);

    // Test connection
    $(document).on('click', '.seo-geo-test-btn', function () {
        var btn       = $(this);
        var providerId = btn.data('provider');
        var apiKey    = btn.siblings('input[type=password]').val();
        var resultEl  = $('#test-result-' + providerId);

        resultEl.removeClass('success error').text('Teste…');
        btn.prop('disabled', true);

        $.post(seoGeo.ajaxUrl, {
            action:   'seo_geo_test_connection',
            nonce:    seoGeo.nonce,
            provider: providerId,
            api_key:  apiKey,
        }).done(function (res) {
            if (res.success) {
                resultEl.addClass('success').text('✓ ' + res.data);
            } else {
                resultEl.addClass('error').text('✗ ' + res.data);
            }
        }).fail(function () {
            resultEl.addClass('error').text('✗ Netzwerkfehler');
        }).always(function () {
            btn.prop('disabled', false);
        });
    });

    // Reset prompt
    $('#seo-geo-reset-prompt').on('click', function () {
        if (!confirm('Prompt wirklich zurücksetzen?')) return;
        $.post(seoGeo.ajaxUrl, {
            action: 'seo_geo_get_default_prompt',
            nonce:  seoGeo.nonce,
        }).done(function (res) {
            if (res.success) $('textarea[name*=prompt]').val(res.data);
        });
    });
});
```

**Step 6: Add AJAX handler for default prompt reset to SettingsPage.php**

Add to the `register()` method:
```php
add_action( 'wp_ajax_seo_geo_get_default_prompt', [ $this, 'ajax_get_default_prompt' ] );
```

Add the method:
```php
public function ajax_get_default_prompt(): void {
    check_ajax_referer( 'seo_geo_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    wp_send_json_success( self::getDefaultPrompt() );
}
```

**Step 7: Verify in WordPress**

- Navigate to `Einstellungen → SEO & GEO Tools`
- Provider dropdown should show all 4 providers
- Selecting a provider should show its row (API key + model select)
- "Verbindung testen" button should work with a real API key

**Step 8: Commit**

```bash
git add includes/Admin/ assets/
git commit -m "feat: settings page with provider selection, model picker, API key test"
```

---

## Task 7: MetaGenerator Feature

**Files:**
- Create: `includes/Features/MetaGenerator.php`

**Step 1: Create MetaGenerator.php**

```php
<?php
namespace SeoGeo\Features;

use SeoGeo\Admin\SettingsPage;
use SeoGeo\ProviderRegistry;
use SeoGeo\Helpers\TokenEstimator;

class MetaGenerator {
    public function register(): void {
        $settings = SettingsPage::getSettings();
        if ( ! empty( $settings['meta_auto_enabled'] ) ) {
            add_action( 'publish_post', [ $this, 'onPublish' ], 20, 2 );
            add_action( 'publish_page', [ $this, 'onPublish' ], 20, 2 );

            // Custom post types
            foreach ( $settings['meta_post_types'] as $post_type ) {
                if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
                    add_action( "publish_{$post_type}", [ $this, 'onPublish' ], 20, 2 );
                }
            }
        }

        // AJAX for bulk processing
        add_action( 'wp_ajax_seo_geo_bulk_generate',  [ $this, 'ajaxBulkGenerate' ] );
        add_action( 'wp_ajax_seo_geo_bulk_stats',     [ $this, 'ajaxBulkStats' ] );
    }

    public function onPublish( int $post_id, \WP_Post $post ): void {
        // Avoid auto-drafts, revisions
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

        // Skip if meta already exists
        if ( $this->hasExistingMeta( $post_id ) ) return;

        $settings = SettingsPage::getSettings();
        if ( ! in_array( $post->post_type, $settings['meta_post_types'], true ) ) return;

        try {
            $description = $this->generate( $post, $settings );
            if ( ! empty( $description ) ) {
                $this->saveMeta( $post_id, $description );
            }
        } catch ( \Exception $e ) {
            error_log( '[SEO-GEO] Meta generation failed for post ' . $post_id . ': ' . $e->getMessage() );
        }
    }

    public function generate( \WP_Post $post, array $settings ): string {
        $registry = ProviderRegistry::instance();
        $provider = $registry->get( $settings['provider'] );
        if ( ! $provider ) {
            throw new \RuntimeException( 'Provider not found: ' . $settings['provider'] );
        }

        $api_key = $settings['api_keys'][ $settings['provider'] ] ?? '';
        if ( empty( $api_key ) ) {
            throw new \RuntimeException( 'No API key configured for provider: ' . $settings['provider'] );
        }

        $model    = $settings['models'][ $settings['provider'] ] ?? array_key_first( $provider->getModels() );
        $content  = $this->prepareContent( $post, $settings );
        $prompt   = $this->buildPrompt( $post, $content, $settings );

        return $provider->generateText( $prompt, $api_key, $model, 300 );
    }

    private function prepareContent( \WP_Post $post, array $settings ): string {
        $content = wp_strip_all_tags( $post->post_content );
        if ( $settings['token_mode'] === 'limit' ) {
            $content = TokenEstimator::truncate( $content, (int) $settings['token_limit'] );
        }
        return $content;
    }

    private function buildPrompt( \WP_Post $post, string $content, array $settings ): string {
        $language = $this->detectLanguage( $post );
        $prompt   = $settings['prompt'];

        $prompt = str_replace( '{title}',    $post->post_title, $prompt );
        $prompt = str_replace( '{content}',  $content, $prompt );
        $prompt = str_replace( '{excerpt}',  $post->post_excerpt ?: '', $prompt );
        $prompt = str_replace( '{language}', $language, $prompt );

        return $prompt;
    }

    private function detectLanguage( \WP_Post $post ): string {
        // Polylang
        if ( function_exists( 'pll_get_post_language' ) ) {
            $lang = pll_get_post_language( $post->ID, 'name' );
            if ( $lang ) return $lang;
        }

        // WPML
        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            return ICL_LANGUAGE_CODE;
        }

        // WordPress locale → map to language name
        $locale = get_locale();
        $locale_map = [
            'de_DE' => 'Deutsch', 'de_AT' => 'Deutsch', 'de_CH' => 'Deutsch',
            'en_US' => 'English', 'en_GB' => 'English',
            'fr_FR' => 'Français', 'es_ES' => 'Español',
        ];

        return $locale_map[ $locale ] ?? 'Deutsch';
    }

    /** Check if a meta description already exists (Rank Math, Yoast, AIOSEO, custom) */
    public function hasExistingMeta( int $post_id ): bool {
        $fields = [
            '_seo_geo_meta_description',  // own field
            'rank_math_description',       // Rank Math
            '_yoast_wpseo_metadesc',       // Yoast
            '_aioseo_description',         // AIOSEO
            '_seopress_titles_desc',       // SEOPress
            '_meta_description',           // generic
        ];
        foreach ( $fields as $field ) {
            if ( ! empty( get_post_meta( $post_id, $field, true ) ) ) {
                return true;
            }
        }
        return false;
    }

    public function saveMeta( int $post_id, string $description ): void {
        update_post_meta( $post_id, '_seo_geo_meta_description', sanitize_text_field( $description ) );

        // Also write to active SEO plugin's field if detected
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $description ) );
        } elseif ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $description ) );
        }
    }

    /** AJAX: Return stats for bulk page */
    public function ajaxBulkStats(): void {
        check_ajax_referer( 'seo_geo_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $settings   = SettingsPage::getSettings();
        $post_types = $settings['meta_post_types'];
        $stats      = [];

        foreach ( $post_types as $pt ) {
            $ids = $this->getPostsWithoutMeta( $pt, 9999 );
            $stats[ $pt ] = count( $ids );
        }

        wp_send_json_success( $stats );
    }

    /** AJAX: Process one batch of posts */
    public function ajaxBulkGenerate(): void {
        check_ajax_referer( 'seo_geo_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $post_type = sanitize_key( $_POST['post_type'] ?? 'post' );
        $limit     = min( 5, (int) ( $_POST['batch_size'] ?? 5 ) );
        $settings  = SettingsPage::getSettings();

        // Override provider/model if passed from bulk page
        if ( ! empty( $_POST['provider'] ) ) {
            $settings['provider'] = sanitize_key( $_POST['provider'] );
        }
        if ( ! empty( $_POST['model'] ) ) {
            $settings['models'][ $settings['provider'] ] = sanitize_text_field( $_POST['model'] );
        }

        $post_ids = $this->getPostsWithoutMeta( $post_type, $limit );
        $results  = [];

        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            try {
                $desc = $this->generate( $post, $settings );
                $this->saveMeta( $post_id, $desc );
                $results[] = [
                    'id'          => $post_id,
                    'title'       => get_the_title( $post_id ),
                    'description' => $desc,
                    'success'     => true,
                ];
            } catch ( \Exception $e ) {
                $results[] = [
                    'id'      => $post_id,
                    'title'   => get_the_title( $post_id ),
                    'error'   => $e->getMessage(),
                    'success' => false,
                ];
            }
        }

        wp_send_json_success( [
            'results'    => $results,
            'processed'  => count( $results ),
            'remaining'  => count( $this->getPostsWithoutMeta( $post_type, 9999 ) ),
        ] );
    }

    private function getPostsWithoutMeta( string $post_type, int $limit ): array {
        global $wpdb;

        // Get all published posts of this type
        $all_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = 'publish'
             LIMIT %d",
            $post_type, $limit * 10  // fetch more to filter
        ) );

        $without_meta = [];
        foreach ( $all_ids as $id ) {
            if ( ! $this->hasExistingMeta( (int) $id ) ) {
                $without_meta[] = (int) $id;
                if ( count( $without_meta ) >= $limit ) break;
            }
        }

        return $without_meta;
    }
}
```

**Step 2: Manual test — Auto-mode**

1. Go to WordPress admin → create a new Post
2. Publish it
3. Go back to edit the post → check `Custom Fields` panel for `_seo_geo_meta_description`
4. Expected: Field exists with a generated description in German

**Step 3: Commit**

```bash
git add includes/Features/MetaGenerator.php
git commit -m "feat: MetaGenerator with auto-publish hook and bulk AJAX endpoints"
```

---

## Task 8: Bulk Page

**Files:**
- Create: `includes/Admin/BulkPage.php`
- Create: `includes/Admin/views/bulk.php`

**Step 1: Create BulkPage.php**

```php
<?php
namespace SeoGeo\Admin;

use SeoGeo\ProviderRegistry;
use SeoGeo\Helpers\TokenEstimator;

class BulkPage {
    public function register(): void {
        add_management_page(
            'GEO Bulk Meta',
            'GEO Bulk Meta',
            'manage_options',
            'seo-geo-bulk',
            [ $this, 'render' ]
        );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'tools_page_seo-geo-bulk' ) return;
        wp_enqueue_style( 'seo-geo-admin', SEO_GEO_URL . 'assets/admin.css', [], SEO_GEO_VERSION );
        wp_enqueue_script( 'seo-geo-bulk', SEO_GEO_URL . 'assets/bulk.js', [ 'jquery' ], SEO_GEO_VERSION, true );
        wp_localize_script( 'seo-geo-bulk', 'seoGeoBulk', [
            'nonce'   => wp_create_nonce( 'seo_geo_admin' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings  = SettingsPage::getSettings();
        $registry  = ProviderRegistry::instance();
        $providers = $registry->all();
        include SEO_GEO_DIR . 'includes/Admin/views/bulk.php';
    }
}
```

**Step 2: Create bulk.php view**

```php
<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap seo-geo-settings">
    <h1>GEO Bulk Meta-Generator</h1>
    <p>Generiert Meta-Beschreibungen für Artikel ohne vorhandene Meta-Beschreibung.</p>

    <div id="seo-geo-bulk-stats" style="background:#fff;padding:15px;border:1px solid #ddd;margin-bottom:20px;">
        <em>Lade Statistiken…</em>
    </div>

    <table class="form-table">
        <tr>
            <th>Provider</th>
            <td>
                <select id="seo-geo-bulk-provider">
                    <?php foreach ( $providers as $id => $provider ): ?>
                    <option value="<?= esc_attr($id) ?>"
                        <?= selected($settings['provider'], $id, false) ?>>
                        <?= esc_html($provider->getName()) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th>Modell</th>
            <td>
                <select id="seo-geo-bulk-model">
                    <?php
                    $active_provider = $registry->get($settings['provider']);
                    if ($active_provider):
                        foreach ($active_provider->getModels() as $mid => $mlabel):
                    ?>
                    <option value="<?= esc_attr($mid) ?>"><?= esc_html($mlabel) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th>Max. Artikel diesen Run</th>
            <td>
                <input type="number" id="seo-geo-bulk-limit" value="20" min="1" max="500">
                <p class="description" id="seo-geo-cost-estimate"></p>
            </td>
        </tr>
    </table>

    <p>
        <button id="seo-geo-bulk-start" class="button button-primary">Bulk-Run starten</button>
        <button id="seo-geo-bulk-stop" class="button" style="display:none;">Abbrechen</button>
    </p>

    <div id="seo-geo-progress-wrap" style="display:none;margin:15px 0;">
        <div style="background:#ddd;border-radius:3px;height:20px;width:100%;">
            <div id="seo-geo-progress-bar"
                 style="background:#0073aa;height:20px;border-radius:3px;width:0;transition:width .3s;"></div>
        </div>
        <p id="seo-geo-progress-text">0 / 0 verarbeitet</p>
    </div>

    <div id="seo-geo-bulk-log" style="background:#1e1e1e;color:#d4d4d4;padding:15px;font-family:monospace;font-size:12px;max-height:400px;overflow-y:auto;display:none;"></div>
</div>
```

**Step 3: Create `assets/bulk.js`**

```js
/* assets/bulk.js */
jQuery(function ($) {
    var running   = false;
    var stopFlag  = false;
    var processed = 0;
    var total     = 0;

    // Load stats on page load
    loadStats();

    function loadStats() {
        $.post(seoGeoBulk.ajaxUrl, {
            action: 'seo_geo_bulk_stats',
            nonce:  seoGeoBulk.nonce,
        }).done(function (res) {
            if (!res.success) return;
            var html = '<strong>Posts ohne Meta-Beschreibung:</strong><ul>';
            var t = 0;
            $.each(res.data, function (pt, count) {
                html += '<li>' + pt + ': <strong>' + count + '</strong></li>';
                t += count;
            });
            html += '</ul><strong>Gesamt: ' + t + '</strong>';
            total = t;
            $('#seo-geo-bulk-stats').html(html);
            updateCostEstimate();
        });
    }

    // Update model dropdown when provider changes
    $('#seo-geo-bulk-provider').on('change', function () {
        // Simple reload — provider models are static, reload page with param
        // or update via AJAX. For V1: reload.
        window.location.href = window.location.href + '&provider=' + $(this).val();
    });

    $('#seo-geo-bulk-limit, #seo-geo-bulk-model').on('change', updateCostEstimate);

    function updateCostEstimate() {
        var limit  = parseInt($('#seo-geo-bulk-limit').val()) || 20;
        // Rough estimate: avg article 800 tokens input, 50 tokens output
        var inputTokens  = limit * 800;
        var outputTokens = limit * 50;
        $('#seo-geo-cost-estimate').text(
            'Grobe Kostenschätzung: ~' + inputTokens + ' Input-Token + ' + outputTokens + ' Output-Token'
        );
    }

    $('#seo-geo-bulk-start').on('click', function () {
        if (running) return;
        running   = true;
        stopFlag  = false;
        processed = 0;

        $(this).prop('disabled', true);
        $('#seo-geo-bulk-stop').show();
        $('#seo-geo-progress-wrap').show();
        $('#seo-geo-bulk-log').show().html('');

        var limit    = parseInt($('#seo-geo-bulk-limit').val()) || 20;
        var provider = $('#seo-geo-bulk-provider').val();
        var model    = $('#seo-geo-bulk-model').val();

        runBatch('post', limit, provider, model);
    });

    $('#seo-geo-bulk-stop').on('click', function () {
        stopFlag = true;
        log('⚠ Abbruch angefordert…', 'warn');
    });

    function runBatch(postType, remaining, provider, model) {
        if (stopFlag || remaining <= 0) {
            finish();
            return;
        }

        var batchSize = Math.min(5, remaining);

        $.post(seoGeoBulk.ajaxUrl, {
            action:     'seo_geo_bulk_generate',
            nonce:      seoGeoBulk.nonce,
            post_type:  postType,
            batch_size: batchSize,
            provider:   provider,
            model:      model,
        }).done(function (res) {
            if (!res.success) {
                log('✗ Fehler: ' + res.data, 'error');
                finish();
                return;
            }

            $.each(res.data.results, function (i, item) {
                if (item.success) {
                    log('✓ [' + item.id + '] ' + item.title + '<br><small style="color:#9cdcfe;">' + item.description + '</small>');
                } else {
                    log('✗ [' + item.id + '] ' + item.title + ' — ' + item.error, 'error');
                }
                processed++;
            });

            updateProgress(processed, total);

            if (res.data.remaining > 0 && !stopFlag) {
                runBatch(postType, remaining - batchSize, provider, model);
            } else {
                finish();
            }
        }).fail(function () {
            log('✗ Netzwerkfehler', 'error');
            finish();
        });
    }

    function updateProgress(done, t) {
        var pct = t > 0 ? Math.round((done / t) * 100) : 100;
        $('#seo-geo-progress-bar').css('width', pct + '%');
        $('#seo-geo-progress-text').text(done + ' / ' + t + ' verarbeitet');
    }

    function log(msg, type) {
        var color = type === 'error' ? '#f48771' : type === 'warn' ? '#dcdcaa' : '#9cdcfe';
        $('#seo-geo-bulk-log').append('<div style="color:' + color + ';margin-bottom:4px;">' + msg + '</div>');
        var el = document.getElementById('seo-geo-bulk-log');
        el.scrollTop = el.scrollHeight;
    }

    function finish() {
        running = false;
        $('#seo-geo-bulk-start').prop('disabled', false);
        $('#seo-geo-bulk-stop').hide();
        log('— Fertig —');
        loadStats();
    }
});
```

**Step 4: Manual test**

- Navigate to `Tools → GEO Bulk Meta`
- Stats block should load showing post counts
- Start a bulk run with limit 3
- Watch progress bar and log fill in
- Verify generated descriptions appear in post meta

**Step 5: Commit**

```bash
git add includes/Admin/BulkPage.php includes/Admin/views/bulk.php assets/bulk.js
git commit -m "feat: bulk meta generation page with progress bar and live log"
```

---

## Task 9: SchemaEnhancer

**Files:**
- Create: `includes/Features/SchemaEnhancer.php`

**Step 1: Create SchemaEnhancer.php**

```php
<?php
namespace SeoGeo\Features;

use SeoGeo\Admin\SettingsPage;

class SchemaEnhancer {
    public function register(): void {
        $settings = SettingsPage::getSettings();
        $enabled  = $settings['schema_enabled'] ?? [];

        if ( empty( $enabled ) ) return;

        if ( in_array( 'ai_meta_tags', $enabled, true ) ) {
            add_action( 'wp_head', [ $this, 'outputAiMetaTags' ], 1 );
        }

        // JSON-LD output
        $json_ld_types = array_diff( $enabled, [ 'ai_meta_tags' ] );
        if ( ! empty( $json_ld_types ) ) {
            add_action( 'wp_head', [ $this, 'outputJsonLd' ], 5 );
        }

        // Output our own meta description if no SEO plugin is active
        add_action( 'wp_head', [ $this, 'outputMetaDescription' ], 2 );
    }

    public function outputAiMetaTags(): void {
        echo '<meta name="robots" content="max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";
        echo '<meta name="googlebot" content="max-snippet:-1, max-image-preview:large">' . "\n";
    }

    public function outputMetaDescription(): void {
        // Only output if no SEO plugin will do it
        if ( defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('AIOSEO_VERSION') ) return;

        if ( ! is_singular() ) return;

        $post_id = get_the_ID();
        $desc    = get_post_meta( $post_id, '_seo_geo_meta_description', true );
        if ( empty( $desc ) ) return;

        echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
    }

    public function outputJsonLd(): void {
        $settings = SettingsPage::getSettings();
        $enabled  = $settings['schema_enabled'] ?? [];
        $schemas  = [];

        if ( in_array( 'organization', $enabled, true ) ) {
            $schemas[] = $this->buildOrganizationSchema( $settings );
        }

        if ( is_singular() ) {
            if ( in_array( 'article_about', $enabled, true ) ) {
                $schemas[] = $this->buildArticleSchema();
            }
            if ( in_array( 'author', $enabled, true ) ) {
                $schemas[] = $this->buildAuthorSchema();
            }
            if ( in_array( 'speakable', $enabled, true ) ) {
                $schemas[] = $this->buildSpeakableSchema();
            }
        }

        if ( in_array( 'breadcrumb', $enabled, true ) && ! defined('RANK_MATH_VERSION') && ! defined('WPSEO_VERSION') ) {
            $breadcrumb = $this->buildBreadcrumbSchema();
            if ( $breadcrumb ) $schemas[] = $breadcrumb;
        }

        foreach ( $schemas as $schema ) {
            echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        }
    }

    private function buildOrganizationSchema( array $settings ): array {
        $same_as = array_values( array_filter( $settings['schema_same_as']['organization'] ?? [] ) );
        $schema  = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => get_bloginfo('name'),
            'url'      => home_url('/'),
        ];
        if ( ! empty( $same_as ) ) {
            $schema['sameAs'] = $same_as;
        }
        $logo = get_site_icon_url(192);
        if ( $logo ) {
            $schema['logo'] = $logo;
        }
        return $schema;
    }

    private function buildArticleSchema(): array {
        $post = get_post();
        return [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => get_the_title(),
            'url'              => get_permalink(),
            'datePublished'    => get_the_date('c'),
            'dateModified'     => get_the_modified_date('c'),
            'description'      => get_post_meta( get_the_ID(), '_seo_geo_meta_description', true ) ?: get_the_excerpt(),
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
        ];
    }

    private function buildAuthorSchema(): array {
        $author_id = get_the_author_meta('ID');
        $schema    = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => get_the_author(),
            'url'      => get_author_posts_url( $author_id ),
        ];
        $twitter = get_the_author_meta('twitter', $author_id);
        if ( $twitter ) {
            $schema['sameAs'] = [ 'https://twitter.com/' . ltrim($twitter, '@') ];
        }
        return $schema;
    }

    private function buildSpeakableSchema(): array {
        return [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebPage',
            'speakable' => [
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => [ 'h1', '.entry-content p:first-of-type', '.post-content p:first-of-type' ],
            ],
            'url' => get_permalink(),
        ];
    }

    private function buildBreadcrumbSchema(): ?array {
        if ( ! is_singular() && ! is_category() ) return null;

        $items = [
            [
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => get_bloginfo('name'),
                'item'     => home_url('/'),
            ],
        ];

        if ( is_singular() ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => get_the_title(),
                'item'     => get_permalink(),
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
```

**Step 2: Manual test**

1. Enable schema types in `Einstellungen → SEO & GEO Tools → Schema.org Enhancer`
2. Add sameAs URLs (e.g. your Twitter/LinkedIn)
3. View a published post → right-click → View Source → search for `application/ld+json`
4. Expected: JSON-LD blocks present, validate at https://validator.schema.org

**Step 3: Commit**

```bash
git add includes/Features/SchemaEnhancer.php
git commit -m "feat: Schema.org enhancer with Organization, Article, Author, Speakable, Breadcrumb"
```

---

## Task 10: README & Final Polish

**Files:**
- Create: `README.md`

**Step 1: Create README.md**

````markdown
# SEO & GEO Tools

A WordPress plugin that extends any site with AI-powered meta descriptions and GEO-optimized structured data, helping blogs get cited by ChatGPT, Claude, Grok, and Gemini.

**Developed by [Donau2Space.de](https://donau2space.de)**

## Features

- **AI Meta Generator** — Automatically writes SEO-optimized meta descriptions on publish
- **Bulk Generator** — Process all existing posts without meta descriptions
- **Schema.org Enhancer** — GEO-optimized structured data (Organization, Author, Speakable, Article)
- **Multi-Provider** — OpenAI, Anthropic, Google Gemini, xAI Grok
- **Standalone** — Works with Rank Math, Yoast, AIOSEO, SEOPress, or no SEO plugin

## Requirements

- WordPress 6.0+
- PHP 8.0+
- At least one AI provider API key

## Adding a New Provider

1. Create `includes/Providers/YourProvider.php` implementing `ProviderInterface`
2. Register in `includes/Core.php` → `register_hooks()`: `$registry->register( new Providers\YourProvider() );`
3. That's it — the provider appears automatically in all dropdowns

## Adding a New Feature

1. Create `includes/Features/YourFeature.php` with a `register()` method
2. Add `require_once` in `Core.php → load_dependencies()`
3. Instantiate and call `->register()` in `Core.php → register_hooks()`

## Available Hooks

```php
// Filter the list of registered providers
apply_filters( 'seo_geo_providers', $providers );

// Filter the prompt before sending to AI
apply_filters( 'seo_geo_prompt', $prompt, $post );

// Action after meta description is saved
do_action( 'seo_geo_meta_saved', $post_id, $description );
```

## Settings

`Settings → SEO & GEO Tools` — provider selection, API keys, model picker, prompt customization

`Tools → GEO Bulk Meta` — bulk generation with cost estimate and live progress log
````

**Step 2: Add missing WordPress hooks to MetaGenerator for extensibility**

In `MetaGenerator::buildPrompt()`, add filter:
```php
$prompt = apply_filters( 'seo_geo_prompt', $prompt, $post );
```

In `MetaGenerator::saveMeta()`, add action after saving:
```php
do_action( 'seo_geo_meta_saved', $post_id, $description );
```

**Step 3: Final test — full flow**

1. Fresh WordPress install (or staging)
2. Activate plugin
3. Configure OpenAI key + GPT-4.1 model
4. Enable Organization schema + add sameAs URLs
5. Publish a new post → verify meta description generated
6. Check source for JSON-LD
7. Run bulk for existing posts → check progress log

**Step 4: Final commit**

```bash
git add README.md includes/
git commit -m "feat: README, extensibility hooks, plugin complete v1.0.0"
```

---

## Summary

| Task | Component | Status |
|---|---|---|
| 1 | Plugin scaffold | ⬜ |
| 2 | ProviderInterface + Registry | ⬜ |
| 3 | OpenAI Provider | ⬜ |
| 4 | Anthropic, Gemini, Grok | ⬜ |
| 5 | TokenEstimator | ⬜ |
| 6 | Settings Page | ⬜ |
| 7 | MetaGenerator | ⬜ |
| 8 | Bulk Page | ⬜ |
| 9 | SchemaEnhancer | ⬜ |
| 10 | README + hooks | ⬜ |
