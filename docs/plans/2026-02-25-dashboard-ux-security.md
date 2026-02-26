# Dashboard UX & Security — v1.2.2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Polish the admin dashboard (styled boxes, dismissible welcome, token tracking), gate AI with an enable toggle + cost warning, fix all Plugin Check/PHPCS warnings, localize all strings, and add transient caching.

**Architecture:** Pure PHP/JS/CSS inside `bre-dev/`. No new dependencies. Token usage accumulates in `bre_usage_stats` WP option (estimation via `TokenEstimator::estimate()`). Welcome notice uses `user_meta` for per-user dismiss + `bre_first_activated` option for 24 h auto-expiry. AI enable flag lives in `bre_settings` (same option as provider data).

**Tech Stack:** PHP 8.0+, WordPress 6.0+, jQuery (WP-bundled), PHPUnit (existing test suite in `tests/`)

---

### Task 1: Fix Plugin Check Warnings — Move compat detection out of template

**Files:**
- Modify: `includes/Admin/AdminMenu.php`
- Modify: `includes/Admin/views/dashboard.php`

**Step 1: Add `get_compat_info()` private method to AdminMenu**

Add after `get_meta_stats()`:

```php
private function get_compat_info(): array {
    $compat = array();
    if ( defined( 'RANK_MATH_VERSION' ) ) {
        $compat[] = array(
            'name'  => 'Rank Math',
            'notes' => array(
                __( 'llms.txt: BRE serves the file with priority — Rank Math is bypassed.', 'bavarian-rank-engine' ),
                __( 'Schema.org: BRE suppresses its own JSON-LD to avoid duplicates.', 'bavarian-rank-engine' ),
                __( 'Meta descriptions: BRE writes to the Rank Math meta field.', 'bavarian-rank-engine' ),
            ),
        );
    }
    if ( defined( 'WPSEO_VERSION' ) ) {
        $compat[] = array(
            'name'  => 'Yoast SEO',
            'notes' => array(
                __( 'Schema.org: BRE suppresses its own JSON-LD to avoid duplicates.', 'bavarian-rank-engine' ),
                __( 'Meta descriptions: BRE writes to the Yoast meta field.', 'bavarian-rank-engine' ),
            ),
        );
    }
    if ( defined( 'AIOSEO_VERSION' ) ) {
        $compat[] = array(
            'name'  => 'All in One SEO',
            'notes' => array(
                __( 'Meta descriptions: BRE writes to the AIOSEO meta field.', 'bavarian-rank-engine' ),
            ),
        );
    }
    if ( class_exists( 'SeoPress_Titles_Admin' ) ) {
        $compat[] = array(
            'name'  => 'SEOPress',
            'notes' => array(
                __( 'Meta descriptions: BRE writes to the SEOPress meta field.', 'bavarian-rank-engine' ),
            ),
        );
    }
    return $compat;
}
```

**Step 2: Update `render_dashboard()` to use the new method**

Replace the existing method body:

```php
public function render_dashboard(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings   = SettingsPage::getSettings();
    $provider   = $settings['provider'] ?? 'openai';
    $post_types = $settings['meta_post_types'] ?? array( 'post', 'page' );
    $meta_stats = $this->get_meta_stats( $post_types );
    $bre_compat = $this->get_compat_info();

    include BRE_DIR . 'includes/Admin/views/dashboard.php';
}
```

**Step 3: Strip compat building logic from dashboard.php**

Remove lines 63–100 (the entire `$bre_compat = array(); ...` block including all `if ( defined(...) )` builders).
Keep only the conditional render block (`if ( ! empty( $bre_compat ) ) : ... endif;`) which reads the already-built array.
The `$bre_compat` variable arrives in scope via include — no assignment in the template means no Plugin Check warning.

**Step 4: Run tests**

```bash
cd /var/www/dev/plugins/bre/bre-dev && php composer.phar exec phpunit
```
Expected: all existing tests PASS.

**Step 5: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add includes/Admin/AdminMenu.php includes/Admin/views/dashboard.php
git -C /var/www/dev/plugins/bre/bre-dev commit -m "fix: move compat detection out of template to clear Plugin Check warnings"
```

---

### Task 2: Fix i18n — Localize hardcoded German strings in admin.js

**Files:**
- Modify: `includes/Admin/AdminMenu.php`
- Modify: `includes/Admin/ProviderPage.php`
- Modify: `assets/admin.js`

**Step 1: Extend wp_localize_script in AdminMenu::enqueue_assets()**

Replace the existing `wp_localize_script` call with:

```php
wp_localize_script(
    'bre-admin',
    'breAdmin',
    array(
        'nonce'        => wp_create_nonce( 'bre_admin' ),
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'testing'      => __( 'Testing…', 'bavarian-rank-engine' ),
        'networkError' => __( 'Network error', 'bavarian-rank-engine' ),
        'resetConfirm' => __( 'Really reset the prompt?', 'bavarian-rank-engine' ),
    )
);
```

**Step 2: Apply the same to ProviderPage::enqueue_assets()**

Same replacement — identical localize data object.

**Step 3: Update admin.js — replace hardcoded German strings**

- Line 16: `'Teste\u2026'` → `breAdmin.testing`
- Line 31: `'\u2717 Netzwerkfehler'` → `'\u2717 ' + breAdmin.networkError`
- Line 38: `'Prompt wirklich zur\u00fccksetzen?'` → `breAdmin.resetConfirm`

**Step 4: Run tests**

```bash
php composer.phar exec phpunit
```

**Step 5: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add assets/admin.js includes/Admin/AdminMenu.php includes/Admin/ProviderPage.php
git -C /var/www/dev/plugins/bre/bre-dev commit -m "fix: localize all hardcoded German strings in admin.js"
```

---

### Task 3: Dismissible Welcome Notice (24 h auto-expiry, Bavarian gag)

**Files:**
- Modify: `seo-geo.php`
- Modify: `includes/Admin/AdminMenu.php`
- Modify: `includes/Admin/views/dashboard.php`
- Modify: `assets/admin.js`
- Modify: `assets/admin.css`

**Step 1: Store activation timestamp in seo-geo.php**

In the `register_activation_hook` closure, add after `flush_rewrite_rules()`:

```php
if ( ! get_option( 'bre_first_activated' ) ) {
    update_option( 'bre_first_activated', time() );
}
```

**Step 2: Add should_show_welcome() + ajax_dismiss_welcome() to AdminMenu**

Add to `register()`:

```php
add_action( 'wp_ajax_bre_dismiss_welcome', array( $this, 'ajax_dismiss_welcome' ) );
```

Add these two methods:

```php
private function should_show_welcome(): bool {
    if ( get_user_meta( get_current_user_id(), 'bre_welcome_dismissed', true ) ) {
        return false;
    }
    $activated = (int) get_option( 'bre_first_activated', 0 );
    if ( ! $activated ) {
        // First admin visit after a legacy install — set timestamp now
        update_option( 'bre_first_activated', time() );
        return true;
    }
    return ( time() - $activated ) < DAY_IN_SECONDS;
}

public function ajax_dismiss_welcome(): void {
    check_ajax_referer( 'bre_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
    }
    update_user_meta( get_current_user_id(), 'bre_welcome_dismissed', 1 );
    wp_send_json_success();
}
```

Add to `render_dashboard()` before the include:

```php
$bre_show_welcome = $this->should_show_welcome();
```

**Step 3: Replace old notice in dashboard.php with styled dismissible version**

Remove the current `<div class="notice notice-info inline" ...>` block (lines 6–10) and replace with:

```php
<?php if ( $bre_show_welcome ) : ?>
<div class="bre-welcome-notice" id="bre-welcome-notice">
    <button type="button" class="bre-dismiss" id="bre-dismiss-welcome"
            aria-label="<?php esc_attr_e( 'Dismiss', 'bavarian-rank-engine' ); ?>">&#215;</button>
    <p style="margin:0 0 6px;font-size:15px;">
        &#127866; <strong><?php esc_html_e( 'Servus! Welcome to Bavarian Rank Engine.', 'bavarian-rank-engine' ); ?></strong>
    </p>
    <p style="margin:0;color:#444;">
        <?php esc_html_e( 'No Lederhosen required — your SEO is already in good hands.', 'bavarian-rank-engine' ); ?>
        <a href="<?php echo esc_url( 'https://bavarianrankengine.com/howto.html' ); ?>" target="_blank" rel="noopener">
            <?php esc_html_e( 'Read the setup guide and be running in five minutes \u2192', 'bavarian-rank-engine' ); ?>
        </a>
    </p>
</div>
<?php endif; ?>
```

**Step 4: Add JS dismiss handler in admin.js**

Inside the jQuery ready function, append:

```javascript
$( '#bre-dismiss-welcome' ).on( 'click', function () {
    $( '#bre-welcome-notice' ).slideUp( 200 );
    $.post( breAdmin.ajaxUrl, {
        action: 'bre_dismiss_welcome',
        nonce:  breAdmin.nonce,
    } );
} );
```

**Step 5: Add CSS for welcome notice in admin.css**

```css
.bre-welcome-notice {
    background: linear-gradient(135deg, #fff 0%, #f0f6ff 100%);
    border-left: 4px solid #2271b1;
    border-radius: 0 4px 4px 0;
    padding: 16px 40px 16px 20px;
    margin: 16px 0 0;
    position: relative;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.bre-welcome-notice a { font-weight: 600; }
.bre-dismiss {
    position: absolute;
    top: 10px;
    right: 12px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 20px;
    color: #999;
    line-height: 1;
    padding: 2px 6px;
}
.bre-dismiss:hover { color: #333; }
```

**Step 6: Run tests**

```bash
php composer.phar exec phpunit
```

**Step 7: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add seo-geo.php includes/Admin/AdminMenu.php includes/Admin/views/dashboard.php assets/admin.js assets/admin.css
git -C /var/www/dev/plugins/bre/bre-dev commit -m "feat: add dismissible 24 h welcome notice with Bavarian gag"
```

---

### Task 4: Dashboard Visual Polish

**Files:**
- Modify: `includes/Admin/views/dashboard.php`
- Modify: `assets/admin.css`

**Step 1: Replace Meta Coverage table with progress bars**

Replace the entire `<table class="widefat striped">` block inside the Meta Coverage box with:

```php
<?php foreach ( $meta_stats as $pt => $stat ) : ?>
<div style="margin-bottom:14px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px;">
        <strong><?php echo esc_html( $pt ); ?></strong>
        <span style="color:#666;">
            <?php echo esc_html( $stat['with_meta'] ); ?>/<?php echo esc_html( $stat['total'] ); ?>
            &mdash; <?php echo esc_html( $stat['pct'] ); ?>%
        </span>
    </div>
    <div class="bre-progress-bar">
        <div class="bre-progress-fill <?php echo $stat['pct'] >= 80 ? 'bre-ok' : ( $stat['pct'] >= 40 ? 'bre-warn' : 'bre-bad' ); ?>"
             style="width:<?php echo esc_attr( $stat['pct'] ); ?>%"></div>
    </div>
</div>
<?php endforeach; ?>
```

**Step 2: Replace Quick Links bullet list with styled nav list**

Replace the `<ul style="...">` block:

```php
<ul class="bre-quick-links-list">
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-provider' ) ); ?>">
        &#x1F511; <?php esc_html_e( 'AI Provider Settings', 'bavarian-rank-engine' ); ?>
    </a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-meta' ) ); ?>">
        &#x270F;&#xFE0F; <?php esc_html_e( 'Meta Generator Settings', 'bavarian-rank-engine' ); ?>
    </a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-llms' ) ); ?>">
        &#x1F4C4; llms.txt
    </a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-bulk' ) ); ?>">
        &#x26A1; <?php esc_html_e( 'Bulk Generator', 'bavarian-rank-engine' ); ?>
    </a></li>
    <li><a href="<?php echo esc_url( 'https://bavarianrankengine.com/howto.html' ); ?>" target="_blank" rel="noopener">
        &#x1F4D6; <?php esc_html_e( 'Documentation &amp; How To', 'bavarian-rank-engine' ); ?>
    </a></li>
</ul>
```

**Step 3: Add colored dot before each bot name in AI Crawlers table**

In the crawler `<tbody>`, replace the `<code>` cell:

```php
<td><span class="bre-bot-dot"></span><code><?php echo esc_html( $row['bot_name'] ); ?></code></td>
```

**Step 4: Add CSS for progress bars, quick links, and crawler dots**

Append to `admin.css`:

```css
/* --- Progress bars --- */
.bre-progress-bar {
    height: 8px;
    background: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
}
.bre-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .3s ease;
}
.bre-progress-fill.bre-ok   { background: #46b450; }
.bre-progress-fill.bre-warn { background: #ffb900; }
.bre-progress-fill.bre-bad  { background: #dc3232; }

/* --- Quick links --- */
.bre-quick-links-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.bre-quick-links-list li {
    border-bottom: 1px solid #f0f0f1;
}
.bre-quick-links-list li:last-child { border-bottom: none; }
.bre-quick-links-list a {
    display: block;
    padding: 9px 6px;
    text-decoration: none;
    color: #2271b1;
    transition: padding-left .12s;
}
.bre-quick-links-list a:hover {
    color: #135e96;
    padding-left: 12px;
}

/* --- Crawler dot --- */
.bre-bot-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #46b450;
    margin-right: 6px;
    vertical-align: middle;
}
```

**Step 5: Run tests**

```bash
php composer.phar exec phpunit
```

**Step 6: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add includes/Admin/views/dashboard.php assets/admin.css
git -C /var/www/dev/plugins/bre/bre-dev commit -m "feat: polish dashboard — progress bars, styled quick links, crawler dots"
```

---

### Task 5: Token Usage Tracking (estimation-based)

**Files:**
- Modify: `tests/bootstrap.php` (add `update_option` stub)
- Create: `tests/Features/UsageTrackingTest.php`
- Modify: `includes/Features/MetaGenerator.php`

**Step 1: Add `update_option` stub to bootstrap.php**

After the `get_option` stub block, add:

```php
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        $GLOBALS['bre_test_options'][ $option ] = $value;
        return true;
    }
}
```

**Step 2: Write the failing test**

Create `tests/Features/UsageTrackingTest.php`:

```php
<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\MetaGenerator;

class UsageTrackingTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['bre_test_options'] = [];
    }

    public function test_record_usage_accumulates_tokens(): void {
        MetaGenerator::record_usage( 100, 50 );
        MetaGenerator::record_usage( 200, 80 );

        $stats = get_option( 'bre_usage_stats' );

        $this->assertSame( 300, $stats['tokens_in'] );
        $this->assertSame( 130, $stats['tokens_out'] );
        $this->assertSame( 2,   $stats['count'] );
    }

    public function test_record_usage_starts_from_zero(): void {
        MetaGenerator::record_usage( 50, 25 );

        $stats = get_option( 'bre_usage_stats' );

        $this->assertSame( 50, $stats['tokens_in'] );
        $this->assertSame( 25, $stats['tokens_out'] );
        $this->assertSame( 1,  $stats['count'] );
    }
}
```

**Step 3: Run test to confirm it fails**

```bash
php composer.phar exec phpunit tests/Features/UsageTrackingTest.php
```
Expected: FAIL — "Call to undefined method MetaGenerator::record_usage()"

**Step 4: Add `record_usage()` to MetaGenerator and call it in `generate()`**

Add static method:

```php
public static function record_usage( int $tokens_in, int $tokens_out ): void {
    $stats               = get_option( 'bre_usage_stats', array( 'tokens_in' => 0, 'tokens_out' => 0, 'count' => 0 ) );
    $stats['tokens_in']  = (int) ( $stats['tokens_in'] ?? 0 ) + $tokens_in;
    $stats['tokens_out'] = (int) ( $stats['tokens_out'] ?? 0 ) + $tokens_out;
    $stats['count']      = (int) ( $stats['count'] ?? 0 ) + 1;
    update_option( 'bre_usage_stats', $stats, false );
}
```

In `generate()`, after the `$result = $provider->generateText(...)` line, before `return $result`:

```php
$tokens_in  = TokenEstimator::estimate( $prompt );
$tokens_out = TokenEstimator::estimate( $result );
self::record_usage( $tokens_in, $tokens_out );
```

**Step 5: Run tests to confirm they pass**

```bash
php composer.phar exec phpunit tests/Features/UsageTrackingTest.php
```
Expected: 2 tests PASS

**Step 6: Run full suite**

```bash
php composer.phar exec phpunit
```
Expected: all tests PASS

**Step 7: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add tests/bootstrap.php tests/Features/UsageTrackingTest.php includes/Features/MetaGenerator.php
git -C /var/www/dev/plugins/bre/bre-dev commit -m "feat: estimation-based token usage tracking via record_usage()"
```

---

### Task 6: Status Widget — Token & Cost Display

**Files:**
- Modify: `includes/Admin/AdminMenu.php`
- Modify: `includes/Admin/views/dashboard.php`

**Step 1: Prepare usage data in render_dashboard()**

Add before the `include` in `render_dashboard()`:

```php
$usage_stats  = get_option( 'bre_usage_stats', array( 'tokens_in' => 0, 'tokens_out' => 0, 'count' => 0 ) );
$model        = $settings['models'][ $provider ] ?? '';
$costs_config = $settings['costs'][ $provider ][ $model ] ?? array();
$cost_usd     = null;
if ( ! empty( $costs_config['input'] ) || ! empty( $costs_config['output'] ) ) {
    $cost_usd = round(
        ( (int) ( $usage_stats['tokens_in'] ?? 0 ) / 1_000_000 ) * (float) ( $costs_config['input'] ?? 0 )
        + ( (int) ( $usage_stats['tokens_out'] ?? 0 ) / 1_000_000 ) * (float) ( $costs_config['output'] ?? 0 ),
        4
    );
}
```

**Step 2: Rewrite Status box in dashboard.php**

Replace the current Status `<div class="inside">` content:

```php
<div class="inside">
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="padding:5px 0;color:#666;font-size:12px;width:50%;"><?php esc_html_e( 'Version', 'bavarian-rank-engine' ); ?></td>
            <td style="padding:5px 0;font-weight:600;"><?php echo esc_html( BRE_VERSION ); ?></td>
        </tr>
        <tr>
            <td style="padding:5px 0;color:#666;font-size:12px;"><?php esc_html_e( 'Active Provider', 'bavarian-rank-engine' ); ?></td>
            <td style="padding:5px 0;font-weight:600;"><?php echo esc_html( $provider ); ?></td>
        </tr>
        <tr>
            <td style="padding:5px 0;color:#666;font-size:12px;"><?php esc_html_e( 'AI metas generated', 'bavarian-rank-engine' ); ?></td>
            <td style="padding:5px 0;font-weight:600;"><?php echo esc_html( number_format_i18n( (int) ( $usage_stats['count'] ?? 0 ) ) ); ?></td>
        </tr>
        <tr>
            <td style="padding:5px 0;color:#666;font-size:12px;"><?php esc_html_e( 'Tokens used (est.)', 'bavarian-rank-engine' ); ?></td>
            <td style="padding:5px 0;font-weight:600;">
                ~<?php echo esc_html( number_format_i18n( (int) ( $usage_stats['tokens_in'] ?? 0 ) + (int) ( $usage_stats['tokens_out'] ?? 0 ) ) ); ?>
            </td>
        </tr>
        <?php if ( null !== $cost_usd ) : ?>
        <tr>
            <td style="padding:5px 0;color:#666;font-size:12px;"><?php esc_html_e( 'Est. cost (USD)', 'bavarian-rank-engine' ); ?></td>
            <td style="padding:5px 0;font-weight:600;">~$<?php echo esc_html( number_format( $cost_usd, 4 ) ); ?></td>
        </tr>
        <?php endif; ?>
    </table>
    <p style="margin:12px 0 0;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bre-provider' ) ); ?>" class="button button-secondary" style="font-size:12px;">
            <?php esc_html_e( 'Configure AI Provider', 'bavarian-rank-engine' ); ?>
        </a>
    </p>
</div>
```

**Step 3: Run tests**

```bash
php composer.phar exec phpunit
```

**Step 4: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add includes/Admin/AdminMenu.php includes/Admin/views/dashboard.php
git -C /var/www/dev/plugins/bre/bre-dev commit -m "feat: token usage and estimated cost in Status widget"
```

---

### Task 7: AI Enable Toggle with Cost Warning

**Files:**
- Modify: `includes/Admin/SettingsPage.php`
- Modify: `includes/Admin/ProviderPage.php`
- Modify: `includes/Admin/views/provider.php`
- Modify: `assets/admin.js`
- Modify: `assets/admin.css`
- Modify: `includes/Features/MetaGenerator.php`

**Step 1: Add `ai_enabled` default to SettingsPage::getSettings()**

In the `$defaults` array:

```php
'ai_enabled' => true,   // true = existing installs keep working; new installs also start on
```

**Step 2: Sanitize `ai_enabled` in ProviderPage::sanitize()**

After `$clean['provider'] = ...`:

```php
$clean['ai_enabled'] = ! empty( $input['ai_enabled'] );
```

**Step 3: Add toggle + warning to views/provider.php**

Insert immediately after `<?php settings_fields( 'bre_provider' ); ?>` and before `<h2>`:

```php
<div class="bre-ai-toggle-wrap">
    <label style="font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
        <input type="checkbox" name="bre_settings[ai_enabled]" value="1" id="bre-ai-enabled"
               <?php checked( $settings['ai_enabled'] ?? true, true ); ?>>
        <?php esc_html_e( 'Enable AI generation', 'bavarian-rank-engine' ); ?>
    </label>
    <p class="bre-ai-cost-notice">
        &#9888; <?php esc_html_e( 'This feature will incur costs with your AI provider. Make sure you understand the pricing before entering an API key.', 'bavarian-rank-engine' ); ?>
    </p>
</div>
<div id="bre-ai-fields">
```

Add `</div><!-- /#bre-ai-fields -->` directly before `<?php submit_button( ... ); ?>`.

**Step 4: Add CSS for the toggle wrapper**

Append to `admin.css`:

```css
/* --- AI toggle --- */
.bre-ai-toggle-wrap {
    background: #fff8e5;
    border-left: 4px solid #ffb900;
    border-radius: 0 4px 4px 0;
    padding: 14px 16px;
    margin-bottom: 24px;
}
.bre-ai-cost-notice {
    margin: 8px 0 0;
    color: #856404;
    font-size: 13px;
}
```

**Step 5: Add JS toggle behavior in admin.js**

Add inside the jQuery ready function:

```javascript
function bre_update_ai_fields() {
    if ( $( '#bre-ai-enabled' ).is( ':checked' ) ) {
        $( '#bre-ai-fields' ).show();
    } else {
        $( '#bre-ai-fields' ).hide();
    }
}
if ( $( '#bre-ai-enabled' ).length ) {
    bre_update_ai_fields();
    $( '#bre-ai-enabled' ).on( 'change', bre_update_ai_fields );
}
```

**Step 6: Guard MetaGenerator::generate() with the flag**

At the start of `generate()`, after the provider/key setup block, add:

```php
if ( empty( $settings['ai_enabled'] ) ) {
    return FallbackMeta::extract( $post );
}
```

**Step 7: Run tests**

```bash
php composer.phar exec phpunit
```

**Step 8: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add includes/Admin/SettingsPage.php includes/Admin/ProviderPage.php includes/Admin/views/provider.php assets/admin.js assets/admin.css includes/Features/MetaGenerator.php
git -C /var/www/dev/plugins/bre/bre-dev commit -m "feat: AI enable toggle with cost warning on provider page"
```

---

### Task 8: Performance — Transient Caching for Dashboard Queries

**Files:**
- Modify: `includes/Admin/AdminMenu.php`
- Modify: `includes/Features/MetaGenerator.php`

**Step 1: Wrap get_meta_stats() with a transient**

Replace the method body (keep the `$wpdb` queries, just wrap them):

```php
private function get_meta_stats( array $post_types ): array {
    $cache_key = 'bre_meta_stats';
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    global $wpdb;
    $stats = array();
    foreach ( $post_types as $pt ) {
        $total     = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $pt
            )
        );
        $with_meta = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 AND pm.meta_key = %s AND pm.meta_value != ''",
                $pt,
                '_bre_meta_description'
            )
        );
        $stats[ $pt ] = array(
            'total'     => $total,
            'with_meta' => $with_meta,
            'pct'       => $total > 0 ? round( ( $with_meta / $total ) * 100 ) : 0,
        );
    }

    set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
    return $stats;
}
```

**Step 2: Cache crawler summary in render_dashboard()**

In `render_dashboard()`, add before the `include`:

```php
$crawlers = get_transient( 'bre_crawler_summary' );
if ( false === $crawlers ) {
    $crawlers = \BavarianRankEngine\Features\CrawlerLog::get_recent_summary( 30 );
    set_transient( 'bre_crawler_summary', $crawlers, 5 * MINUTE_IN_SECONDS );
}
```

Remove the inline `$crawlers = CrawlerLog::get_recent_summary(30)` line from `dashboard.php` — `$crawlers` now arrives via include scope from the controller.

**Step 3: Invalidate meta stats cache on meta save**

At the end of `MetaGenerator::saveMeta()`:

```php
delete_transient( 'bre_meta_stats' );
```

**Step 4: Run tests**

```bash
php composer.phar exec phpunit
```

**Step 5: Commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add includes/Admin/AdminMenu.php includes/Features/MetaGenerator.php includes/Admin/views/dashboard.php
git -C /var/www/dev/plugins/bre/bre-dev commit -m "perf: 5-minute transient caching for dashboard DB queries"
```

---

### Task 9: Version Bump to 1.2.2

**Files:**
- Modify: `seo-geo.php`
- Modify: `readme.txt`

**Step 1: Bump version in seo-geo.php**

- Plugin header: `Version: 1.2.1` → `Version: 1.2.2`
- Constant: `define( 'BRE_VERSION', '1.2.1' );` → `define( 'BRE_VERSION', '1.2.2' );`

**Step 2: Add changelog entry to readme.txt**

Under `== Changelog ==`, add:

```
= 1.2.2 =
* New: Dismissible welcome notice with 24 h auto-expiry and Bavarian flavour
* New: AI enable toggle with cost warning on AI Provider page
* New: Estimated token usage and cost in Status widget
* Improved: Dashboard UI — progress bars for meta coverage, styled quick links, crawler dot indicators
* Fix: Plugin Check warnings (variable definitions in template moved to controller)
* Fix: Hardcoded German strings in admin.js replaced with localized equivalents
* Perf: 5-minute transient caching for dashboard DB queries
```

**Step 3: Run full test suite**

```bash
php composer.phar exec phpunit
```
Expected: all tests PASS, zero failures or errors.

**Step 4: Final commit**

```bash
git -C /var/www/dev/plugins/bre/bre-dev add seo-geo.php readme.txt
git -C /var/www/dev/plugins/bre/bre-dev commit -m "release: bump to 1.2.2"
```
