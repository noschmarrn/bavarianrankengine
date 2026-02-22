# GEO Schnellüberblick Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a per-post "GEO Schnellüberblick" block — AI-generated summary, bullet points, and optional FAQ — rendered as a `<details>` element, configurable globally and per-post.

**Architecture:** New `GeoBlock` feature class handles AI generation, content filtering, and post-meta storage. `GeoPage` admin class owns the global settings page. `GeoEditorBox` owns the per-post meta box and AJAX endpoints. All wired into `Core` exactly like existing features (MetaGenerator / LlmsPage pattern).

**Tech Stack:** PHP 8.0+, WordPress Settings API, `wp_ajax_*` AJAX, jQuery (already enqueued in admin), `the_content` filter, `transition_post_status` hook, JSON output from AI provider via existing `ProviderInterface::generateText()`.

---

## Codebase Patterns (read before starting)

- Feature classes: `includes/Features/FeatureName.php` with `register()` method
- Admin classes: `includes/Admin/PageName.php` with `register()` + `render()` methods
- View templates: `includes/Admin/views/pagename.php` — pure PHP, no output buffering
- Settings: `get_option('bre_xyz_settings', [])` → static `getSettings()` method on feature class
- WordPress Settings API: `register_setting('group', 'option_key', ['sanitize_callback' => ...])` → form POSTs to `options.php` with `settings_fields('group')`
- AJAX: `add_action('wp_ajax_bre_action', [$this, 'method'])` → `check_ajax_referer('bre_admin', 'nonce')` → `wp_send_json_success/error()`
- Provider access: `ProviderRegistry::instance()->get($id)` → `$provider->generateText($prompt, $api_key, $model, $max_tokens)`
- All files loaded manually in `Core::load_dependencies()`, registered in `Core::register_hooks()`
- ABSPATH guard comes AFTER namespace declaration (critical — see previous fatal error)
- phpcs:ignore pattern: add inline for DirectQuery/NoCaching on direct DB calls

## Files to Create

| File | Purpose |
|------|---------|
| `includes/Features/GeoBlock.php` | Core: settings, generate, render, publish hook |
| `includes/Admin/GeoPage.php` | Admin settings page class |
| `includes/Admin/GeoEditorBox.php` | Post editor meta box + AJAX |
| `includes/Admin/views/geo.php` | Settings page view template |
| `assets/geo-editor.js` | Meta box JS (generate/clear/lock) |
| `assets/geo-frontend.css` | Scoped frontend CSS for `.bre-geo` |

## Files to Modify

| File | Change |
|------|--------|
| `includes/Core.php` | Add requires + register calls |
| `includes/Admin/AdminMenu.php` | Add GEO submenu entry |

---

## Task 1: GeoBlock — Settings Foundation

**Files:**
- Create: `includes/Features/GeoBlock.php`

**Step 1: Create the file with namespace, guard, and getSettings()**

```php
<?php
namespace BavarianRankEngine\Features;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use BavarianRankEngine\Admin\SettingsPage;
use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Helpers\TokenEstimator;

class GeoBlock {
    public const OPTION_KEY = 'bre_geo_settings';

    // Post meta keys
    public const META_ENABLED   = '_bre_geo_enabled';
    public const META_LOCK      = '_bre_geo_lock';
    public const META_GENERATED = '_bre_geo_last_generated_at';
    public const META_SUMMARY   = '_bre_geo_summary';
    public const META_BULLETS   = '_bre_geo_bullets';
    public const META_FAQ       = '_bre_geo_faq';
    public const META_ADDON     = '_bre_geo_prompt_addon';

    // Fluff phrases to detect in AI output
    private const FLUFF_PHRASES = [
        'ultimativ', 'gamechanger', 'in diesem artikel', 'wir schauen uns an',
        'in this article', 'ultimate guide', 'game changer', 'game-changer',
    ];

    public static function getSettings(): array {
        $defaults = [
            'enabled'           => false,
            'mode'              => 'auto_on_publish',
            'post_types'        => [ 'post', 'page' ],
            'position'          => 'after_first_p',
            'output_style'      => 'details_collapsible',
            'title'             => 'Schnellüberblick',
            'label_summary'     => 'Kurzfassung',
            'label_bullets'     => 'Kernaussagen',
            'label_faq'         => 'FAQ',
            'minimal_css'       => true,
            'custom_css'        => '',
            'prompt_default'    => self::getDefaultPrompt(),
            'word_threshold'    => 350,
            'regen_on_update'   => false,
            'allow_prompt_addon'=> false,
        ];
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
    }

    public static function getDefaultPrompt(): string {
        return 'Analysiere den folgenden Artikel und erstelle einen strukturierten Schnellüberblick.' . "\n"
            . 'Antworte ausschließlich mit einem validen JSON-Objekt (keine Markdown-Code-Blöcke, kein Text davor oder danach).' . "\n\n"
            . 'Sprache: {language}' . "\n"
            . 'Artikel-Titel: {title}' . "\n\n"
            . 'Regeln:' . "\n"
            . '- summary: 40–90 Wörter, neutral, sachlich, keine Werbung, keine Superlative.' . "\n"
            . '- bullets: 3–7 kurze Kernaussagen. Keine Wiederholungen aus der summary.' . "\n"
            . '- faq: 0–5 Frage-Antwort-Paare, NUR wenn der Artikel echte Fragen beantwortet. Sonst leeres Array [].' . "\n"
            . '- Nichts erfinden. Keine Keyword-Häufung. Kurze, klare Sätze.' . "\n"
            . '- Keine Phrasen wie "In diesem Artikel", "ultimativ", "Gamechanger".' . "\n\n"
            . 'JSON-Format (exakt):' . "\n"
            . '{"summary":"...","bullets":["...","..."],"faq":[{"q":"...","a":"..."}]}' . "\n\n"
            . 'Artikel-Inhalt:' . "\n"
            . '{content}';
    }
}
```

**Step 2: Verify the file is valid PHP**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Features/GeoBlock.php
```
Expected: `No syntax errors detected`

---

## Task 2: GeoBlock — AI Generation and Quality Gate

**Files:**
- Modify: `includes/Features/GeoBlock.php` (add methods)

**Step 1: Add generate() and helper methods**

Add these methods to the `GeoBlock` class body:

```php
    public function generate( int $post_id, bool $force = false ): bool {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $settings = self::getSettings();

        // Check lock
        if ( ! $force && get_post_meta( $post_id, self::META_LOCK, true ) ) {
            return false;
        }

        $global   = SettingsPage::getSettings();
        $provider = ProviderRegistry::instance()->get( $global['provider'] );
        $api_key  = $global['api_keys'][ $global['provider'] ] ?? '';

        if ( ! $provider || empty( $api_key ) ) {
            return false;
        }

        $model   = $global['models'][ $global['provider'] ] ?? array_key_first( $provider->getModels() );
        $content = wp_strip_all_tags( do_shortcode( $post->post_content ) );

        // Token-limit the content input (reuse existing helper)
        $content = TokenEstimator::truncate( $content, 2000 );

        $word_count    = str_word_count( $content );
        $force_no_faq  = $word_count < (int) $settings['word_threshold'];
        $addon         = $settings['allow_prompt_addon']
                         ? sanitize_textarea_field( get_post_meta( $post_id, self::META_ADDON, true ) )
                         : '';
        $prompt = $this->buildPrompt( $post, $content, $settings, $addon, $force_no_faq );

        try {
            $raw    = $provider->generateText( $prompt, $api_key, $model, 800 );
            $parsed = $this->parseResponse( $raw );
            if ( null === $parsed ) {
                return false;
            }
            $data = $this->qualityGate( $parsed, $force_no_faq );
            $this->saveMeta( $post_id, $data );
            return true;
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( '[BRE GEO] Generation failed for post ' . $post_id . ': ' . $e->getMessage() );
            }
            return false;
        }
    }

    private function buildPrompt( \WP_Post $post, string $content, array $settings, string $addon, bool $force_no_faq ): string {
        $locale_map = [
            'de_DE' => 'Deutsch', 'de_AT' => 'Deutsch', 'de_CH' => 'Deutsch',
            'en_US' => 'English', 'en_GB' => 'English',
            'fr_FR' => 'Français', 'es_ES' => 'Español',
        ];

        $language = $locale_map[ get_locale() ] ?? 'Deutsch';
        if ( function_exists( 'pll_get_post_language' ) ) {
            $lang = pll_get_post_language( $post->ID, 'name' );
            if ( $lang ) {
                $language = $lang;
            }
        } elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            $language = ICL_LANGUAGE_CODE;
        }

        $prompt = $settings['prompt_default'];
        $prompt = str_replace( '{title}', $post->post_title, $prompt );
        $prompt = str_replace( '{content}', $content, $prompt );
        $prompt = str_replace( '{language}', $language, $prompt );

        if ( $force_no_faq ) {
            $prompt .= "\n\nWICHTIG: Setze faq immer auf ein leeres Array: []";
        }
        if ( ! empty( $addon ) ) {
            $prompt .= "\n\nZusätzliche Anweisung: " . $addon;
        }

        return $prompt;
    }

    private function parseResponse( string $raw ): ?array {
        // Strip markdown code fences if present
        $raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
        $raw = preg_replace( '/\s*```$/', '', $raw );
        $raw = trim( $raw );

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return null;
        }
        // Require at minimum a summary
        if ( empty( $data['summary'] ) || ! is_string( $data['summary'] ) ) {
            return null;
        }
        return $data;
    }

    private function qualityGate( array $data, bool $force_no_faq ): array {
        $summary = trim( $data['summary'] ?? '' );
        $bullets = array_values( array_filter( (array) ( $data['bullets'] ?? [] ), 'is_string' ) );
        $faq     = $force_no_faq ? [] : array_values( array_filter( (array) ( $data['faq'] ?? [] ), function ( $item ) {
            return is_array( $item ) && ! empty( $item['q'] ) && ! empty( $item['a'] );
        } ) );

        // Hard bounds
        $word_count = str_word_count( $summary );
        if ( $word_count < 10 || $word_count > 140 ) {
            // Trim to first 140 words if too long
            if ( $word_count > 140 ) {
                $words   = explode( ' ', $summary );
                $summary = implode( ' ', array_slice( $words, 0, 140 ) );
            }
        }

        // Trim bullets/FAQ to soft max
        if ( count( $bullets ) > 7 ) {
            $bullets = array_slice( $bullets, 0, 7 );
        }
        if ( count( $faq ) > 5 ) {
            $faq = array_slice( $faq, 0, 5 );
        }

        return [
            'summary' => $summary,
            'bullets' => $bullets,
            'faq'     => $faq,
        ];
    }

    public function saveMeta( int $post_id, array $data ): void {
        update_post_meta( $post_id, self::META_SUMMARY, sanitize_text_field( $data['summary'] ?? '' ) );
        update_post_meta( $post_id, self::META_BULLETS, wp_json_encode( array_map( 'sanitize_text_field', $data['bullets'] ?? [] ) ) );

        $faq_clean = array_map( function ( $item ) {
            return [
                'q' => sanitize_text_field( $item['q'] ?? '' ),
                'a' => sanitize_text_field( $item['a'] ?? '' ),
            ];
        }, $data['faq'] ?? [] );
        update_post_meta( $post_id, self::META_FAQ, wp_json_encode( $faq_clean ) );
        update_post_meta( $post_id, self::META_GENERATED, time() );
    }

    public static function getMeta( int $post_id ): array {
        $summary = get_post_meta( $post_id, self::META_SUMMARY, true ) ?: '';
        $bullets = json_decode( get_post_meta( $post_id, self::META_BULLETS, true ) ?: '[]', true );
        $faq     = json_decode( get_post_meta( $post_id, self::META_FAQ, true ) ?: '[]', true );
        return [
            'summary' => is_string( $summary ) ? $summary : '',
            'bullets' => is_array( $bullets ) ? $bullets : [],
            'faq'     => is_array( $faq ) ? $faq : [],
        ];
    }
```

**Step 2: Syntax check**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Features/GeoBlock.php
```

---

## Task 3: GeoBlock — Frontend Rendering and Content Filter

**Files:**
- Modify: `includes/Features/GeoBlock.php` (add register + render methods)
- Create: `assets/geo-frontend.css`

**Step 1: Add register(), content filter, and renderBlock() to GeoBlock**

```php
    public function register(): void {
        $settings = self::getSettings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }
        if ( $settings['output_style'] !== 'store_only_no_frontend' ) {
            add_filter( 'the_content', [ $this, 'injectBlock' ] );
        }
        if ( $settings['minimal_css'] ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueueCss' ] );
        }
        if ( ! empty( $settings['custom_css'] ) ) {
            add_action( 'wp_head', [ $this, 'inlineCustomCss' ] );
        }
        // Publish hook
        add_action( 'transition_post_status', [ $this, 'onStatusTransition' ], 20, 3 );
        // Update hook
        if ( ! empty( $settings['regen_on_update'] ) ) {
            add_action( 'save_post', [ $this, 'onSavePost' ], 20, 2 );
        }
    }

    public function enqueueCss(): void {
        if ( ! is_singular() ) {
            return;
        }
        wp_enqueue_style( 'bre-geo-frontend', BRE_URL . 'assets/geo-frontend.css', [], BRE_VERSION );
    }

    public function inlineCustomCss(): void {
        if ( ! is_singular() ) {
            return;
        }
        $settings = self::getSettings();
        $css      = $settings['custom_css'] ?? '';
        if ( empty( $css ) ) {
            return;
        }
        // Output is scoped — only safe CSS properties inside .bre-geo
        echo '<style>.bre-geo{' . esc_html( $css ) . '}</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function injectBlock( string $content ): string {
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $content;
        }

        // Per-post enabled override (nullable: '' = follow global, '1' = on, '0' = off)
        $per_post = get_post_meta( $post_id, self::META_ENABLED, true );
        if ( $per_post === '0' ) {
            return $content;
        }

        $meta = self::getMeta( $post_id );
        if ( empty( $meta['summary'] ) && empty( $meta['bullets'] ) ) {
            return $content;
        }

        $block    = $this->renderBlock( $meta );
        $settings = self::getSettings();

        switch ( $settings['position'] ) {
            case 'top':
                return $block . $content;
            case 'bottom':
                return $content . $block;
            case 'after_first_p':
            default:
                $parts = preg_split( '/(<\/p>)/i', $content, 2, PREG_SPLIT_DELIM_CAPTURE );
                if ( count( $parts ) >= 3 ) {
                    return $parts[0] . $parts[1] . $block . $parts[2];
                }
                return $block . $content;
        }
    }

    private function renderBlock( array $meta ): string {
        $settings = self::getSettings();
        $style    = $settings['output_style'];

        $title          = esc_html( $settings['title'] );
        $label_summary  = esc_html( $settings['label_summary'] );
        $label_bullets  = esc_html( $settings['label_bullets'] );
        $label_faq      = esc_html( $settings['label_faq'] );

        $inner = '';

        if ( ! empty( $meta['summary'] ) ) {
            $inner .= '<div class="bre-geo__section bre-geo__summary">'
                    . '<h3>' . $label_summary . '</h3>'
                    . '<p>' . esc_html( $meta['summary'] ) . '</p>'
                    . '</div>';
        }

        if ( ! empty( $meta['bullets'] ) ) {
            $items = '';
            foreach ( $meta['bullets'] as $bullet ) {
                $items .= '<li>' . esc_html( $bullet ) . '</li>';
            }
            $inner .= '<div class="bre-geo__section bre-geo__bullets">'
                    . '<h3>' . $label_bullets . '</h3>'
                    . '<ul>' . $items . '</ul>'
                    . '</div>';
        }

        if ( ! empty( $meta['faq'] ) ) {
            $pairs = '';
            foreach ( $meta['faq'] as $item ) {
                $pairs .= '<dt>' . esc_html( $item['q'] ) . '</dt>'
                        . '<dd>' . esc_html( $item['a'] ) . '</dd>';
            }
            $inner .= '<div class="bre-geo__section bre-geo__faq">'
                    . '<h3>' . $label_faq . '</h3>'
                    . '<dl>' . $pairs . '</dl>'
                    . '</div>';
        }

        if ( $style === 'open_always' ) {
            return '<details class="bre-geo" data-bre="geo" open>'
                 . '<summary><span class="bre-geo__title">' . $title . '</span></summary>'
                 . $inner
                 . '</details>';
        }

        return '<details class="bre-geo" data-bre="geo">'
             . '<summary><span class="bre-geo__title">' . $title . '</span></summary>'
             . $inner
             . '</details>';
    }

    public function onStatusTransition( string $new_status, string $old_status, \WP_Post $post ): void {
        if ( $new_status !== 'publish' ) {
            return;
        }
        $settings = self::getSettings();
        if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
            return;
        }
        $mode = $settings['mode'];
        if ( $mode === 'manual_only' ) {
            return;
        }
        if ( $mode === 'hybrid' ) {
            $meta = self::getMeta( $post->ID );
            if ( ! empty( $meta['summary'] ) ) {
                return;
            }
        }
        $this->generate( $post->ID );
    }

    public function onSavePost( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( $post->post_status !== 'publish' ) {
            return;
        }
        $settings = self::getSettings();
        if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
            return;
        }
        $this->generate( $post_id );
    }
```

**Step 2: Create `assets/geo-frontend.css`**

```css
/* Bavarian Rank Engine — GEO Block (scoped to .bre-geo) */
.bre-geo {
    margin: 1.5em 0;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #fafafa;
    padding: 0;
}

.bre-geo summary {
    cursor: pointer;
    padding: 0.75em 1em;
    font-weight: 600;
    list-style: none;
    display: flex;
    align-items: center;
}

.bre-geo summary::-webkit-details-marker { display: none; }

.bre-geo summary::before {
    content: '▶';
    display: inline-block;
    margin-right: 0.5em;
    font-size: 0.7em;
    transition: transform 0.2s;
}

.bre-geo[open] summary::before { transform: rotate(90deg); }

.bre-geo__title { flex: 1; }

.bre-geo__section {
    padding: 0.75em 1em;
    border-top: 1px solid #e0e0e0;
}

.bre-geo__section h3 {
    font-size: 0.8em;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #666;
    margin: 0 0 0.5em;
}

.bre-geo__bullets ul {
    margin: 0;
    padding-left: 1.25em;
}

.bre-geo__bullets li { margin-bottom: 0.25em; }

.bre-geo__faq dl { margin: 0; }

.bre-geo__faq dt {
    font-weight: 600;
    margin-top: 0.5em;
}

.bre-geo__faq dd {
    margin-left: 0;
    color: #444;
}
```

**Step 3: Syntax check**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Features/GeoBlock.php
```

---

## Task 4: GeoPage — Admin Settings Class

**Files:**
- Create: `includes/Admin/GeoPage.php`

**Step 1: Create the file**

```php
<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use BavarianRankEngine\Features\GeoBlock;

class GeoPage {
    public function register(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_settings(): void {
        register_setting(
            'bre_geo',
            GeoBlock::OPTION_KEY,
            [ 'sanitize_callback' => [ $this, 'sanitize' ] ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'bavarian-rank_page_bre-geo' ) {
            return;
        }
        wp_enqueue_style( 'bre-admin', BRE_URL . 'assets/admin.css', [], BRE_VERSION );
    }

    public function sanitize( mixed $input ): array {
        $input = is_array( $input ) ? $input : [];
        $clean = [];

        $clean['enabled']            = ! empty( $input['enabled'] );
        $clean['regen_on_update']    = ! empty( $input['regen_on_update'] );
        $clean['minimal_css']        = ! empty( $input['minimal_css'] );
        $clean['allow_prompt_addon'] = ! empty( $input['allow_prompt_addon'] );

        $allowed_modes = [ 'auto_on_publish', 'manual_only', 'hybrid' ];
        $clean['mode'] = in_array( $input['mode'] ?? '', $allowed_modes, true )
            ? $input['mode'] : 'auto_on_publish';

        $allowed_positions = [ 'after_first_p', 'top', 'bottom' ];
        $clean['position'] = in_array( $input['position'] ?? '', $allowed_positions, true )
            ? $input['position'] : 'after_first_p';

        $allowed_styles = [ 'details_collapsible', 'open_always', 'store_only_no_frontend' ];
        $clean['output_style'] = in_array( $input['output_style'] ?? '', $allowed_styles, true )
            ? $input['output_style'] : 'details_collapsible';

        $clean['title']          = sanitize_text_field( $input['title'] ?? 'Schnellüberblick' );
        $clean['label_summary']  = sanitize_text_field( $input['label_summary'] ?? 'Kurzfassung' );
        $clean['label_bullets']  = sanitize_text_field( $input['label_bullets'] ?? 'Kernaussagen' );
        $clean['label_faq']      = sanitize_text_field( $input['label_faq'] ?? 'FAQ' );
        $clean['custom_css']     = sanitize_textarea_field( $input['custom_css'] ?? '' );
        $clean['prompt_default'] = sanitize_textarea_field(
            ! empty( $input['prompt_default'] ) ? $input['prompt_default'] : GeoBlock::getDefaultPrompt()
        );
        $clean['word_threshold'] = max( 50, (int) ( $input['word_threshold'] ?? 350 ) );

        $all_post_types     = array_keys( get_post_types( [ 'public' => true ] ) );
        $clean['post_types'] = array_values(
            array_intersect(
                array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [] ) ),
                $all_post_types
            )
        );
        if ( empty( $clean['post_types'] ) ) {
            $clean['post_types'] = [ 'post', 'page' ];
        }

        return $clean;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings   = GeoBlock::getSettings();
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        include BRE_DIR . 'includes/Admin/views/geo.php';
    }
}
```

**Step 2: Syntax check**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Admin/GeoPage.php
```

---

## Task 5: Admin Settings View (geo.php)

**Files:**
- Create: `includes/Admin/views/geo.php`

**Step 1: Create the view template**

```php
<?php if ( ! defined( 'ABSPATH' ) ) {
    exit;} ?>
<div class="wrap bre-settings">
    <h1><?php esc_html_e( 'GEO Schnellüberblick', 'bavarian-rank-engine' ); ?></h1>

    <?php settings_errors( 'bre_geo' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'bre_geo' ); ?>

        <h2><?php esc_html_e( 'Aktivierung', 'bavarian-rank-engine' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'GEO Block aktivieren', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="bre_geo_settings[enabled]" value="1"
                               <?php checked( $settings['enabled'], true ); ?>>
                        <?php esc_html_e( 'Schnellüberblick-Block im Frontend ausgeben', 'bavarian-rank-engine' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Modus', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <select name="bre_geo_settings[mode]">
                        <option value="auto_on_publish" <?php selected( $settings['mode'], 'auto_on_publish' ); ?>>
                            <?php esc_html_e( 'Auto bei Publish/Update (empfohlen)', 'bavarian-rank-engine' ); ?>
                        </option>
                        <option value="hybrid" <?php selected( $settings['mode'], 'hybrid' ); ?>>
                            <?php esc_html_e( 'Hybrid: Auto nur wenn Felder leer', 'bavarian-rank-engine' ); ?>
                        </option>
                        <option value="manual_only" <?php selected( $settings['mode'], 'manual_only' ); ?>>
                            <?php esc_html_e( 'Nur manuell (Editor-Button)', 'bavarian-rank-engine' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Post-Types', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <?php foreach ( $post_types as $pt_slug => $pt_obj ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
                    <label style="margin-right:15px;">
                        <input type="checkbox" name="bre_geo_settings[post_types][]"
                               value="<?php echo esc_attr( $pt_slug ); ?>"
                               <?php checked( in_array( $pt_slug, $settings['post_types'], true ), true ); ?>>
                        <?php echo esc_html( $pt_obj->labels->singular_name ); ?>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Bei Update neu generieren', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="bre_geo_settings[regen_on_update]" value="1"
                               <?php checked( $settings['regen_on_update'], true ); ?>>
                        <?php esc_html_e( 'Bei jedem Speichern eines publizierten Beitrags neu generieren', 'bavarian-rank-engine' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Wortschwellwert für FAQ', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <input type="number" name="bre_geo_settings[word_threshold]"
                           value="<?php echo esc_attr( $settings['word_threshold'] ); ?>"
                           min="50" max="2000" style="width:80px;">
                    <p class="description">
                        <?php esc_html_e( 'Unter dieser Wortanzahl wird keine FAQ generiert. Standard: 350', 'bavarian-rank-engine' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Ausgabe', 'bavarian-rank-engine' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Position', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <select name="bre_geo_settings[position]">
                        <option value="after_first_p" <?php selected( $settings['position'], 'after_first_p' ); ?>>
                            <?php esc_html_e( 'Nach dem ersten Absatz (Standard)', 'bavarian-rank-engine' ); ?>
                        </option>
                        <option value="top" <?php selected( $settings['position'], 'top' ); ?>>
                            <?php esc_html_e( 'Anfang des Beitrags', 'bavarian-rank-engine' ); ?>
                        </option>
                        <option value="bottom" <?php selected( $settings['position'], 'bottom' ); ?>>
                            <?php esc_html_e( 'Ende des Beitrags', 'bavarian-rank-engine' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Ausgabe-Stil', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <select name="bre_geo_settings[output_style]">
                        <option value="details_collapsible" <?php selected( $settings['output_style'], 'details_collapsible' ); ?>>
                            <?php esc_html_e( 'Einklappbar <details> (Standard)', 'bavarian-rank-engine' ); ?>
                        </option>
                        <option value="open_always" <?php selected( $settings['output_style'], 'open_always' ); ?>>
                            <?php esc_html_e( 'Immer aufgeklappt', 'bavarian-rank-engine' ); ?>
                        </option>
                        <option value="store_only_no_frontend" <?php selected( $settings['output_style'], 'store_only_no_frontend' ); ?>>
                            <?php esc_html_e( 'Nur speichern, nicht ausgeben', 'bavarian-rank-engine' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Labels', 'bavarian-rank-engine' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Block-Titel', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <input type="text" name="bre_geo_settings[title]"
                           value="<?php echo esc_attr( $settings['title'] ); ?>"
                           class="regular-text" placeholder="Schnellüberblick">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Label Kurzfassung', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <input type="text" name="bre_geo_settings[label_summary]"
                           value="<?php echo esc_attr( $settings['label_summary'] ); ?>"
                           class="regular-text" placeholder="Kurzfassung">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Label Kernaussagen', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <input type="text" name="bre_geo_settings[label_bullets]"
                           value="<?php echo esc_attr( $settings['label_bullets'] ); ?>"
                           class="regular-text" placeholder="Kernaussagen">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Label FAQ', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <input type="text" name="bre_geo_settings[label_faq]"
                           value="<?php echo esc_attr( $settings['label_faq'] ); ?>"
                           class="regular-text" placeholder="FAQ">
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Styling', 'bavarian-rank-engine' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Minimal-CSS laden', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="bre_geo_settings[minimal_css]" value="1"
                               <?php checked( $settings['minimal_css'], true ); ?>>
                        <?php esc_html_e( 'Basis-Stylesheet für .bre-geo auf dem Frontend laden', 'bavarian-rank-engine' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Custom CSS', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <textarea name="bre_geo_settings[custom_css]" rows="6" class="large-text code"><?php
                        echo esc_textarea( $settings['custom_css'] );
                    ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'Wird automatisch auf .bre-geo{...} begrenzt. Nur CSS-Eigenschaften eingeben, keinen Selektor.', 'bavarian-rank-engine' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'KI Prompt', 'bavarian-rank-engine' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Standard-Prompt', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <textarea name="bre_geo_settings[prompt_default]" rows="12" class="large-text code"><?php
                        echo esc_textarea( $settings['prompt_default'] );
                    ?></textarea>
                    <p class="description">
                        <?php esc_html_e( 'Variablen: {title}, {content}, {language}', 'bavarian-rank-engine' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Prompt-Zusatz pro Beitrag', 'bavarian-rank-engine' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="bre_geo_settings[allow_prompt_addon]" value="1"
                               <?php checked( $settings['allow_prompt_addon'], true ); ?>>
                        <?php esc_html_e( 'Autoren dürfen im Editor einen Prompt-Zusatz pro Beitrag eingeben', 'bavarian-rank-engine' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Einstellungen speichern', 'bavarian-rank-engine' ) ); ?>
    </form>

    <hr>
    <p style="color:#999;font-size:12px;">
        Bavarian Rank Engine <?php echo esc_html( BRE_VERSION ); ?> &mdash;
        <?php esc_html_e( 'developed with', 'bavarian-rank-engine' ); ?> ♥
        <a href="https://donau2space.de" target="_blank" rel="noopener">Donau2Space.de</a>
    </p>
</div>
```

**Step 2: Verify file exists and has no PHP errors (it's a template, use php -l workaround)**

```bash
php -r "define('ABSPATH','x'); include '/var/www/dev/plugins/bre-dev/includes/Admin/views/geo.php';" 2>&1 | grep -i error || echo "OK"
```

---

## Task 6: GeoEditorBox — Post Editor Meta Box

**Files:**
- Create: `includes/Admin/GeoEditorBox.php`

**Step 1: Create the file**

```php
<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use BavarianRankEngine\Features\GeoBlock;

class GeoEditorBox {
    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
        add_action( 'save_post', [ $this, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_bre_geo_generate', [ $this, 'ajax_generate' ] );
        add_action( 'wp_ajax_bre_geo_clear', [ $this, 'ajax_clear' ] );
    }

    public function add_boxes(): void {
        $settings   = GeoBlock::getSettings();
        foreach ( $settings['post_types'] as $pt ) {
            add_meta_box(
                'bre_geo_box',
                __( 'GEO Schnellüberblick (BRE)', 'bavarian-rank-engine' ),
                [ $this, 'render' ],
                $pt,
                'normal',
                'default'
            );
        }
    }

    public function render( \WP_Post $post ): void {
        $settings      = GeoBlock::getSettings();
        $meta          = GeoBlock::getMeta( $post->ID );
        $enabled       = get_post_meta( $post->ID, GeoBlock::META_ENABLED, true );
        $lock          = (bool) get_post_meta( $post->ID, GeoBlock::META_LOCK, true );
        $generated_at  = get_post_meta( $post->ID, GeoBlock::META_GENERATED, true );
        $prompt_addon  = get_post_meta( $post->ID, GeoBlock::META_ADDON, true ) ?: '';
        $global        = \BavarianRankEngine\Admin\SettingsPage::getSettings();
        $has_api_key   = ! empty( $global['api_keys'][ $global['provider'] ] ?? '' );

        wp_nonce_field( 'bre_geo_save_' . $post->ID, 'bre_geo_nonce' );
        ?>
        <div id="bre-geo-box" data-post-id="<?php echo esc_attr( $post->ID ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'bre_admin' ) ); ?>">

            <p style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <label>
                    <input type="checkbox" name="bre_geo_enabled" value="1"
                           <?php checked( $enabled, '1' ); ?>>
                    <?php esc_html_e( 'GEO-Block für diesen Beitrag aktiv', 'bavarian-rank-engine' ); ?>
                </label>
                <label>
                    <input type="checkbox" name="bre_geo_lock" value="1" id="bre-geo-lock"
                           <?php checked( $lock, true ); ?>>
                    <?php esc_html_e( 'Auto-Regeneration sperren', 'bavarian-rank-engine' ); ?>
                </label>
                <?php if ( $generated_at ) : ?>
                <span style="font-size:11px;color:#666;">
                    <?php
                    // translators: %s = human-readable date
                    printf( esc_html__( 'Generiert: %s', 'bavarian-rank-engine' ), esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', (int) $generated_at ) ) );
                    ?>
                </span>
                <?php endif; ?>
            </p>

            <?php if ( $has_api_key ) : ?>
            <p>
                <button type="button" class="button" id="bre-geo-generate">
                    <?php empty( $meta['summary'] )
                        ? esc_html_e( 'Jetzt generieren', 'bavarian-rank-engine' )
                        : esc_html_e( 'Neu generieren', 'bavarian-rank-engine' ); ?>
                </button>
                <?php if ( ! empty( $meta['summary'] ) ) : ?>
                <button type="button" class="button" id="bre-geo-clear" style="margin-left:6px;">
                    <?php esc_html_e( 'Leeren', 'bavarian-rank-engine' ); ?>
                </button>
                <?php endif; ?>
                <span id="bre-geo-status" style="margin-left:10px;font-size:12px;"></span>
            </p>
            <?php endif; ?>

            <p style="margin-bottom:4px;">
                <label for="bre-geo-summary"><strong><?php esc_html_e( 'Kurzfassung', 'bavarian-rank-engine' ); ?></strong></label>
            </p>
            <textarea id="bre-geo-summary" name="bre_geo_summary" rows="3"
                      style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( $meta['summary'] ); ?></textarea>

            <p style="margin-bottom:4px;margin-top:10px;">
                <label for="bre-geo-bullets"><strong><?php esc_html_e( 'Kernaussagen', 'bavarian-rank-engine' ); ?></strong></label>
                <span style="font-size:11px;color:#666;margin-left:8px;"><?php esc_html_e( '(eine pro Zeile)', 'bavarian-rank-engine' ); ?></span>
            </p>
            <textarea id="bre-geo-bullets" name="bre_geo_bullets" rows="5"
                      style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( implode( "\n", $meta['bullets'] ) ); ?></textarea>

            <p style="margin-bottom:4px;margin-top:10px;">
                <label for="bre-geo-faq"><strong><?php esc_html_e( 'FAQ', 'bavarian-rank-engine' ); ?></strong></label>
                <span style="font-size:11px;color:#666;margin-left:8px;"><?php esc_html_e( '(Format: Frage? | Antwort — eine pro Zeile)', 'bavarian-rank-engine' ); ?></span>
            </p>
            <textarea id="bre-geo-faq" name="bre_geo_faq" rows="4"
                      style="width:100%;box-sizing:border-box;"><?php
                $faq_lines = array_map( function ( $item ) {
                    return ( $item['q'] ?? '' ) . ' | ' . ( $item['a'] ?? '' );
                }, $meta['faq'] );
                echo esc_textarea( implode( "\n", $faq_lines ) );
            ?></textarea>

            <?php if ( $settings['allow_prompt_addon'] ) : ?>
            <p style="margin-bottom:4px;margin-top:10px;">
                <label for="bre-geo-addon"><strong><?php esc_html_e( 'Prompt-Zusatz (optional)', 'bavarian-rank-engine' ); ?></strong></label>
            </p>
            <textarea id="bre-geo-addon" name="bre_geo_prompt_addon" rows="2"
                      style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( $prompt_addon ); ?></textarea>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['bre_geo_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bre_geo_nonce'] ) ), 'bre_geo_save_' . $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Per-post enabled flag ('' = follow global, '1' = on, '0' = off)
        $enabled = isset( $_POST['bre_geo_enabled'] ) ? '1' : '0';
        update_post_meta( $post_id, GeoBlock::META_ENABLED, $enabled );

        $lock = isset( $_POST['bre_geo_lock'] ) ? '1' : '';
        update_post_meta( $post_id, GeoBlock::META_LOCK, $lock );

        // Manual field edits
        $summary = sanitize_text_field( wp_unslash( $_POST['bre_geo_summary'] ?? '' ) );
        update_post_meta( $post_id, GeoBlock::META_SUMMARY, $summary );

        $raw_bullets = sanitize_textarea_field( wp_unslash( $_POST['bre_geo_bullets'] ?? '' ) );
        $bullets     = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_bullets ) ) ) );
        update_post_meta( $post_id, GeoBlock::META_BULLETS, wp_json_encode( $bullets ) );

        $raw_faq = sanitize_textarea_field( wp_unslash( $_POST['bre_geo_faq'] ?? '' ) );
        $faq     = [];
        foreach ( array_filter( array_map( 'trim', explode( "\n", $raw_faq ) ) ) as $line ) {
            $parts = explode( '|', $line, 2 );
            if ( count( $parts ) === 2 ) {
                $faq[] = [ 'q' => trim( $parts[0] ), 'a' => trim( $parts[1] ) ];
            }
        }
        update_post_meta( $post_id, GeoBlock::META_FAQ, wp_json_encode( $faq ) );

        if ( isset( $_POST['bre_geo_prompt_addon'] ) ) {
            update_post_meta( $post_id, GeoBlock::META_ADDON, sanitize_textarea_field( wp_unslash( $_POST['bre_geo_prompt_addon'] ) ) );
        }
    }

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        wp_enqueue_script(
            'bre-geo-editor',
            BRE_URL . 'assets/geo-editor.js',
            [ 'jquery' ],
            BRE_VERSION,
            true
        );
    }

    public function ajax_generate(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
        }

        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( __( 'Post not found.', 'bavarian-rank-engine' ) );
        }

        $geo = new GeoBlock();
        if ( $geo->generate( $post_id, true ) ) {
            $meta = GeoBlock::getMeta( $post_id );
            wp_send_json_success( [
                'summary' => $meta['summary'],
                'bullets' => $meta['bullets'],
                'faq'     => $meta['faq'],
            ] );
        } else {
            wp_send_json_error( __( 'Generierung fehlgeschlagen. API-Key und Provider-Einstellungen prüfen.', 'bavarian-rank-engine' ) );
        }
    }

    public function ajax_clear(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'bavarian-rank-engine' ) );
        }

        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid post ID' );
        }

        delete_post_meta( $post_id, GeoBlock::META_SUMMARY );
        delete_post_meta( $post_id, GeoBlock::META_BULLETS );
        delete_post_meta( $post_id, GeoBlock::META_FAQ );
        delete_post_meta( $post_id, GeoBlock::META_GENERATED );
        wp_send_json_success();
    }
}
```

**Step 2: Syntax check**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Admin/GeoEditorBox.php
```

---

## Task 7: Editor JavaScript (geo-editor.js)

**Files:**
- Create: `assets/geo-editor.js`

**Step 1: Create the JS file**

```js
/* global jQuery, ajaxurl */
jQuery( function ( $ ) {
    var $box      = $( '#bre-geo-box' );
    if ( ! $box.length ) return;

    var postId    = $box.data( 'post-id' );
    var nonce     = $box.data( 'nonce' );
    var $generate = $( '#bre-geo-generate' );
    var $clear    = $( '#bre-geo-clear' );
    var $status   = $( '#bre-geo-status' );
    var $summary  = $( '#bre-geo-summary' );
    var $bullets  = $( '#bre-geo-bullets' );
    var $faq      = $( '#bre-geo-faq' );
    var $lock     = $( '#bre-geo-lock' );

    function setStatus( msg, isError ) {
        $status.text( msg ).css( 'color', isError ? '#dc3232' : '#46b450' );
        if ( msg ) {
            setTimeout( function () { $status.text( '' ); }, 4000 );
        }
    }

    function populateFields( data ) {
        $summary.val( data.summary || '' );
        $bullets.val( ( data.bullets || [] ).join( '\n' ) );
        var faqLines = ( data.faq || [] ).map( function ( item ) {
            return item.q + ' | ' + item.a;
        } );
        $faq.val( faqLines.join( '\n' ) );
        // Auto-set lock when fields are populated by AI
        $lock.prop( 'checked', false );
    }

    // Track manual edits → set lock automatically
    $summary.add( $bullets ).add( $faq ).on( 'input', function () {
        $lock.prop( 'checked', true );
    } );

    if ( $generate.length ) {
        $generate.on( 'click', function () {
            $generate.prop( 'disabled', true ).text( '…' );
            setStatus( '' );
            $.post( ajaxurl, {
                action:  'bre_geo_generate',
                nonce:   nonce,
                post_id: postId,
            } ).done( function ( res ) {
                if ( res.success ) {
                    populateFields( res.data );
                    setStatus( 'Generiert ✓', false );
                    $generate.text( 'Neu generieren' );
                } else {
                    setStatus( res.data || 'Fehler', true );
                }
            } ).fail( function () {
                setStatus( 'Verbindungsfehler', true );
            } ).always( function () {
                $generate.prop( 'disabled', false );
            } );
        } );
    }

    if ( $clear.length ) {
        $clear.on( 'click', function () {
            if ( ! window.confirm( 'GEO-Felder wirklich leeren?' ) ) return;
            $clear.prop( 'disabled', true );
            $.post( ajaxurl, {
                action:  'bre_geo_clear',
                nonce:   nonce,
                post_id: postId,
            } ).done( function ( res ) {
                if ( res.success ) {
                    $summary.val( '' );
                    $bullets.val( '' );
                    $faq.val( '' );
                    $lock.prop( 'checked', false );
                    setStatus( 'Geleert', false );
                }
            } ).always( function () {
                $clear.prop( 'disabled', false );
            } );
        } );
    }
} );
```

---

## Task 8: Wire Into Core and AdminMenu

**Files:**
- Modify: `includes/Core.php`
- Modify: `includes/Admin/AdminMenu.php`

**Step 1: Add to Core::load_dependencies() — after CrawlerLog line**

In `load_dependencies()`, after the `CrawlerLog` require line, add:

```php
        require_once BRE_DIR . 'includes/Features/GeoBlock.php';
        require_once BRE_DIR . 'includes/Admin/GeoPage.php';
        require_once BRE_DIR . 'includes/Admin/GeoEditorBox.php';
```

**Step 2: Add to Core::register_hooks() — after CrawlerLog register**

In `register_hooks()`, after `( new Features\CrawlerLog() )->register();`, add:

```php
        ( new Features\GeoBlock() )->register();
```

In the `is_admin()` block, after `( new Admin\RobotsPage() )->register();`, add:

```php
            ( new Admin\GeoPage() )->register();
            ( new Admin\GeoEditorBox() )->register();
```

**Step 3: Add submenu in AdminMenu::add_menus() — after robots.txt entry**

```php
        add_submenu_page(
            'bavarian-rank',
            __( 'GEO Schnellüberblick', 'bavarian-rank-engine' ),
            __( 'GEO Block', 'bavarian-rank-engine' ),
            'manage_options',
            'bre-geo',
            array( new GeoPage(), 'render' )
        );
```

**Step 4: Syntax check all three modified files**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Core.php && \
php -l /var/www/dev/plugins/bre-dev/includes/Admin/AdminMenu.php
```

---

## Task 9: Full Integration Test

**Step 1: Activate in a local WordPress install and verify no fatal errors**

```bash
php -l /var/www/dev/plugins/bre-dev/includes/Features/GeoBlock.php && \
php -l /var/www/dev/plugins/bre-dev/includes/Admin/GeoPage.php && \
php -l /var/www/dev/plugins/bre-dev/includes/Admin/GeoEditorBox.php && \
php -l /var/www/dev/plugins/bre-dev/includes/Core.php && \
php -l /var/www/dev/plugins/bre-dev/includes/Admin/AdminMenu.php
echo "All files OK"
```

**Step 2: Manual admin test checklist**

1. Go to **Bavarian Rank → GEO Block** → settings page loads, no errors
2. Enable GEO block, set mode to "auto_on_publish", save → settings saved
3. Open a post editor → "GEO Schnellüberblick (BRE)" meta box appears
4. Click "Jetzt generieren" → AJAX fires, fields populate with summary/bullets/FAQ
5. Manually edit summary field → lock checkbox auto-checks
6. Click "Leeren" → fields cleared
7. Save post → manually entered values persist
8. View the published post → `<details class="bre-geo">` appears in correct position
9. Verify only `.bre-geo` styles apply (no global style leakage)

**Step 3: Test quality gate edge cases**

- Post with < 350 words → no FAQ generated (faq = [])
- AI returns markdown-fenced JSON → stripped correctly, parses OK
- AI returns invalid JSON → generate() returns false, no crash

---

## Task 10: Build and Final Check

**Step 1: Run build**

```bash
bash /var/www/dev/plugins/bre-dev/bin/build.sh
```

Expected: Build completes, ZIP created. File count increases by ~6 new files.

**Step 2: Verify no website/ or README.md in ZIP**

```bash
find /var/www/dev/plugins/bavarian-rank-engine -name "*.html" -o -name "README.md" | wc -l
```

Expected: `0`

**Step 3: PHPCS check for Plugin-Check-relevant violations**

```bash
vendor/bin/phpcs --standard=WordPress --extensions=php \
  --ignore=vendor,node_modules,website \
  includes/Features/GeoBlock.php \
  includes/Admin/GeoPage.php \
  includes/Admin/GeoEditorBox.php 2>&1 | \
  grep -iE "(WordPress\.Security\.|WordPress\.DB\.|PluginCheck\.|NonPrefixedVariable|NonPrefixedFunction|DevelopmentFunctions)"
```

Expected: No output (zero violations in Plugin-Check-relevant sniffs).

---

## Summary

| Task | What gets built |
|------|----------------|
| 1 | `GeoBlock` class shell + `getSettings()` + constants |
| 2 | `generate()` + `buildPrompt()` + `parseResponse()` + `qualityGate()` + `saveMeta()` |
| 3 | `register()` + `injectBlock()` + `renderBlock()` + publish/save hooks + frontend CSS |
| 4 | `GeoPage` admin class (settings registration + sanitize) |
| 5 | `geo.php` view template (full settings form) |
| 6 | `GeoEditorBox` (meta box, save hook, AJAX generate/clear) |
| 7 | `geo-editor.js` (generate/clear AJAX + auto-lock on manual edit) |
| 8 | Wire into `Core.php` + `AdminMenu.php` |
| 9 | Integration test checklist |
| 10 | Build + PHPCS verification |
