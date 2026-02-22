# Bavarian Rank Engine — Full Rebuild Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform the seo-geo plugin into "Bavarian Rank Engine" — fixing the critical activation bug, rebranding, restructuring admin menus, adding llms.txt generation, replacing OpenSSL key storage with WP-salt XOR obfuscation, and adding i18n (de_DE / en_US).

**Architecture:** All dev stays in `/var/www/dev/plugins/seo-geo/`. New namespace `BavarianRankEngine`, text domain `bavarian-rank-engine`, constants prefix `BRE_`. Top-level WP admin menu with subpages. A packaging script copies and renames everything into a `bavarian-rank-engine/` directory and creates a zip.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, no external composer deps beyond PHPUnit for tests. No OpenSSL, no Node build step required. Assets: vanilla JS + inline WP styles.

---

## Task 1: Fix Critical Activation Bug

**Files:**
- Modify: `includes/Admin/SettingsPage.php:118-122`

**Problem:** `explode("\n", $input['schema_same_as']['organization'] ?? '')` crashes when the stored value is already an array (happens on re-save after first activation).

**Step 1: Write failing test** (add to `tests/Admin/SettingsPageTest.php` — create file)

```php
<?php
namespace BavarianRankEngine\Tests\Admin;

use WP_Mock\Tools\TestCase;

class SettingsPageTest extends TestCase {
    public function test_sanitize_handles_array_same_as(): void {
        // Simulate second save: organization is already a stored array
        $input = [
            'provider'          => 'openai',
            'schema_same_as'    => ['organization' => ['https://twitter.com/test']],
            'schema_enabled'    => [],
            'meta_post_types'   => [],
            'api_keys'          => [],
            'models'            => [],
        ];
        $page   = new \BavarianRankEngine\Admin\SettingsPage();
        $result = $page->sanitize_settings($input);
        $this->assertIsArray($result['schema_same_as']['organization']);
    }
}
```

**Step 2: Run test to confirm it fails**

```bash
cd /var/www/dev/plugins/seo-geo && vendor/bin/phpunit tests/Admin/SettingsPageTest.php
```
Expected: FAIL or error.

**Step 3: Fix `sanitize_settings()` in `SettingsPage.php` around line 118**

Replace:
```php
$clean['schema_same_as'] = [
    'organization' => array_values( array_filter( array_map( 'esc_url_raw',
        array_map( 'trim', explode( "\n", $input['schema_same_as']['organization'] ?? '' ) )
    ) ) ),
];
```

With:
```php
$org_raw = $input['schema_same_as']['organization'] ?? '';
if ( is_array( $org_raw ) ) {
    $org_raw = implode( "\n", $org_raw );
}
$clean['schema_same_as'] = [
    'organization' => array_values( array_filter( array_map( 'esc_url_raw',
        array_map( 'trim', explode( "\n", $org_raw ) )
    ) ) ),
];
```

**Step 4: Run test again**

Expected: PASS.

**Step 5: Commit**

```bash
git add includes/Admin/SettingsPage.php tests/Admin/SettingsPageTest.php
git commit -m "fix: guard explode() against array input in schema_same_as sanitizer"
```

---

## Task 2: Rebrand — Rename Plugin Header, Constants, Namespace

**Files:**
- Modify: `seo-geo.php` (plugin header + constants)
- Modify: All `includes/**/*.php` (namespace + constant references)
- Modify: `includes/Core.php`
- Modify: `uninstall.php`

**Step 1: Update plugin header in `seo-geo.php`**

```php
<?php
/**
 * Plugin Name:       Bavarian Rank Engine
 * Plugin URI:        https://donau2space.de
 * Description:       AI-powered meta descriptions, GEO structured data, and llms.txt for WordPress.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Donau2Space
 * Author URI:        https://donau2space.de
 * License:           GPL-2.0-or-later
 * Text Domain:       bavarian-rank-engine
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BRE_VERSION', '2.0.0' );
define( 'BRE_FILE',    __FILE__ );
define( 'BRE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BRE_URL',     plugin_dir_url( __FILE__ ) );

require_once BRE_DIR . 'includes/Core.php';

function bre_init(): void {
    load_plugin_textdomain( 'bavarian-rank-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    \BavarianRankEngine\Core::instance()->init();
}
add_action( 'plugins_loaded', 'bre_init' );
```

**Step 2: Global search-replace namespaces and constants**

Run these sed operations on all PHP files:
```bash
find /var/www/dev/plugins/seo-geo/includes -name "*.php" -exec \
  sed -i \
    -e 's/namespace SeoGeo/namespace BavarianRankEngine/g' \
    -e 's/use SeoGeo\\/use BavarianRankEngine\\/g' \
    -e 's/\\SeoGeo\\/\\BavarianRankEngine\\/g' \
    -e 's/SEO_GEO_VERSION/BRE_VERSION/g' \
    -e 's/SEO_GEO_DIR/BRE_DIR/g' \
    -e 's/SEO_GEO_URL/BRE_URL/g' \
    -e 's/SEO_GEO_FILE/BRE_FILE/g' \
    {} \;
```

Also update tests/:
```bash
find /var/www/dev/plugins/seo-geo/tests -name "*.php" -exec \
  sed -i \
    -e 's/namespace SeoGeo/namespace BavarianRankEngine/g' \
    -e 's/use SeoGeo\\/use BavarianRankEngine\\/g' \
    {} \;
```

**Step 3: Update `composer.json` autoload PSR-4**

Change:
```json
"autoload": {
  "psr-4": { "SeoGeo\\": "includes/" }
},
"autoload-dev": {
  "psr-4": { "SeoGeo\\Tests\\": "tests/" }
}
```
To:
```json
"autoload": {
  "psr-4": { "BavarianRankEngine\\": "includes/" }
},
"autoload-dev": {
  "psr-4": { "BavarianRankEngine\\Tests\\": "tests/" }
}
```

**Step 4: Regenerate autoloader**

```bash
cd /var/www/dev/plugins/seo-geo && php composer.phar dump-autoload
```

**Step 5: Update `uninstall.php`**

Change option key references from `seo_geo_settings` → `bre_settings`, delete `seo_geo_*` options.

**Step 6: Run tests**

```bash
vendor/bin/phpunit
```
Expected: all passing.

**Step 7: Commit**

```bash
git add -A
git commit -m "feat: rebrand to Bavarian Rank Engine — namespace, constants, plugin header"
```

---

## Task 3: API Key Storage Without OpenSSL — XOR + WP Salts

**Files:**
- Modify: `includes/Helpers/KeyVault.php` (replace OpenSSL with XOR obfuscation)
- Modify: `includes/Admin/SettingsPage.php` (add wp-config.php constants support)

**Context:** OpenSSL is unavailable on some hosts. WP's `AUTH_KEY` is always defined (set in wp-config.php by WP installer). XOR + base64 keeps keys out of plain-text DB dumps without any extension dependency. Optionally, if the admin defines `BRE_OPENAI_KEY` etc. in wp-config.php manually, those take priority.

**Step 1: Rewrite `KeyVault.php`**

```php
<?php
namespace BavarianRankEngine\Helpers;

/**
 * Obfuscates API keys in the database using XOR with WP AUTH_KEY.
 * Keys defined as BRE_<PROVIDER>_KEY constants in wp-config.php take priority
 * and are never stored in the database at all.
 */
class KeyVault {

    /** Obfuscate a plain key for DB storage. No OpenSSL required. */
    public static function encrypt( string $key ): string {
        if ( $key === '' ) return '';
        return 'bre1:' . base64_encode( self::xor( $key, self::salt() ) );
    }

    /** Recover the plain key from an obfuscated DB value. */
    public static function decrypt( string $stored ): string {
        if ( $stored === '' ) return '';
        // Legacy: OpenSSL-encrypted values start with a valid base64 block ≥ 17 bytes
        // after decoding — detect by absence of our prefix.
        if ( str_starts_with( $stored, 'bre1:' ) ) {
            $raw = base64_decode( substr( $stored, 5 ), true );
            return $raw !== false ? self::xor( $raw, self::salt() ) : '';
        }
        // Legacy OpenSSL path — attempt to decode but return empty if it fails
        // (user will need to re-enter the key once after upgrade).
        return '';
    }

    /** Read key for a provider: wp-config constant takes priority over DB. */
    public static function getKey( string $provider_id, array $db_keys ): string {
        $const = 'BRE_' . strtoupper( $provider_id ) . '_KEY';
        if ( defined( $const ) ) {
            return constant( $const );
        }
        $stored = $db_keys[ $provider_id ] ?? '';
        return self::decrypt( $stored );
    }

    /** Returns masked version for display: ••••••Ab3c9 */
    public static function mask( string $plain ): string {
        if ( $plain === '' ) return '';
        return str_repeat( '•', max( 0, mb_strlen( $plain ) - 5 ) ) . mb_substr( $plain, -5 );
    }

    /** True if this provider's key comes from a wp-config constant. */
    public static function isFromConstant( string $provider_id ): bool {
        return defined( 'BRE_' . strtoupper( $provider_id ) . '_KEY' );
    }

    private static function xor( string $data, string $key ): string {
        $out = '';
        $len = strlen( $key );
        for ( $i = 0, $n = strlen( $data ); $i < $n; $i++ ) {
            $out .= $data[ $i ] ^ $key[ $i % $len ];
        }
        return $out;
    }

    private static function salt(): string {
        $a = defined( 'AUTH_KEY' )        ? AUTH_KEY        : 'bre-a';
        $b = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'bre-b';
        return hash( 'sha256', $a . $b ); // always 64 chars, no extension needed
    }
}
```

**Step 2: Update `SettingsPage.getSettings()` to use `KeyVault::getKey()`**

In `getSettings()`, replace the foreach that decrypts api_keys:
```php
foreach ( $settings['api_keys'] as $id => $stored ) {
    $settings['api_keys'][ $id ] = KeyVault::getKey( $id, $settings['api_keys'] );
}
```

**Step 3: Add admin notice for wp-config.php keys**

In `SettingsPage.register()` add:
```php
add_action( 'admin_notices', [ $this, 'maybe_show_config_notice' ] );
```

And add method:
```php
public function maybe_show_config_notice(): void {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'bavarian-rank' ) === false ) return;
    // Show tip on how to add keys to wp-config.php
    echo '<div class="notice notice-info is-dismissible"><p>';
    printf(
        /* translators: %s: code example */
        esc_html__( 'Tip: For maximum security, add API keys to wp-config.php: %s', 'bavarian-rank-engine' ),
        '<code>define(\'BRE_OPENAI_KEY\', \'sk-...\');</code>'
    );
    echo '</p></div>';
}
```

**Step 4: Run KeyVault tests**

```bash
vendor/bin/phpunit tests/Helpers/KeyVaultTest.php
```
Update `KeyVaultTest.php` to cover new XOR encrypt/decrypt roundtrip and `getKey()` with/without constant.

**Step 5: Commit**

```bash
git add includes/Helpers/KeyVault.php includes/Admin/SettingsPage.php tests/Helpers/KeyVaultTest.php
git commit -m "feat: replace OpenSSL key storage with WP-salt XOR obfuscation; support wp-config constants"
```

---

## Task 4: Admin Menu Restructure — Top-Level "Bavarian Rank"

**Files:**
- Modify: `includes/Admin/SettingsPage.php` → split into:
  - `includes/Admin/AdminMenu.php` (registers top-level menu + all submenus)
  - `includes/Admin/DashboardPage.php` (Dashboard subpage)
  - `includes/Admin/ProviderPage.php` (AI Provider subpage)
  - `includes/Admin/MetaPage.php` (Meta Generator subpage)
- Modify: `includes/Admin/BulkPage.php` (move to "Bavarian Rank" submenu)
- Create: `includes/Admin/views/dashboard.php`
- Create: `includes/Admin/views/provider.php`
- Create: `includes/Admin/views/meta.php`
- Modify: `includes/Core.php` (register AdminMenu instead of SettingsPage)

**Menu structure:**

```
Bavarian Rank (top-level, dashicons-chart-area)
├── Dashboard          → AdminMenu::render_dashboard()
├── AI Provider        → ProviderPage::render()
├── Meta Generator     → MetaPage::render()
├── llms.txt           → LlmsPage::render()   [added in Task 5]
└── Bulk Generator     → BulkPage::render()   [existing, relocated]
```

**Step 1: Create `AdminMenu.php`**

```php
<?php
namespace BavarianRankEngine\Admin;

class AdminMenu {
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
    }

    public function add_menus(): void {
        add_menu_page(
            __( 'Bavarian Rank', 'bavarian-rank-engine' ),
            __( 'Bavarian Rank', 'bavarian-rank-engine' ),
            'manage_options',
            'bavarian-rank',
            [ $this, 'render_dashboard' ],
            'dashicons-chart-area',
            80
        );
        add_submenu_page( 'bavarian-rank', __( 'Dashboard', 'bavarian-rank-engine' ),
            __( 'Dashboard', 'bavarian-rank-engine' ), 'manage_options', 'bavarian-rank', [ $this, 'render_dashboard' ] );
        add_submenu_page( 'bavarian-rank', __( 'AI Provider', 'bavarian-rank-engine' ),
            __( 'AI Provider', 'bavarian-rank-engine' ), 'manage_options', 'bre-provider', [ new ProviderPage(), 'render' ] );
        add_submenu_page( 'bavarian-rank', __( 'Meta Generator', 'bavarian-rank-engine' ),
            __( 'Meta Generator', 'bavarian-rank-engine' ), 'manage_options', 'bre-meta', [ new MetaPage(), 'render' ] );
        add_submenu_page( 'bavarian-rank', __( 'llms.txt', 'bavarian-rank-engine' ),
            'llms.txt', 'manage_options', 'bre-llms', [ new LlmsPage(), 'render' ] );
        add_submenu_page( 'bavarian-rank', __( 'Bulk Generator', 'bavarian-rank-engine' ),
            __( 'Bulk Generator', 'bavarian-rank-engine' ), 'manage_options', 'bre-bulk', [ new BulkPage(), 'render' ] );
    }

    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings       = SettingsPage::getSettings();
        $post_types     = $settings['meta_post_types'] ?? [ 'post', 'page' ];
        $meta_stats     = $this->get_meta_stats( $post_types );
        $provider       = $settings['provider'] ?? 'openai';
        include BRE_DIR . 'includes/Admin/views/dashboard.php';
    }

    private function get_meta_stats( array $post_types ): array {
        global $wpdb;
        $stats = [];
        foreach ( $post_types as $pt ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $pt
            ) );
            $with_meta = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = '_yoast_wpseo_metadesc' AND pm.meta_value != ''", $pt
                 // fallback: also check _bre_meta_description
            ) );
            // Also count BRE-generated ones
            $with_bre = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = '_bre_meta_description' AND pm.meta_value != ''", $pt
            ) );
            $stats[ $pt ] = [ 'total' => $total, 'with_meta' => max( $with_meta, $with_bre ) ];
        }
        return $stats;
    }
}
```

**Step 2: Split SettingsPage into ProviderPage + MetaPage**

`ProviderPage.php` handles: provider select, API keys, model select, connection test.
`MetaPage.php` handles: auto-mode, post types, token mode, prompt.
`SettingsPage.php` becomes a thin static utility: `getSettings()`, `sanitize_settings()`, `getDefaultPrompt()` only — no menu registration.

Each Page class registers its own `register_settings` group and `admin_init` hook.

**Step 3: Create `views/dashboard.php`**

Dashboard shows:
- Plugin version + active provider
- Table: Post Type | Published | With Meta | Coverage %
- Links to each subpage
- Admin notice if no API key configured

**Step 4: Update `Core.php`**

```php
if ( is_admin() ) {
    ( new Admin\AdminMenu() )->register();
    ( new Admin\BulkPage() )->register_ajax(); // AJAX only, menu via AdminMenu
}
```

**Step 5: Update asset enqueue hooks**

Each page class checks its own `$hook` slug (e.g., `bavarian-rank_page_bre-provider`).

**Step 6: Run tests**

```bash
vendor/bin/phpunit
```

**Step 7: Commit**

```bash
git add includes/Admin/ includes/Core.php
git commit -m "feat: restructure admin to top-level Bavarian Rank menu with Dashboard, AI Provider, Meta subpages"
```

---

## Task 5: llms.txt Feature

**Files:**
- Create: `includes/Admin/LlmsPage.php`
- Create: `includes/Admin/views/llms.php`
- Create: `includes/Features/LlmsTxt.php`
- Modify: `includes/Core.php` (register LlmsTxt feature)

**llms.txt format (served at `https://example.com/llms.txt`):**

```
# {title}

{description_before}

## Featured Resources

{custom_links}

## Content

{posts_list}    ← "Post Title — https://url.com — 2024-01-15"

---
{description_after}

---
{description_footer}
```

**Settings stored in `bre_llms_settings` option:**

| Field              | Type    | Description                                         |
|--------------------|---------|-----------------------------------------------------|
| `enabled`          | bool    | Whether the endpoint is active                      |
| `title`            | string  | # heading in llms.txt                               |
| `description_before` | textarea | text before featured links                       |
| `description_after`  | textarea | text after content list                          |
| `description_footer` | textarea | final section                                   |
| `custom_links`     | textarea | raw lines: "Name — https://url" (one per line)     |
| `post_types`       | array   | which post types to include                         |

**Step 1: Create `LlmsTxt.php` feature class**

```php
<?php
namespace BavarianRankEngine\Features;

class LlmsTxt {
    private const OPTION_KEY = 'bre_llms_settings';

    public function register(): void {
        add_action( 'init', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'serve' ] );
        register_activation_hook( BRE_FILE, [ $this, 'flush_rules' ] );
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule( '^llms\.txt$', 'index.php?bre_llms=1', 'top' );
    }

    public function add_query_var( array $vars ): array {
        $vars[] = 'bre_llms';
        return $vars;
    }

    public function serve(): void {
        if ( ! get_query_var( 'bre_llms' ) ) return;
        $settings = self::getSettings();
        if ( empty( $settings['enabled'] ) ) {
            status_header( 404 );
            exit;
        }
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo $this->build( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    private function build( array $s ): string {
        $out = '';
        if ( ! empty( $s['title'] ) ) {
            $out .= '# ' . $s['title'] . "\n\n";
        }
        if ( ! empty( $s['description_before'] ) ) {
            $out .= trim( $s['description_before'] ) . "\n\n";
        }
        if ( ! empty( $s['custom_links'] ) ) {
            $out .= "## Featured Resources\n\n";
            $out .= trim( $s['custom_links'] ) . "\n\n";
        }
        $post_types = $s['post_types'] ?? [ 'post', 'page' ];
        if ( ! empty( $post_types ) ) {
            $out .= "## Content\n\n";
            $out .= $this->build_content_list( $post_types );
        }
        if ( ! empty( $s['description_after'] ) ) {
            $out .= "\n---\n" . trim( $s['description_after'] ) . "\n";
        }
        if ( ! empty( $s['description_footer'] ) ) {
            $out .= "\n---\n" . trim( $s['description_footer'] ) . "\n";
        }
        return $out;
    }

    private function build_content_list( array $post_types ): string {
        $args  = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];
        $query = new \WP_Query( $args );
        $lines = [];
        foreach ( $query->posts as $post ) {
            $lines[] = sprintf(
                '- [%s](%s) — %s',
                $post->post_title,
                get_permalink( $post ),
                get_the_date( 'Y-m-d', $post )
            );
        }
        wp_reset_postdata();
        return implode( "\n", $lines ) . "\n";
    }

    public function flush_rules(): void {
        $this->add_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function getSettings(): array {
        $defaults = [
            'enabled'             => false,
            'title'               => get_bloginfo( 'name' ),
            'description_before'  => '',
            'description_after'   => '',
            'description_footer'  => '',
            'custom_links'        => '',
            'post_types'          => [ 'post', 'page' ],
        ];
        return array_merge( $defaults, get_option( self::OPTION_KEY, [] ) );
    }
}
```

**Step 2: Create `LlmsPage.php` admin class**

Registers `bre_llms_settings` option with sanitize callback. Renders the form in `views/llms.php`.

Form fields: enabled toggle, title input, description_before textarea, description_after textarea, description_footer textarea, custom_links textarea, post_types checkboxes. Preview button that opens `[site_url]/llms.txt` in new tab.

**Step 3: Create `views/llms.php`**

Clean form layout consistent with other admin pages. Show current llms.txt URL as a link. Include "Flush Rewrite Rules" button (POST action).

**Step 4: Register in `Core.php`**

```php
( new Features\LlmsTxt() )->register();
```

And in admin:
```php
( new Admin\LlmsPage() )->register();
```

**Step 5: Test rewrite rule works**

```bash
# After activating plugin on a live WP install:
curl -I https://[your-site]/llms.txt
# Expected: 200 text/plain
```

**Step 6: Commit**

```bash
git add includes/Features/LlmsTxt.php includes/Admin/LlmsPage.php includes/Admin/views/llms.php includes/Core.php
git commit -m "feat: add llms.txt feature with configurable content, custom links, and rewrite endpoint"
```

---

## Task 6: Internationalization (de_DE + en_US)

**Files:**
- Create: `languages/bavarian-rank-engine.pot`
- Create: `languages/bavarian-rank-engine-de_DE.po`
- Create: `languages/bavarian-rank-engine-de_DE.mo` (compiled)
- Verify: All user-facing strings use `__()`, `_e()`, `esc_html__()` etc. with domain `bavarian-rank-engine`

**Step 1: Audit all PHP view files for bare German/English strings**

Search for strings not wrapped in i18n functions:
```bash
grep -rn "echo '" includes/Admin/views/ | grep -v "esc_html\|__\|_e\|esc_attr"
```

**Step 2: Wrap all bare strings**

Example replacements:
- `'Einstellungen speichern'` → `__( 'Einstellungen speichern', 'bavarian-rank-engine' )`
- `echo 'Gespeichert'` → `esc_html_e( 'Gespeichert', 'bavarian-rank-engine' )`

**Step 3: Generate .pot file** (via WP-CLI if available, else manually)

```bash
# If WP-CLI available:
wp i18n make-pot /var/www/dev/plugins/seo-geo languages/bavarian-rank-engine.pot --slug=bavarian-rank-engine

# Fallback: create manually with all msgid strings
```

**Step 4: Create `de_DE.po` with translations**

Key strings to translate (German already used → English msgid, German msgstr):

```po
msgid "AI Provider"
msgstr "KI-Anbieter"

msgid "Meta Generator"
msgstr "Meta-Generator"

msgid "Dashboard"
msgstr "Dashboard"

msgid "Bulk Generator"
msgstr "Massen-Generator"

msgid "Save Settings"
msgstr "Einstellungen speichern"

msgid "Test Connection"
msgstr "Verbindung testen"

msgid "Active Provider"
msgstr "Aktiver Anbieter"
```

**Step 5: Compile .mo file**

```bash
msgfmt languages/bavarian-rank-engine-de_DE.po -o languages/bavarian-rank-engine-de_DE.mo
```

**Step 6: Commit**

```bash
git add languages/
git commit -m "feat: add i18n support — en_US + de_DE translations"
```

---

## Task 7: Build / Packaging Script

**Files:**
- Create: `bin/build.sh`

**Step 1: Create `bin/build.sh`**

```bash
#!/usr/bin/env bash
set -e

PLUGIN_DIR="/var/www/dev/plugins/seo-geo"
OUT_DIR="/var/www/dev/plugins/bavarian-rank-engine"
ZIP_FILE="/var/www/dev/plugins/bavarian-rank-engine.zip"

# Clean previous build
rm -rf "$OUT_DIR"
mkdir -p "$OUT_DIR"

# Copy plugin files (exclude dev artifacts)
rsync -av --exclude='.git' \
          --exclude='node_modules' \
          --exclude='vendor/phpunit' \
          --exclude='vendor/nikic' \
          --exclude='vendor/sebastian' \
          --exclude='vendor/phar-io' \
          --exclude='vendor/theseer' \
          --exclude='vendor/myclabs' \
          --exclude='tests' \
          --exclude='docs' \
          --exclude='bin' \
          --exclude='composer.phar' \
          --exclude='phpunit.xml' \
          --exclude='.phpunit.result.cache' \
          --exclude='firebase-debug.log' \
          "$PLUGIN_DIR/" "$OUT_DIR/"

# Rename main plugin file
mv "$OUT_DIR/seo-geo.php" "$OUT_DIR/bavarian-rank-engine.php"

# Install production composer deps only
cd "$OUT_DIR" && php composer.phar install --no-dev --optimize-autoloader 2>/dev/null || true

# Create zip
cd /var/www/dev/plugins
zip -r "$ZIP_FILE" bavarian-rank-engine/

echo "✓ Built: $ZIP_FILE"
```

**Step 2: Make executable**

```bash
chmod +x /var/www/dev/plugins/seo-geo/bin/build.sh
```

**Step 3: Test build**

```bash
/var/www/dev/plugins/seo-geo/bin/build.sh
ls -lh /var/www/dev/plugins/bavarian-rank-engine.zip
```

**Step 4: Commit**

```bash
git add bin/build.sh
git commit -m "feat: add build/packaging script for bavarian-rank-engine distribution"
```

---

## Summary

| Task | Scope | Risk |
|------|-------|------|
| 1. Fix bug | 4 lines changed | Critical fix, low risk |
| 2. Rebrand | sed + header + composer.json | Medium — test after |
| 3. KeyVault XOR | Replace OpenSSL with XOR | Medium — users re-enter keys once |
| 4. Admin menu | Restructure 3 classes | Medium — hook names change |
| 5. llms.txt | New feature, new route | Low — isolated |
| 6. i18n | String wrapping | Low — additive |
| 7. Build script | Shell script | Low |

**Execute in order** — Task 1 immediately (site is broken), Tasks 2-7 sequentially.
