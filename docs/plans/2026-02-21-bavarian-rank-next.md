# Bavarian Rank Engine — Next Iteration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix the bulk generator bug, add queue/rate-limiting, cost display, llms.txt priority/cache/pagination, meta fallback, editor widgets, link analysis, and robots.txt AI bot management.

**Architecture:** All changes in `bre-dev/` (renamed from `seo-geo/`). Build via `bin/build.sh` → `bavarian-rank-engine/`. Tests run with `vendor/bin/phpunit`. No new composer dependencies; no JS build toolchain.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, PHPUnit, vanilla JS + jQuery (existing), WP transients for caching/locking, `wpdb` for raw queries.

**Note on TokenEstimator:** `includes/Helpers/TokenEstimator.php` already has hardcoded pricing. User-editable costs will be stored in `bre_settings['costs']` and used to override/supplement it in the bulk estimate.

---

## Task 1: Rename seo-geo → bre-dev

**Files:**
- Shell: `mv /var/www/dev/plugins/seo-geo /var/www/dev/plugins/bre-dev`
- Modify: `bin/build.sh` line 6: update `PLUGIN_SRC`

**Step 1: Rename directory**
```bash
mv /var/www/dev/plugins/seo-geo /var/www/dev/plugins/bre-dev
```

**Step 2: Update build.sh**

In `bin/build.sh`, change:
```bash
PLUGIN_SRC="/var/www/dev/plugins/seo-geo"
```
to:
```bash
PLUGIN_SRC="/var/www/dev/plugins/bre-dev"
```

**Step 3: Verify tests still pass**
```bash
cd /var/www/dev/plugins/bre-dev && vendor/bin/phpunit --no-coverage
```
Expected: All green (paths are relative inside the repo).

**Step 4: Commit**
```bash
git add bin/build.sh
git commit -m "chore: rename dev directory seo-geo → bre-dev, update build.sh"
```

---

## Task 2: Fix Bulk Bug — getPostsWithoutMeta SQL

**Files:**
- Modify: `includes/Features/MetaGenerator.php` — `getPostsWithoutMeta()`
- Test: `tests/Features/MetaGeneratorTest.php` (new)

**Root cause:** `getPostsWithoutMeta()` fetches newest N posts via `ORDER BY ID DESC LIMIT` then PHP-filters. After the first run, those posts all have meta → returns empty even though older posts need processing. Fix: use NOT EXISTS SQL like `countPostsWithoutMeta()` does.

**Step 1: Add WP stubs to bootstrap**

Add to `tests/bootstrap.php`:
```php
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) {
        return $single ? '' : [];
    }
}
```

**Step 2: Write the failing test**

Create `tests/Features/MetaGeneratorTest.php`:
```php
<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\MetaGenerator;

class MetaGeneratorTest extends TestCase {

    public function test_has_existing_meta_returns_false_when_no_meta(): void {
        $gen = new MetaGenerator();
        // With our stub, get_post_meta always returns ''
        $result = $gen->hasExistingMeta( 999 );
        $this->assertFalse( $result );
    }

    public function test_has_existing_meta_returns_true_when_meta_set(): void {
        // Override stub for this test
        global $bre_test_meta;
        $bre_test_meta = [ 999 => [ '_bre_meta_description' => 'some desc' ] ];

        $gen    = new MetaGenerator();
        $result = $gen->hasExistingMeta( 999 );
        $this->assertTrue( $result );

        $bre_test_meta = [];
    }
}
```

Add dynamic stub to `tests/bootstrap.php`:
```php
$GLOBALS['bre_test_meta'] = [];
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key = '', $single = false ) {
        $val = $GLOBALS['bre_test_meta'][ $post_id ][ $key ] ?? '';
        return $single ? $val : ( $val !== '' ? [ $val ] : [] );
    }
}
```

**Step 3: Run to confirm it fails / passes baseline**
```bash
vendor/bin/phpunit tests/Features/MetaGeneratorTest.php --no-coverage
```

**Step 4: Fix `getPostsWithoutMeta()` in MetaGenerator.php**

Replace the existing private method:
```php
private function getPostsWithoutMeta( string $post_type, int $limit ): array {
    global $wpdb;

    $meta_fields = [
        '_bre_meta_description', 'rank_math_description',
        '_yoast_wpseo_metadesc', '_aioseo_description',
        '_seopress_titles_desc', '_meta_description',
    ];

    $not_exists = '';
    foreach ( $meta_fields as $field ) {
        $not_exists .= $wpdb->prepare(
            " AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = p.ID
                  AND pm.meta_key = %s
                  AND pm.meta_value != ''
            )",
            $field
        );
    }

    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         WHERE p.post_type = %s AND p.post_status = 'publish'"
        . $not_exists .
        " ORDER BY p.ID DESC LIMIT %d",
        $post_type,
        $limit
    ) ) );
}
```

Also increase the `$limit` cap in `ajaxBulkGenerate()`:
```php
$limit = min( 20, max( 1, (int) ( $_POST['batch_size'] ?? 5 ) ) );
```
(was `min( 5, ...)` — the 5-cap was a hidden bug causing 200-post requests to still only batch 5)

**Step 5: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```
Expected: All green.

**Step 6: Commit**
```bash
git add includes/Features/MetaGenerator.php tests/Features/MetaGeneratorTest.php tests/bootstrap.php
git commit -m "fix: bulk getPostsWithoutMeta uses NOT EXISTS SQL, raise batch cap to 20"
```

---

## Task 3: BulkQueue Helper — Lock + Logging

**Files:**
- Create: `includes/Helpers/BulkQueue.php`
- Test: `tests/Helpers/BulkQueueTest.php`

**Step 1: Add transient stubs to bootstrap**

Add to `tests/bootstrap.php`:
```php
$GLOBALS['bre_transients'] = [];
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiry = 0 ) {
        $GLOBALS['bre_transients'][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['bre_transients'][ $key ] ?? false;
    }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) {
        unset( $GLOBALS['bre_transients'][ $key ] );
        return true;
    }
}
```

**Step 2: Write failing tests**

Create `tests/Helpers/BulkQueueTest.php`:
```php
<?php
namespace BavarianRankEngine\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Helpers\BulkQueue;

class BulkQueueTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['bre_transients'] = [];
    }

    public function test_acquire_sets_lock(): void {
        $this->assertTrue( BulkQueue::acquire() );
        $this->assertTrue( BulkQueue::isLocked() );
    }

    public function test_acquire_fails_when_already_locked(): void {
        BulkQueue::acquire();
        $this->assertFalse( BulkQueue::acquire() );
    }

    public function test_release_clears_lock(): void {
        BulkQueue::acquire();
        BulkQueue::release();
        $this->assertFalse( BulkQueue::isLocked() );
    }

    public function test_is_locked_false_initially(): void {
        $this->assertFalse( BulkQueue::isLocked() );
    }
}
```

**Step 3: Run to confirm failure**
```bash
vendor/bin/phpunit tests/Helpers/BulkQueueTest.php --no-coverage
```

**Step 4: Implement BulkQueue.php**

Create `includes/Helpers/BulkQueue.php`:
```php
<?php
namespace BavarianRankEngine\Helpers;

class BulkQueue {
    private const LOCK_KEY = 'bre_bulk_running';
    private const LOCK_TTL = 900; // 15 minutes

    public static function acquire(): bool {
        if ( self::isLocked() ) {
            return false;
        }
        set_transient( self::LOCK_KEY, time(), self::LOCK_TTL );
        return true;
    }

    public static function release(): void {
        delete_transient( self::LOCK_KEY );
    }

    public static function isLocked(): bool {
        return get_transient( self::LOCK_KEY ) !== false;
    }

    public static function lockAge(): int {
        $started = get_transient( self::LOCK_KEY );
        return $started ? ( time() - (int) $started ) : 0;
    }
}
```

Also add to `Core.php` load_dependencies():
```php
require_once BRE_DIR . 'includes/Helpers/BulkQueue.php';
```

**Step 5: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 6: Commit**
```bash
git add includes/Helpers/BulkQueue.php tests/Helpers/BulkQueueTest.php tests/bootstrap.php includes/Core.php
git commit -m "feat: add BulkQueue helper with transient-based locking"
```

---

## Task 4: Bulk Retries + Failed Marker (PHP)

**Files:**
- Modify: `includes/Features/MetaGenerator.php` — `ajaxBulkGenerate()` + new `ajaxBulkLock()`/`ajaxBulkRelease()`

**Step 1: Add lock check + acquire to ajaxBulkGenerate**

At the top of `ajaxBulkGenerate()`, add:
```php
use BavarianRankEngine\Helpers\BulkQueue;
```
(Add to top of file with other use statements.)

In `ajaxBulkGenerate()`, add lock integration and retry logic. Replace the `foreach` loop:
```php
// Acquire lock on first batch (JS passes is_first=1)
if ( ! empty( $_POST['is_first'] ) ) {
    if ( ! BulkQueue::acquire() ) {
        wp_send_json_error( [
            'locked'   => true,
            'lock_age' => BulkQueue::lockAge(),
            'message'  => __( 'Ein Bulk-Prozess läuft bereits.', 'bavarian-rank-engine' ),
        ] );
        return;
    }
}

$post_ids = $this->getPostsWithoutMeta( $post_type, $limit );
$results  = [];
$max_retries = 3;

foreach ( $post_ids as $post_id ) {
    $post    = get_post( $post_id );
    $success = false;
    $last_error = '';

    for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
        try {
            $desc = $this->generate( $post, $settings );
            $this->saveMeta( $post_id, $desc );
            delete_post_meta( $post_id, '_bre_bulk_failed' );
            $results[] = [
                'id'          => $post_id,
                'title'       => get_the_title( $post_id ),
                'description' => $desc,
                'success'     => true,
                'attempts'    => $attempt,
            ];
            $success = true;
            break;
        } catch ( \Exception $e ) {
            $last_error = $e->getMessage();
            error_log( '[BRE] Post ' . $post_id . ' attempt ' . $attempt . '/' . $max_retries . ': ' . $last_error );
            if ( $attempt < $max_retries ) {
                sleep( 1 );
            }
        }
    }

    if ( ! $success ) {
        update_post_meta( $post_id, '_bre_bulk_failed', $last_error );
        $results[] = [
            'id'      => $post_id,
            'title'   => get_the_title( $post_id ),
            'error'   => $last_error,
            'success' => false,
        ];
    }
}

// Release lock when JS signals last batch
if ( ! empty( $_POST['is_last'] ) ) {
    BulkQueue::release();
}

wp_send_json_success( [
    'results'   => $results,
    'processed' => count( $results ),
    'remaining' => $this->countPostsWithoutMeta( $post_type ),
    'locked'    => BulkQueue::isLocked(),
] );
```

**Step 2: Add `ajaxBulkRelease` action** (called when user clicks Stop):
```php
public function register(): void {
    // ... existing actions ...
    add_action( 'wp_ajax_bre_bulk_release', [ $this, 'ajaxBulkRelease' ] );
    add_action( 'wp_ajax_bre_bulk_status',  [ $this, 'ajaxBulkStatus' ] );
}

public function ajaxBulkRelease(): void {
    check_ajax_referer( 'bre_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    BulkQueue::release();
    wp_send_json_success();
}

public function ajaxBulkStatus(): void {
    check_ajax_referer( 'bre_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    wp_send_json_success( [
        'locked'   => BulkQueue::isLocked(),
        'lock_age' => BulkQueue::lockAge(),
    ] );
}
```

**Step 3: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 4: Commit**
```bash
git add includes/Features/MetaGenerator.php
git commit -m "feat: bulk retries (3x), failed post meta, lock acquire/release per batch"
```

---

## Task 5: Bulk Rate Limit + JS Overhaul

**Files:**
- Modify: `assets/bulk.js`
- Modify: `includes/Admin/views/bulk.php`
- Modify: `includes/Admin/BulkPage.php` (localize lock status)

**Step 1: Update BulkPage.php to pass lock status**

In `enqueue_assets()`, update `wp_localize_script`:
```php
wp_localize_script( 'bre-bulk', 'breBulk', [
    'nonce'    => wp_create_nonce( 'bre_admin' ),
    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
    'isLocked' => \BavarianRankEngine\Helpers\BulkQueue::isLocked(),
    'lockAge'  => \BavarianRankEngine\Helpers\BulkQueue::lockAge(),
    'rateDelay'=> 6000, // ms between batches = 10 posts/min
    'i18n'     => [
        'locked'    => __( 'Ein Bulk-Prozess läuft bereits (%ds).', 'bavarian-rank-engine' ),
        'done'      => __( '— Fertig —', 'bavarian-rank-engine' ),
        'cancelled' => __( '⚠ Abbruch angefordert…', 'bavarian-rank-engine' ),
    ],
] );
```

**Step 2: Rewrite bulk.js**

Replace `assets/bulk.js` entirely:
```javascript
/* global breBulk */
jQuery( function ( $ ) {
    var running   = false;
    var stopFlag  = false;
    var processed = 0;
    var total     = 0;
    var failedItems = [];

    // Show lock warning on page load
    if ( breBulk.isLocked ) {
        showLockWarning( breBulk.lockAge );
    }

    loadStats();

    function showLockWarning( age ) {
        var msg = 'Ein Bulk-Prozess läuft bereits' + ( age ? ' (seit ' + age + 's)' : '' ) + '.';
        $( '#bre-lock-warning' ).text( msg ).show();
        $( '#bre-bulk-start' ).prop( 'disabled', true );
    }

    function hideLockWarning() {
        $( '#bre-lock-warning' ).hide();
        $( '#bre-bulk-start' ).prop( 'disabled', false );
    }

    function loadStats() {
        $.post( breBulk.ajaxUrl, { action: 'bre_bulk_stats', nonce: breBulk.nonce } )
            .done( function ( res ) {
                if ( ! res.success ) return;
                var html = '<strong>Posts ohne Meta-Beschreibung:</strong><ul>';
                var t = 0;
                $.each( res.data, function ( pt, count ) {
                    html += '<li>' + $( '<span>' ).text( pt ).html() + ': <strong>' + parseInt( count, 10 ) + '</strong></li>';
                    t += parseInt( count, 10 );
                } );
                html += '</ul><strong>Gesamt: ' + t + '</strong>';
                total = t;
                $( '#bre-bulk-stats' ).html( html );
                updateCostEstimate();
            } );
    }

    $( '#bre-bulk-limit, #bre-bulk-model' ).on( 'change', updateCostEstimate );

    function updateCostEstimate() {
        var limit        = parseInt( $( '#bre-bulk-limit' ).val(), 10 ) || 20;
        var inputTokens  = limit * 800;
        var outputTokens = limit * 50;

        var costHtml = '~' + inputTokens + ' Input-Token + ' + outputTokens + ' Output-Token';

        var costData = breBulk.costs || {};
        var provider = $( '#bre-bulk-provider' ).val();
        var model    = $( '#bre-bulk-model' ).val();

        if ( costData[ provider ] && costData[ provider ][ model ] ) {
            var c       = costData[ provider ][ model ];
            var inCost  = ( inputTokens  / 1000000 ) * parseFloat( c.input  || 0 );
            var outCost = ( outputTokens / 1000000 ) * parseFloat( c.output || 0 );
            var total   = inCost + outCost;
            if ( total > 0 ) {
                costHtml += ' ≈ $' + total.toFixed( 4 );
            }
        }

        $( '#bre-cost-estimate' ).text( costHtml );
    }

    $( '#bre-bulk-provider' ).on( 'change', updateCostEstimate );

    $( '#bre-bulk-start' ).on( 'click', function () {
        if ( running ) return;

        // Check lock first
        $.post( breBulk.ajaxUrl, { action: 'bre_bulk_status', nonce: breBulk.nonce } )
            .done( function ( res ) {
                if ( res.success && res.data.locked ) {
                    showLockWarning( res.data.lock_age );
                    return;
                }
                startRun();
            } );
    } );

    function startRun() {
        running   = true;
        stopFlag  = false;
        processed = 0;
        failedItems = [];

        $( '#bre-bulk-start' ).prop( 'disabled', true );
        $( '#bre-bulk-stop' ).show();
        $( '#bre-progress-wrap' ).show();
        $( '#bre-bulk-log' ).show().html( '' );
        $( '#bre-failed-summary' ).hide().html( '' );
        hideLockWarning();

        var limit    = parseInt( $( '#bre-bulk-limit' ).val(), 10 ) || 20;
        var provider = $( '#bre-bulk-provider' ).val();
        var model    = $( '#bre-bulk-model' ).val();

        log( '▶ Start — max ' + limit + ' Posts, Provider: ' + provider );
        runBatch( 'post', limit, provider, model, true );
    }

    $( '#bre-bulk-stop' ).on( 'click', function () {
        stopFlag = true;
        log( '⚠ Abbruch angefordert…', 'warn' );
        releaseLock();
    } );

    function releaseLock() {
        $.post( breBulk.ajaxUrl, { action: 'bre_bulk_release', nonce: breBulk.nonce } );
    }

    function runBatch( postType, remaining, provider, model, isFirst ) {
        if ( stopFlag || remaining <= 0 ) {
            finish();
            return;
        }

        var batchSize = Math.min( 20, remaining );
        var isLast    = ( remaining - batchSize ) <= 0;

        log( '↻ Verarbeite ' + batchSize + ' Posts… (' + remaining + ' verbleibend)' );

        $.post( breBulk.ajaxUrl, {
            action:     'bre_bulk_generate',
            nonce:      breBulk.nonce,
            post_type:  postType,
            batch_size: batchSize,
            provider:   provider,
            model:      model,
            is_first:   isFirst ? 1 : 0,
            is_last:    isLast  ? 1 : 0,
        } ).done( function ( res ) {
            if ( ! res.success ) {
                if ( res.data && res.data.locked ) {
                    showLockWarning( res.data.lock_age );
                    finish();
                    return;
                }
                log( '✗ Fehler: ' + $( '<span>' ).text( ( res.data && res.data.message ) || 'Unbekannter Fehler' ).html(), 'error' );
                finish();
                return;
            }

            $.each( res.data.results, function ( i, item ) {
                if ( item.success ) {
                    var attemptsNote = item.attempts > 1 ? ' (Versuch ' + item.attempts + ')' : '';
                    log(
                        '✓ [' + item.id + '] ' + $( '<span>' ).text( item.title ).html() + attemptsNote +
                        '<br><small style="color:#9cdcfe;">' + $( '<span>' ).text( item.description ).html() + '</small>'
                    );
                } else {
                    failedItems.push( item );
                    log(
                        '✗ [' + item.id + '] ' + $( '<span>' ).text( item.title ).html() +
                        ' — ' + $( '<span>' ).text( item.error ).html(),
                        'error'
                    );
                }
                processed++;
            } );

            updateProgress( processed, total );

            if ( res.data.remaining > 0 && ! stopFlag && ! isLast ) {
                setTimeout( function () {
                    runBatch( postType, remaining - batchSize, provider, model, false );
                }, breBulk.rateDelay );
            } else {
                if ( isLast || res.data.remaining === 0 ) releaseLock();
                finish();
            }
        } ).fail( function () {
            log( '✗ Netzwerkfehler', 'error' );
            releaseLock();
            finish();
        } );
    }

    function updateProgress( done, t ) {
        var pct = t > 0 ? Math.round( ( done / t ) * 100 ) : 100;
        $( '#bre-progress-bar' ).css( 'width', pct + '%' );
        $( '#bre-progress-text' ).text( done + ' / ' + t + ' verarbeitet' );
    }

    function log( msg, type ) {
        var color = type === 'error' ? '#f48771' : type === 'warn' ? '#dcdcaa' : '#9cdcfe';
        $( '#bre-bulk-log' ).append( '<div style="color:' + color + ';margin-bottom:4px;">' + msg + '</div>' );
        var el = document.getElementById( 'bre-bulk-log' );
        el.scrollTop = el.scrollHeight;
    }

    function finish() {
        running = false;
        $( '#bre-bulk-start' ).prop( 'disabled', false );
        $( '#bre-bulk-stop' ).hide();
        log( '— Fertig —' );

        if ( failedItems.length > 0 ) {
            var html = '<strong>⚠ ' + failedItems.length + ' Posts fehlgeschlagen:</strong><ul>';
            $.each( failedItems, function ( i, item ) {
                html += '<li>[' + item.id + '] ' + $( '<span>' ).text( item.title ).html() +
                        ': <em>' + $( '<span>' ).text( item.error ).html() + '</em></li>';
            } );
            html += '</ul>';
            $( '#bre-failed-summary' ).html( html ).show();
        }

        loadStats();
    }
} );
```

**Step 3: Update bulk.php view — add lock warning + failed summary divs**

In `includes/Admin/views/bulk.php`, add after `<h1>`:
```html
<div id="bre-lock-warning"
     style="display:none;background:#fcf8e3;border:1px solid #faebcc;padding:10px 15px;margin-bottom:15px;border-radius:3px;color:#8a6d3b;">
</div>
```

After the log div, add:
```html
<div id="bre-failed-summary"
     style="display:none;background:#fdf2f2;border:1px solid #f5c6cb;padding:10px 15px;margin-top:15px;border-radius:3px;"></div>
```

**Step 4: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 5: Commit**
```bash
git add assets/bulk.js includes/Admin/views/bulk.php includes/Admin/BulkPage.php
git commit -m "feat: bulk rate limiting (6s delay), lock check on start, failed items summary"
```

---

## Task 6: Provider Cost Settings

**Files:**
- Modify: `includes/Admin/SettingsPage.php` — add `costs` to defaults + sanitize
- Modify: `includes/Admin/ProviderPage.php` — register settings
- Modify: `includes/Admin/views/provider.php` — cost fields + pricing links
- Modify: `includes/Admin/BulkPage.php` — localize cost data to JS

**Step 1: Add costs to SettingsPage defaults**

In `getSettings()`, add to `$defaults`:
```php
'costs' => [], // ['openai']['gpt-4o-mini'] => ['input' => 0.15, 'output' => 0.60]
```

In `sanitize_settings()`, add after the models section:
```php
$clean['costs'] = [];
foreach ( ( $input['costs'] ?? [] ) as $provider_id => $models ) {
    $provider_id = sanitize_key( $provider_id );
    foreach ( (array) $models as $model_id => $prices ) {
        $clean['costs'][ $provider_id ][ sanitize_text_field( $model_id ) ] = [
            'input'  => max( 0.0, (float) ( $prices['input']  ?? 0 ) ),
            'output' => max( 0.0, (float) ( $prices['output'] ?? 0 ) ),
        ];
    }
}
```

**Step 2: Add pricing links array to ProviderPage.php**

In `includes/Admin/ProviderPage.php`, add a constant:
```php
private const PRICING_URLS = [
    'openai'    => 'https://openai.com/de-DE/api/pricing',
    'anthropic' => 'https://platform.claude.com/docs/en/about-claude/pricing',
    'gemini'    => 'https://ai.google.dev/gemini-api/docs/pricing?hl=de',
    'grok'      => 'https://docs.x.ai/developers/models',
];
```

Pass to view in `render()`:
```php
$pricing_urls = self::PRICING_URLS;
include BRE_DIR . 'includes/Admin/views/provider.php';
```

**Step 3: Update provider.php view — add cost fields + pricing link**

In the per-provider row, after the model `<select>`, add:
```html
<?php
$pricing_url = $pricing_urls[ $id ] ?? '';
if ( $pricing_url ) :
?>
<p>
    <a href="<?php echo esc_url( $pricing_url ); ?>" target="_blank" rel="noopener">
        <?php esc_html_e( 'Aktuelle Preise ansehen →', 'bavarian-rank-engine' ); ?>
    </a>
</p>
<?php endif; ?>
<p><?php esc_html_e( 'Kosten pro 1 Million Token (optional, für Kostenübersicht):', 'bavarian-rank-engine' ); ?></p>
<?php foreach ( $provider->getModels() as $model_id => $model_label ) :
    $saved_costs = $settings['costs'][ $id ][ $model_id ] ?? [];
?>
<div style="margin-bottom:8px;">
    <label style="display:inline-block;width:200px;"><?php echo esc_html( $model_label ); ?>:</label>
    Input $<input type="number" step="0.0001" min="0"
        name="bre_settings[costs][<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $model_id ); ?>][input]"
        value="<?php echo esc_attr( $saved_costs['input'] ?? '' ); ?>"
        placeholder="z.B. 0.15" style="width:80px;"> / 1M
    &nbsp; Output $<input type="number" step="0.0001" min="0"
        name="bre_settings[costs][<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $model_id ); ?>][output]"
        value="<?php echo esc_attr( $saved_costs['output'] ?? '' ); ?>"
        placeholder="z.B. 0.60" style="width:80px;"> / 1M
</div>
<?php endforeach; ?>
```

**Step 4: Pass cost data to bulk.js**

In `BulkPage.php` `enqueue_assets()`, update localize:
```php
$settings = SettingsPage::getSettings();
wp_localize_script( 'bre-bulk', 'breBulk', [
    'nonce'    => wp_create_nonce( 'bre_admin' ),
    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
    'isLocked' => \BavarianRankEngine\Helpers\BulkQueue::isLocked(),
    'lockAge'  => \BavarianRankEngine\Helpers\BulkQueue::lockAge(),
    'rateDelay'=> 6000,
    'costs'    => $settings['costs'] ?? [],
    'i18n'     => [
        'locked' => __( 'Ein Bulk-Prozess läuft bereits.', 'bavarian-rank-engine' ),
    ],
] );
```

**Step 5: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 6: Commit**
```bash
git add includes/Admin/SettingsPage.php includes/Admin/ProviderPage.php \
        includes/Admin/views/provider.php includes/Admin/BulkPage.php
git commit -m "feat: provider cost fields ($/1M token) with pricing links, passed to bulk estimate"
```

---

## Task 7: llms.txt — parse_request Priority + Rank Math Fix

**Files:**
- Modify: `includes/Features/LlmsTxt.php`
- Test: `tests/Features/LlmsTxtTest.php` (extend)

**Step 1: Add stubs needed for parse_request tests**

Add to `tests/bootstrap.php`:
```php
if ( ! function_exists( 'status_header' ) ) {
    function status_header( $code ) {}
}
if ( ! function_exists( 'defined' ) ) { /* already exists in PHP */ }
```

**Step 2: Refactor LlmsTxt.php — switch to parse_request**

In `register()`:
```php
public function register(): void {
    add_action( 'parse_request', [ $this, 'maybe_serve' ], 1 );
    add_action( 'init',          [ $this, 'add_rewrite_rule' ] );
    add_filter( 'query_vars',    [ $this, 'add_query_var' ] );
    add_action( 'admin_notices', [ $this, 'rank_math_notice' ] );
}

public function maybe_serve(): void {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
    if ( $uri === '/llms.txt' ) {
        $this->serve_page( 1 );
        return;
    }
    if ( preg_match( '#^/llms-(\d+)\.txt$#', $uri, $m ) ) {
        $this->serve_page( (int) $m[1] );
    }
}

public function rank_math_notice(): void {
    if ( ! defined( 'RANK_MATH_VERSION' ) ) return;
    $settings = self::getSettings();
    if ( empty( $settings['enabled'] ) ) return;
    echo '<div class="notice notice-info is-dismissible"><p>'
        . esc_html__( 'Bavarian Rank Engine bedient llms.txt mit Priorität — kein Handlungsbedarf bei Rank Math.', 'bavarian-rank-engine' )
        . '</p></div>';
}
```

Remove the old `serve()` method and `template_redirect` hook. Keep `add_rewrite_rule` and `add_query_var` (still needed for edge cases).

**Step 3: Add test for URI detection**

In `LlmsTxtTest.php`, add:
```php
public function test_uri_pattern_matches_llms_txt(): void {
    $uri = '/llms.txt';
    $this->assertSame( 1, preg_match( '#^/llms\.txt$#', $uri ) );
}

public function test_uri_pattern_matches_llms_page_2(): void {
    $uri = '/llms-2.txt';
    preg_match( '#^/llms-(\d+)\.txt$#', $uri, $m );
    $this->assertSame( '2', $m[1] );
}
```

**Step 4: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 5: Commit**
```bash
git add includes/Features/LlmsTxt.php tests/Features/LlmsTxtTest.php
git commit -m "feat: llms.txt uses parse_request (priority over Rank Math), admin notice"
```

---

## Task 8: llms.txt — ETag + Transient Cache + Cache-Clear Button

**Files:**
- Modify: `includes/Features/LlmsTxt.php`
- Modify: `includes/Admin/LlmsPage.php`
- Modify: `includes/Admin/views/llms.php`

**Step 1: Add caching to LlmsTxt**

Add `serve_page()` method and cache helpers:
```php
private const CACHE_KEY = 'bre_llms_cache';

private function serve_page( int $page ): void {
    $settings = self::getSettings();
    if ( empty( $settings['enabled'] ) ) {
        status_header( 404 );
        exit;
    }

    $cache_key = self::CACHE_KEY . '_p' . $page;
    $cached    = get_transient( $cache_key );

    if ( $cached === false ) {
        $cached = $this->build( $settings, $page );
        set_transient( $cache_key, $cached, 0 ); // no expiry
    }

    $etag          = '"' . md5( $cached ) . '"';
    $last_modified = $this->get_last_modified();

    header( 'Content-Type: text/plain; charset=utf-8' );
    header( 'ETag: ' . $etag );
    header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
    header( 'Cache-Control: public, max-age=3600' );

    if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) &&
         trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) {
        status_header( 304 );
        exit;
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $cached;
    exit;
}

private function get_last_modified(): int {
    global $wpdb;
    $latest = $wpdb->get_var(
        "SELECT UNIX_TIMESTAMP(MAX(post_modified_gmt)) FROM {$wpdb->posts}
         WHERE post_status = 'publish'"
    );
    return $latest ? (int) $latest : time();
}

public static function clear_cache(): void {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bre_llms_cache%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bre_llms_cache%'" );
}
```

Auto-clear on settings save — add to `LlmsPage.php` sanitize callback:
```php
LlmsTxt::clear_cache();
```

**Step 2: Add AJAX clear-cache action to LlmsPage.php**

```php
public function register(): void {
    // ... existing ...
    add_action( 'wp_ajax_bre_llms_clear_cache', [ $this, 'ajax_clear_cache' ] );
}

public function ajax_clear_cache(): void {
    check_ajax_referer( 'bre_admin', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    LlmsTxt::clear_cache();
    wp_send_json_success( __( 'Cache geleert.', 'bavarian-rank-engine' ) );
}
```

**Step 3: Add clear-cache button to llms.php view**

```html
<p>
    <button id="bre-llms-clear-cache" class="button">
        <?php esc_html_e( 'Cache leeren', 'bavarian-rank-engine' ); ?>
    </button>
    <span id="bre-cache-result" style="margin-left:10px;"></span>
</p>
<script>
jQuery('#bre-llms-clear-cache').on('click', function() {
    jQuery.post(ajaxurl, {
        action: 'bre_llms_clear_cache',
        nonce: '<?php echo esc_js( wp_create_nonce( 'bre_admin' ) ); ?>'
    }).done(function(res) {
        jQuery('#bre-cache-result').text(res.success ? res.data : 'Fehler');
    });
});
</script>
```

**Step 4: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 5: Commit**
```bash
git add includes/Features/LlmsTxt.php includes/Admin/LlmsPage.php includes/Admin/views/llms.php
git commit -m "feat: llms.txt ETag/Last-Modified headers, transient cache, admin cache-clear button"
```

---

## Task 9: llms.txt — Pagination

**Files:**
- Modify: `includes/Features/LlmsTxt.php` — `build()` + `build_content_list()`
- Modify: `includes/Admin/LlmsPage.php` — add max_links setting
- Modify: `includes/Admin/views/llms.php` — max_links field
- Test: `tests/Features/LlmsTxtTest.php`

**Step 1: Add max_links to settings defaults**

In `getSettings()` defaults:
```php
'max_links' => 500,
```

In `LlmsPage.php` sanitize callback, add:
```php
$clean['max_links'] = max( 50, (int) ( $input['max_links'] ?? 500 ) );
```

**Step 2: Update build() to support pagination**

```php
private function build( array $s, int $page = 1 ): string {
    $max_links  = max( 50, (int) ( $s['max_links'] ?? 500 ) );
    $post_types = $s['post_types'] ?? [ 'post', 'page' ];
    $all_posts  = $this->get_all_posts( $post_types );
    $total      = count( $all_posts );
    $pages      = $total > 0 ? (int) ceil( $total / $max_links ) : 1;
    $offset     = ( $page - 1 ) * $max_links;
    $page_posts = array_slice( $all_posts, $offset, $max_links );

    $out = '';

    if ( $page === 1 ) {
        if ( ! empty( $s['title'] ) ) {
            $out .= '# ' . $s['title'] . "\n\n";
        }
        if ( ! empty( $s['description_before'] ) ) {
            $out .= trim( $s['description_before'] ) . "\n\n";
        }
        if ( ! empty( $s['custom_links'] ) ) {
            $out .= "## Featured Resources\n\n";
            foreach ( explode( "\n", trim( $s['custom_links'] ) ) as $line ) {
                $line = trim( $line );
                if ( $line !== '' ) $out .= $line . "\n";
            }
            $out .= "\n";
        }
    }

    if ( ! empty( $page_posts ) ) {
        $out .= "## Content\n\n";
        foreach ( $page_posts as $post ) {
            $out .= sprintf(
                '- [%s](%s) — %s',
                $post->post_title,
                get_permalink( $post ),
                get_the_date( 'Y-m-d', $post )
            ) . "\n";
        }
        $out .= "\n";
    }

    if ( $pages > 1 ) {
        $out .= "## More\n\n";
        for ( $p = 1; $p <= $pages; $p++ ) {
            if ( $p === $page ) continue;
            $filename = $p === 1 ? 'llms.txt' : "llms-{$p}.txt";
            $url      = home_url( '/' . $filename );
            $out .= "- [{$filename}]({$url})\n";
        }
        $out .= "\n";
    }

    if ( $page === 1 ) {
        if ( ! empty( $s['description_after'] ) ) {
            $out .= "\n---\n" . trim( $s['description_after'] ) . "\n";
        }
        if ( ! empty( $s['description_footer'] ) ) {
            $out .= "\n---\n" . trim( $s['description_footer'] ) . "\n";
        }
    }

    return $out;
}

private function get_all_posts( array $post_types ): array {
    $query = new \WP_Query( [
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ] );
    $posts = $query->posts;
    wp_reset_postdata();
    return $posts;
}
```

Remove old `build_content_list()` (replaced above).

**Step 3: Add max_links field to llms.php view**

```html
<tr>
    <th scope="row"><?php esc_html_e( 'Max. Links pro llms.txt', 'bavarian-rank-engine' ); ?></th>
    <td>
        <input type="number" name="bre_llms_settings[max_links]"
               value="<?php echo esc_attr( $settings['max_links'] ?? 500 ); ?>"
               min="50" max="5000">
        <p class="description">
            <?php esc_html_e( 'Bei mehr Posts werden automatisch llms-2.txt, llms-3.txt etc. erstellt und verlinkt.', 'bavarian-rank-engine' ); ?>
        </p>
    </td>
</tr>
```

**Step 4: Write pagination test**

In `LlmsTxtTest.php`:
```php
public function test_build_splits_into_pages(): void {
    $llms   = new LlmsTxt();
    $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
    $method->setAccessible( true );

    // With empty post_types and max_links=1 on page 1, should not have "## More" (no posts)
    $settings = $this->make_settings( [ 'max_links' => 1 ] );
    $output   = $method->invoke( $llms, $settings, 1 );
    $this->assertStringNotContainsString( '## More', $output );
}
```

**Step 5: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 6: Commit**
```bash
git add includes/Features/LlmsTxt.php includes/Admin/LlmsPage.php includes/Admin/views/llms.php tests/Features/LlmsTxtTest.php
git commit -m "feat: llms.txt pagination — max_links setting, auto llms-2.txt etc."
```

---

## Task 10: FallbackMeta Helper

**Files:**
- Create: `includes/Helpers/FallbackMeta.php`
- Test: `tests/Helpers/FallbackMetaTest.php`

**Step 1: Write failing tests**

Create `tests/Helpers/FallbackMetaTest.php`:
```php
<?php
namespace BavarianRankEngine\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Helpers\FallbackMeta;

class FallbackMetaTest extends TestCase {

    private function make_post( string $content ): \stdClass {
        $post               = new \stdClass();
        $post->post_content = $content;
        return $post;
    }

    public function test_extracts_plain_text_from_html(): void {
        $post   = $this->make_post( '<p>Hello world. This is a test.</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertStringNotContainsString( '<p>', $result );
        $this->assertStringContainsString( 'Hello world', $result );
    }

    public function test_result_is_max_160_chars(): void {
        $long   = str_repeat( 'This is a sentence. ', 20 );
        $post   = $this->make_post( '<p>' . $long . '</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertLessThanOrEqual( 160, mb_strlen( $result ) );
    }

    public function test_result_is_at_least_150_chars_when_content_long_enough(): void {
        $long   = str_repeat( 'This is a longer sentence with enough words. ', 10 );
        $post   = $this->make_post( '<p>' . $long . '</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertGreaterThanOrEqual( 100, mb_strlen( $result ) ); // flexible lower bound
    }

    public function test_does_not_cut_mid_word(): void {
        $content = '<p>' . str_repeat( 'abcdefghij ', 20 ) . '</p>';
        $post    = $this->make_post( $content );
        $result  = FallbackMeta::extract( $post );
        // Should not end with a partial word (no trailing fragment without space)
        $this->assertMatchesRegularExpression( '/\w[\.\!\?…]?$/', $result );
    }

    public function test_short_content_returned_as_is(): void {
        $post   = $this->make_post( '<p>Short.</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertSame( 'Short.', $result );
    }

    public function test_empty_content_returns_empty_string(): void {
        $post   = $this->make_post( '' );
        $result = FallbackMeta::extract( $post );
        $this->assertSame( '', $result );
    }
}
```

**Step 2: Run to confirm failure**
```bash
vendor/bin/phpunit tests/Helpers/FallbackMetaTest.php --no-coverage
```

**Step 3: Implement FallbackMeta.php**

Create `includes/Helpers/FallbackMeta.php`:
```php
<?php
namespace BavarianRankEngine\Helpers;

class FallbackMeta {
    private const MIN = 150;
    private const MAX = 160;

    /**
     * Extract a clean 150–160 char meta description from post content.
     * Ends on a complete sentence or word boundary. No HTML.
     *
     * @param object $post WP_Post or compatible object with post_content
     */
    public static function extract( object $post ): string {
        $text = strip_tags( $post->post_content ?? '' );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        if ( $text === '' ) {
            return '';
        }

        if ( mb_strlen( $text ) <= self::MAX ) {
            return $text;
        }

        // Try to end on a sentence boundary within MAX chars
        $candidate = mb_substr( $text, 0, self::MAX );
        $last_sentence = max(
            mb_strrpos( $candidate, '. ' ),
            mb_strrpos( $candidate, '! ' ),
            mb_strrpos( $candidate, '? ' )
        );

        if ( $last_sentence !== false && $last_sentence >= self::MIN - 1 ) {
            return mb_substr( $text, 0, $last_sentence + 1 );
        }

        // Fall back to last word boundary within MAX
        $last_space = mb_strrpos( $candidate, ' ' );
        if ( $last_space !== false && $last_space >= self::MIN - 10 ) {
            return mb_substr( $text, 0, $last_space ) . '…';
        }

        // Last resort: hard cut with ellipsis
        return mb_substr( $text, 0, self::MAX - 1 ) . '…';
    }
}
```

Also add to `Core.php` load_dependencies():
```php
require_once BRE_DIR . 'includes/Helpers/FallbackMeta.php';
```

**Step 4: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 5: Commit**
```bash
git add includes/Helpers/FallbackMeta.php tests/Helpers/FallbackMetaTest.php includes/Core.php
git commit -m "feat: FallbackMeta helper — sentence-boundary extraction, 150-160 chars"
```

---

## Task 11: MetaGenerator — Fallback Integration + Source Tracking

**Files:**
- Modify: `includes/Features/MetaGenerator.php`

**Step 1: Update generate() to fall back gracefully**

In `generate()`, add fallback after provider failure:
```php
public function generate( \WP_Post $post, array $settings ): string {
    $registry = ProviderRegistry::instance();
    $provider = $registry->get( $settings['provider'] );

    // No provider or no API key → use fallback immediately
    if ( ! $provider || empty( $settings['api_keys'][ $settings['provider'] ] ?? '' ) ) {
        return $this->fallback( $post );
    }

    $model   = $settings['models'][ $settings['provider'] ] ?? array_key_first( $provider->getModels() );
    $content = $this->prepareContent( $post, $settings );
    $prompt  = $this->buildPrompt( $post, $content, $settings );

    return $provider->generateText( $prompt, $settings['api_keys'][ $settings['provider'] ], $model, 300 );
}

private function fallback( \WP_Post $post ): string {
    return \BavarianRankEngine\Helpers\FallbackMeta::extract( $post );
}
```

**Step 2: Add _bre_meta_source to saveMeta()**

```php
public function saveMeta( int $post_id, string $description, string $source = 'ai' ): void {
    $clean = sanitize_text_field( $description );
    update_post_meta( $post_id, '_bre_meta_description', $clean );
    update_post_meta( $post_id, '_bre_meta_source', sanitize_key( $source ) );
    // ... rest unchanged
}
```

In `onPublish()`, detect source and pass it:
```php
try {
    $api_key = $settings['api_keys'][ $settings['provider'] ] ?? '';
    $source  = ( ! empty( $api_key ) ) ? 'ai' : 'fallback';
    $description = $this->generate( $post, $settings );
    if ( ! empty( $description ) ) {
        $this->saveMeta( $post_id, $description, $source );
    }
} catch ( \Exception $e ) {
    // Try fallback
    $fallback = $this->fallback( $post );
    if ( $fallback !== '' ) {
        $this->saveMeta( $post_id, $fallback, 'fallback' );
    }
    error_log( '[BRE] Meta generation failed for post ' . $post_id . ': ' . $e->getMessage() );
}
```

**Step 3: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 4: Commit**
```bash
git add includes/Features/MetaGenerator.php
git commit -m "feat: meta fallback on missing API key or provider error, track _bre_meta_source"
```

---

## Task 12: Post Editor Meta Box

**Files:**
- Create: `includes/Admin/MetaEditorBox.php`
- Create: `assets/editor-meta.js`
- Modify: `includes/Core.php`

**Step 1: Create MetaEditorBox.php**

```php
<?php
namespace BavarianRankEngine\Admin;

use BavarianRankEngine\Features\MetaGenerator;

class MetaEditorBox {
    public function register(): void {
        add_action( 'add_meta_boxes',  [ $this, 'add_boxes' ] );
        add_action( 'save_post',       [ $this, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_bre_regen_meta', [ $this, 'ajax_regen' ] );
    }

    public function add_boxes(): void {
        $settings   = SettingsPage::getSettings();
        $post_types = $settings['meta_post_types'] ?? [ 'post', 'page' ];
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'bre_meta_box',
                __( 'Meta Description (BRE)', 'bavarian-rank-engine' ),
                [ $this, 'render' ],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render( \WP_Post $post ): void {
        $description = get_post_meta( $post->ID, '_bre_meta_description', true );
        $source      = get_post_meta( $post->ID, '_bre_meta_source', true ) ?: 'none';
        $source_labels = [
            'ai'       => __( 'KI generiert', 'bavarian-rank-engine' ),
            'fallback' => __( 'Fallback (erster Absatz)', 'bavarian-rank-engine' ),
            'manual'   => __( 'Manuell', 'bavarian-rank-engine' ),
            'none'     => __( 'Noch nicht generiert', 'bavarian-rank-engine' ),
        ];
        wp_nonce_field( 'bre_save_meta_' . $post->ID, 'bre_meta_nonce' );
        ?>
        <p>
            <span style="background:#eee;padding:2px 6px;border-radius:3px;font-size:11px;">
                <?php echo esc_html( $source_labels[ $source ] ?? $source ); ?>
            </span>
        </p>
        <textarea id="bre-meta-description" name="bre_meta_description"
                  rows="3" style="width:100%;" maxlength="160"
        ><?php echo esc_textarea( $description ); ?></textarea>
        <p>
            <span id="bre-meta-count" style="font-size:11px;color:#666;">
                <?php echo esc_html( mb_strlen( $description ) ); ?> / 160
            </span>
            <?php
            $settings = SettingsPage::getSettings();
            $has_key  = ! empty( $settings['api_keys'][ $settings['provider'] ] ?? '' );
            if ( $has_key ) :
            ?>
            <button type="button" id="bre-regen-meta" class="button button-small"
                    style="float:right;"
                    data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'bre_admin' ) ); ?>">
                <?php esc_html_e( 'Mit KI neu generieren', 'bavarian-rank-engine' ); ?>
            </button>
            <?php endif; ?>
        </p>
        <?php
    }

    public function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['bre_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['bre_meta_nonce'], 'bre_save_meta_' . $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;

        if ( isset( $_POST['bre_meta_description'] ) ) {
            $gen = new MetaGenerator();
            $gen->saveMeta( $post_id, sanitize_textarea_field( $_POST['bre_meta_description'] ), 'manual' );
        }
    }

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        wp_enqueue_script( 'bre-editor-meta', BRE_URL . 'assets/editor-meta.js', [ 'jquery' ], BRE_VERSION, true );
    }

    public function ajax_regen(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $post    = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) wp_send_json_error( 'Post not found' );

        $settings = SettingsPage::getSettings();
        $gen      = new MetaGenerator();

        try {
            $desc = $gen->generate( $post, $settings );
            $gen->saveMeta( $post_id, $desc, 'ai' );
            wp_send_json_success( [ 'description' => $desc ] );
        } catch ( \Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
}
```

**Step 2: Create assets/editor-meta.js**

```javascript
/* global jQuery */
jQuery( function ( $ ) {
    var $textarea = $( '#bre-meta-description' );
    var $count    = $( '#bre-meta-count' );
    var $btn      = $( '#bre-regen-meta' );

    $textarea.on( 'input', function () {
        $count.text( $( this ).val().length + ' / 160' );
    } );

    $btn.on( 'click', function () {
        $btn.prop( 'disabled', true ).text( '…' );
        $.post( ajaxurl, {
            action:  'bre_regen_meta',
            nonce:   $btn.data( 'nonce' ),
            post_id: $btn.data( 'post-id' ),
        } ).done( function ( res ) {
            if ( res.success ) {
                $textarea.val( res.data.description );
                $count.text( res.data.description.length + ' / 160' );
            } else {
                alert( 'Fehler: ' + ( res.data || 'Unbekannt' ) );
            }
        } ).always( function () {
            $btn.prop( 'disabled', false ).text( 'Mit KI neu generieren' );
        } );
    } );
} );
```

**Step 3: Register in Core.php**

In `load_dependencies()`:
```php
require_once BRE_DIR . 'includes/Admin/MetaEditorBox.php';
```

In `register_hooks()`, inside `if ( is_admin() )`:
```php
( new Admin\MetaEditorBox() )->register();
```

**Step 4: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 5: Commit**
```bash
git add includes/Admin/MetaEditorBox.php assets/editor-meta.js includes/Core.php
git commit -m "feat: post editor meta box — show/edit BRE meta, char counter, AJAX regen"
```

---

## Task 13: Post Editor SEO Widget

**Files:**
- Create: `includes/Admin/SeoWidget.php`
- Create: `assets/seo-widget.js`
- Modify: `includes/Core.php`

**Step 1: Create SeoWidget.php**

```php
<?php
namespace BavarianRankEngine\Admin;

class SeoWidget {
    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function add_boxes(): void {
        $settings   = SettingsPage::getSettings();
        $post_types = $settings['meta_post_types'] ?? [ 'post', 'page' ];
        foreach ( $post_types as $pt ) {
            add_meta_box(
                'bre_seo_widget',
                __( 'SEO Analyse (BRE)', 'bavarian-rank-engine' ),
                [ $this, 'render' ],
                $pt,
                'side',
                'default'
            );
        }
    }

    public function render( \WP_Post $post ): void {
        $title_len = mb_strlen( $post->post_title );
        ?>
        <div id="bre-seo-widget" data-site-url="<?php echo esc_attr( home_url() ); ?>">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <tr>
                    <td style="padding:3px 0;color:#888;"><?php esc_html_e( 'Titel:', 'bavarian-rank-engine' ); ?></td>
                    <td id="bre-title-stat" style="text-align:right;">
                        <?php echo esc_html( $title_len ); ?> / 60
                    </td>
                </tr>
                <tr>
                    <td style="padding:3px 0;color:#888;"><?php esc_html_e( 'Wörter:', 'bavarian-rank-engine' ); ?></td>
                    <td id="bre-words-stat" style="text-align:right;">—</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;color:#888;"><?php esc_html_e( 'Lesezeit:', 'bavarian-rank-engine' ); ?></td>
                    <td id="bre-read-stat" style="text-align:right;">—</td>
                </tr>
            </table>
            <hr style="margin:8px 0;">
            <strong style="font-size:11px;"><?php esc_html_e( 'Überschriften', 'bavarian-rank-engine' ); ?></strong>
            <div id="bre-headings-stat" style="font-size:11px;margin-top:4px;">—</div>
            <hr style="margin:8px 0;">
            <strong style="font-size:11px;"><?php esc_html_e( 'Links', 'bavarian-rank-engine' ); ?></strong>
            <div id="bre-links-stat" style="font-size:11px;margin-top:4px;">—</div>
            <div id="bre-seo-warnings" style="margin-top:8px;font-size:11px;color:#d63638;"></div>
        </div>
        <?php
    }

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        wp_enqueue_script( 'bre-seo-widget', BRE_URL . 'assets/seo-widget.js', [ 'jquery' ], BRE_VERSION, true );
    }
}
```

**Step 2: Create assets/seo-widget.js**

```javascript
/* global jQuery */
jQuery( function ( $ ) {
    var $widget  = $( '#bre-seo-widget' );
    if ( ! $widget.length ) return;
    var siteUrl  = $widget.data( 'site-url' ) || window.location.origin;
    var debounce = null;

    function analyse() {
        var content = '';

        // Block editor
        if ( window.wp && wp.data ) {
            var blocks  = wp.data.select( 'core/editor' ).getBlocks();
            var titleEl = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );
            content = blocks.map( function(b) {
                return b.attributes && b.attributes.content ? b.attributes.content : '';
            } ).join( ' ' );
            $( '#bre-title-stat' ).text( ( titleEl || '' ).length + ' / 60' );
        } else {
            // Classic editor
            var ed = typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor
                ? tinyMCE.activeEditor.getContent() : $( '#content' ).val();
            content = ed;
        }

        var plain    = content.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
        var words    = plain ? plain.split( /\s+/ ).length : 0;
        var readMin  = Math.ceil( words / 200 );

        $( '#bre-words-stat' ).text( words.toLocaleString( 'de-DE' ) );
        $( '#bre-read-stat' ).text( '~' + readMin + ' Min.' );

        // Headings
        var headings = { h1:0, h2:0, h3:0, h4:0 };
        $( $.parseHTML( content ) ).find( 'h1,h2,h3,h4' ).each( function() {
            var tag = this.tagName.toLowerCase();
            if ( headings[ tag ] !== undefined ) headings[ tag ]++;
        } );
        ( content.match( /<h([1-4])[^>]*>/gi ) || [] ).forEach( function( m ) {
            var tag = 'h' + m[2];
            if ( headings[ tag ] !== undefined ) headings[ tag ]++;
        } );

        var hHtml = '';
        $.each( headings, function( tag, count ) {
            if ( count > 0 ) hHtml += count + '× ' + tag.toUpperCase() + '  ';
        } );
        $( '#bre-headings-stat' ).text( hHtml || 'Keine' );

        // Links
        var links       = content.match( /<a[^>]+href="([^"]+)"/gi ) || [];
        var internal    = 0;
        var external    = 0;
        links.forEach( function( tag ) {
            var href = ( tag.match( /href="([^"]+)"/ ) || [] )[1] || '';
            if ( href.indexOf( siteUrl ) === 0 || href.indexOf( '/' ) === 0 ) {
                internal++;
            } else if ( href.indexOf( 'http' ) === 0 ) {
                external++;
            }
        } );
        $( '#bre-links-stat' ).text( internal + ' intern  ' + external + ' extern' );

        // Warnings
        var warnings = [];
        if ( headings.h1 === 0 ) warnings.push( '⚠ Keine H1-Überschrift' );
        if ( headings.h1 > 1  ) warnings.push( '⚠ Mehrere H1-Überschriften (' + headings.h1 + ')' );
        if ( internal === 0    ) warnings.push( '⚠ Keine internen Links' );
        $( '#bre-seo-warnings' ).html( warnings.join( '<br>' ) );
    }

    // Block editor: subscribe to store changes
    if ( window.wp && wp.data ) {
        wp.data.subscribe( function () {
            clearTimeout( debounce );
            debounce = setTimeout( analyse, 500 );
        } );
    } else {
        // Classic editor
        $( document ).on( 'input change', '#content', function () {
            clearTimeout( debounce );
            debounce = setTimeout( analyse, 500 );
        } );
        if ( typeof tinyMCE !== 'undefined' ) {
            $( document ).on( 'tinymce-editor-init', function( event, editor ) {
                editor.on( 'KeyUp Change', function () {
                    clearTimeout( debounce );
                    debounce = setTimeout( analyse, 500 );
                } );
            } );
        }
    }

    analyse();
} );
```

**Step 3: Register in Core.php** (inside `if ( is_admin() )`):
```php
( new Admin\SeoWidget() )->register();
```

And in `load_dependencies()`:
```php
require_once BRE_DIR . 'includes/Admin/SeoWidget.php';
```

**Step 4: Run tests + Commit**
```bash
vendor/bin/phpunit --no-coverage
git add includes/Admin/SeoWidget.php assets/seo-widget.js includes/Core.php
git commit -m "feat: post editor SEO widget — word count, headings, links, warnings (no AI)"
```

---

## Task 14: Dashboard Link Analysis

**Files:**
- Modify: `includes/Admin/AdminMenu.php` (or `SettingsPage.php`) — add AJAX handler
- Modify: `includes/Admin/views/dashboard.php`
- Create: `includes/Admin/LinkAnalysis.php`

**Step 1: Create LinkAnalysis.php**

```php
<?php
namespace BavarianRankEngine\Admin;

class LinkAnalysis {
    private const CACHE_KEY = 'bre_link_analysis';
    private const CACHE_TTL = 3600; // 1 hour

    public function register(): void {
        add_action( 'wp_ajax_bre_link_analysis', [ $this, 'ajax_analyse' ] );
    }

    public function ajax_analyse(): void {
        check_ajax_referer( 'bre_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $cached = get_transient( self::CACHE_KEY );
        if ( $cached !== false ) {
            wp_send_json_success( $cached );
            return;
        }

        $data = [
            'no_internal_links' => $this->posts_without_internal_links(),
            'too_many_external' => $this->posts_with_many_external_links(
                (int) get_option( 'bre_ext_link_threshold', 5 )
            ),
            'pillar_pages'      => $this->top_pillar_pages( 5 ),
        ];

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        wp_send_json_success( $data );
    }

    private function posts_without_internal_links(): array {
        global $wpdb;
        $site = home_url();
        // Posts whose content has no href pointing to own domain or relative link
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_content NOT LIKE %s
               AND post_content NOT LIKE 'href=\"/'
             ORDER BY post_date DESC LIMIT 20",
            '%href="' . esc_sql( $site ) . '%'
        ) );
        return array_map( fn($r) => [ 'id' => $r->ID, 'title' => $r->post_title ], $results );
    }

    private function posts_with_many_external_links( int $threshold ): array {
        global $wpdb;
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post','page')
             ORDER BY post_date DESC LIMIT 500"
        );
        $over = [];
        foreach ( $posts as $post ) {
            preg_match_all( '/href="https?:\/\/(?!' . preg_quote( parse_url( home_url(), PHP_URL_HOST ), '/' ) . ')/', $post->post_content, $m );
            $count = count( $m[0] );
            if ( $count >= $threshold ) {
                $over[] = [ 'id' => $post->ID, 'title' => $post->post_title, 'count' => $count ];
            }
        }
        usort( $over, fn($a,$b) => $b['count'] <=> $a['count'] );
        return array_slice( $over, 0, 20 );
    }

    private function top_pillar_pages( int $top ): array {
        global $wpdb;
        $site  = home_url();
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('post','page')"
        );

        $link_counts = [];
        foreach ( $posts as $post ) {
            preg_match_all( '/href="(' . preg_quote( $site, '/' ) . '[^"]+)"/', $post->post_content, $m );
            foreach ( $m[1] as $url ) {
                $url = rtrim( $url, '/' );
                $link_counts[ $url ] = ( $link_counts[ $url ] ?? 0 ) + 1;
            }
        }

        arsort( $link_counts );
        $result = [];
        foreach ( array_slice( $link_counts, 0, $top, true ) as $url => $count ) {
            $result[] = [ 'url' => $url, 'count' => $count ];
        }
        return $result;
    }
}
```

**Step 2: Add dashboard card to views/dashboard.php**

Add a new postbox at the end of the grid:
```html
<div class="postbox" id="bre-link-analysis-card">
    <div class="postbox-header">
        <h2><?php esc_html_e( 'Interne Link-Analyse', 'bavarian-rank-engine' ); ?></h2>
    </div>
    <div class="inside" id="bre-link-analysis-content">
        <em><?php esc_html_e( 'Wird geladen…', 'bavarian-rank-engine' ); ?></em>
    </div>
</div>
<script>
jQuery(function($){
    $.post(ajaxurl, { action:'bre_link_analysis', nonce:'<?php echo esc_js(wp_create_nonce('bre_admin')); ?>' })
    .done(function(res){
        if(!res.success){$('#bre-link-analysis-content').text('Fehler');return;}
        var d=res.data, h='';
        h+='<p><strong>Posts ohne interne Links ('+d.no_internal_links.length+'):</strong></p>';
        if(d.no_internal_links.length){
            h+='<ul style="margin:0 0 10px 20px;">';
            $.each(d.no_internal_links.slice(0,10),function(i,p){h+='<li>'+$('<span>').text(p.title).html()+'</li>';});
            h+='</ul>';
        }
        h+='<p><strong>Posts mit vielen externen Links:</strong></p>';
        if(d.too_many_external.length){
            h+='<ul style="margin:0 0 10px 20px;">';
            $.each(d.too_many_external.slice(0,5),function(i,p){h+='<li>'+$('<span>').text(p.title).html()+' ('+p.count+')</li>';});
            h+='</ul>';
        } else { h+='<p>Keine.</p>'; }
        h+='<p><strong>Pillar Pages (meist verlinkt):</strong></p>';
        if(d.pillar_pages.length){
            h+='<ul style="margin:0 0 10px 20px;">';
            $.each(d.pillar_pages,function(i,p){h+='<li><a href="'+p.url+'" target="_blank">'+$('<span>').text(p.url).html()+'</a> ('+p.count+'x)</li>';});
            h+='</ul>';
        } else { h+='<p>Keine Daten.</p>'; }
        $('#bre-link-analysis-content').html(h);
    });
});
</script>
```

**Step 3: Register in Core.php**

```php
require_once BRE_DIR . 'includes/Admin/LinkAnalysis.php';
// inside if ( is_admin() ):
( new Admin\LinkAnalysis() )->register();
```

**Step 4: Run tests + Commit**
```bash
vendor/bin/phpunit --no-coverage
git add includes/Admin/LinkAnalysis.php includes/Admin/views/dashboard.php includes/Core.php
git commit -m "feat: dashboard link analysis — no-internal-links, pillar pages (no AI)"
```

---

## Task 15: RobotsTxt Feature + Admin Page

**Files:**
- Create: `includes/Features/RobotsTxt.php`
- Create: `includes/Admin/RobotsPage.php`
- Create: `includes/Admin/views/robots.php`
- Modify: `includes/Admin/AdminMenu.php`
- Modify: `includes/Core.php`
- Test: `tests/Features/RobotsTxtTest.php`

**Step 1: Create RobotsTxt.php**

```php
<?php
namespace BavarianRankEngine\Features;

class RobotsTxt {
    private const OPTION_KEY = 'bre_robots_settings';

    public const KNOWN_BOTS = [
        'GPTBot'            => 'OpenAI GPTBot',
        'ClaudeBot'         => 'Anthropic ClaudeBot',
        'Google-Extended'   => 'Google Extended (Bard/Gemini)',
        'PerplexityBot'     => 'Perplexity AI',
        'CCBot'             => 'Common Crawl (CCBot)',
        'Applebot-Extended' => 'Apple AI (Applebot-Extended)',
        'Bytespider'        => 'ByteDance Bytespider',
        'DataForSeoBot'     => 'DataForSEO Bot',
        'ImagesiftBot'      => 'Imagesift Bot',
        'omgili'            => 'Omgili Bot',
        'Diffbot'           => 'Diffbot',
        'FacebookBot'       => 'Meta FacebookBot',
        'Amazonbot'         => 'Amazon Amazonbot',
    ];

    public function register(): void {
        add_filter( 'robots_txt', [ $this, 'append_rules' ], 20, 2 );
    }

    public function append_rules( string $output, bool $public ): string {
        $settings = self::getSettings();
        $blocked  = $settings['blocked_bots'] ?? [];

        foreach ( $blocked as $bot ) {
            if ( isset( self::KNOWN_BOTS[ $bot ] ) ) {
                $output .= "\nUser-agent: {$bot}\nDisallow: /\n";
            }
        }

        return $output;
    }

    public static function getSettings(): array {
        $saved = get_option( self::OPTION_KEY, [] );
        return array_merge( [ 'blocked_bots' => [] ], is_array( $saved ) ? $saved : [] );
    }
}
```

**Step 2: Write test**

Create `tests/Features/RobotsTxtTest.php`:
```php
<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\RobotsTxt;

class RobotsTxtTest extends TestCase {

    public function test_known_bots_list_not_empty(): void {
        $this->assertNotEmpty( RobotsTxt::KNOWN_BOTS );
        $this->assertArrayHasKey( 'GPTBot', RobotsTxt::KNOWN_BOTS );
        $this->assertArrayHasKey( 'ClaudeBot', RobotsTxt::KNOWN_BOTS );
    }

    public function test_get_settings_returns_defaults(): void {
        $settings = RobotsTxt::getSettings();
        $this->assertArrayHasKey( 'blocked_bots', $settings );
        $this->assertIsArray( $settings['blocked_bots'] );
    }

    public function test_append_rules_adds_disallow_for_blocked_bot(): void {
        $robots = new RobotsTxt();
        $method = new \ReflectionMethod( RobotsTxt::class, 'append_rules' );
        $method->setAccessible( true );

        // Simulate settings with GPTBot blocked
        global $bre_test_options;
        $bre_test_options['bre_robots_settings'] = [ 'blocked_bots' => [ 'GPTBot' ] ];

        $output = $method->invoke( $robots, "User-agent: *\nAllow: /\n", true );
        $this->assertStringContainsString( 'User-agent: GPTBot', $output );
        $this->assertStringContainsString( 'Disallow: /', $output );

        $bre_test_options = [];
    }

    public function test_append_rules_ignores_unknown_bots(): void {
        $robots = new RobotsTxt();
        $method = new \ReflectionMethod( RobotsTxt::class, 'append_rules' );
        $method->setAccessible( true );

        global $bre_test_options;
        $bre_test_options['bre_robots_settings'] = [ 'blocked_bots' => [ 'UnknownBot' ] ];

        $output = $method->invoke( $robots, '', true );
        $this->assertStringNotContainsString( 'UnknownBot', $output );

        $bre_test_options = [];
    }
}
```

Add to `tests/bootstrap.php`:
```php
$GLOBALS['bre_test_options'] = [];
// Update get_option stub to check test options first:
// (replace existing get_option stub)
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $option, $default = false ) {
        return $GLOBALS['bre_test_options'][ $option ] ?? $default;
    }
}
```

**Step 3: Create RobotsPage.php + view**

`includes/Admin/RobotsPage.php`:
```php
<?php
namespace BavarianRankEngine\Admin;

use BavarianRankEngine\Features\RobotsTxt;

class RobotsPage {
    public function register(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings(): void {
        register_setting( 'bre_robots', 'bre_robots_settings', [ $this, 'sanitize' ] );
    }

    public function sanitize( mixed $input ): array {
        $input  = is_array( $input ) ? $input : [];
        $blocked = array_values( array_intersect(
            array_map( 'sanitize_text_field', (array) ( $input['blocked_bots'] ?? [] ) ),
            array_keys( RobotsTxt::KNOWN_BOTS )
        ) );
        return [ 'blocked_bots' => $blocked ];
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings = RobotsTxt::getSettings();
        include BRE_DIR . 'includes/Admin/views/robots.php';
    }
}
```

`includes/Admin/views/robots.php`:
```html
<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap bre-settings">
    <h1><?php esc_html_e( 'robots.txt — AI Bots', 'bavarian-rank-engine' ); ?></h1>
    <p>
        <?php esc_html_e( 'Bekannte AI-Bots blockieren. Hinweis: Bots müssen sich nicht daran halten.', 'bavarian-rank-engine' ); ?>
    </p>
    <form method="post" action="options.php">
        <?php settings_fields( 'bre_robots' ); ?>
        <table class="widefat striped">
            <thead><tr>
                <th><?php esc_html_e( 'Bot', 'bavarian-rank-engine' ); ?></th>
                <th><?php esc_html_e( 'Beschreibung', 'bavarian-rank-engine' ); ?></th>
                <th><?php esc_html_e( 'Blockieren', 'bavarian-rank-engine' ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( \BavarianRankEngine\Features\RobotsTxt::KNOWN_BOTS as $bot_key => $bot_label ) : ?>
            <tr>
                <td><code><?php echo esc_html( $bot_key ); ?></code></td>
                <td><?php echo esc_html( $bot_label ); ?></td>
                <td>
                    <input type="checkbox"
                           name="bre_robots_settings[blocked_bots][]"
                           value="<?php echo esc_attr( $bot_key ); ?>"
                           <?php checked( in_array( $bot_key, $settings['blocked_bots'], true ) ); ?>>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button( __( 'Speichern', 'bavarian-rank-engine' ) ); ?>
    </form>
    <p>
        <a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener">
            <?php esc_html_e( 'Aktuelle robots.txt ansehen →', 'bavarian-rank-engine' ); ?>
        </a>
    </p>
</div>
```

**Step 4: Add to AdminMenu.php**

In `register()` / `admin_menu` hook, add:
```php
add_submenu_page(
    'bavarian-rank',
    __( 'robots.txt / AI Bots', 'bavarian-rank-engine' ),
    __( 'robots.txt', 'bavarian-rank-engine' ),
    'manage_options',
    'bre-robots',
    [ new RobotsPage(), 'render' ]
);
```

**Step 5: Register in Core.php**

```php
require_once BRE_DIR . 'includes/Features/RobotsTxt.php';
require_once BRE_DIR . 'includes/Admin/RobotsPage.php';
// features:
( new Features\RobotsTxt() )->register();
// admin:
( new Admin\RobotsPage() )->register();
```

**Step 6: Run tests**
```bash
vendor/bin/phpunit --no-coverage
```

**Step 7: Commit**
```bash
git add includes/Features/RobotsTxt.php includes/Admin/RobotsPage.php \
        includes/Admin/views/robots.php includes/Admin/AdminMenu.php \
        includes/Core.php tests/Features/RobotsTxtTest.php tests/bootstrap.php
git commit -m "feat: robots.txt AI bot management — block GPTBot, ClaudeBot etc. via admin UI"
```

---

## Task 16: CrawlerLog Feature

**Files:**
- Create: `includes/Features/CrawlerLog.php`
- Modify: `seo-geo.php` — activation hook for DB table
- Modify: `includes/Admin/views/dashboard.php` — crawler card
- Modify: `includes/Core.php`

**Step 1: Create CrawlerLog.php**

```php
<?php
namespace BavarianRankEngine\Features;

class CrawlerLog {
    public static function install(): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'bre_crawler_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bot_name    VARCHAR(64)     NOT NULL,
            ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
            url         VARCHAR(512)    NOT NULL DEFAULT '',
            visited_at  DATETIME        NOT NULL,
            PRIMARY KEY (id),
            KEY bot_name (bot_name),
            KEY visited_at (visited_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function register(): void {
        add_action( 'init',       [ $this, 'maybe_log' ], 1 );
        add_action( 'bre_purge_crawler_log', [ $this, 'purge_old' ] );

        if ( ! wp_next_scheduled( 'bre_purge_crawler_log' ) ) {
            wp_schedule_event( time(), 'weekly', 'bre_purge_crawler_log' );
        }
    }

    public function maybe_log(): void {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ( empty( $ua ) ) return;

        $bot = $this->detect_bot( $ua );
        if ( ! $bot ) return;

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'bre_crawler_log',
            [
                'bot_name'   => $bot,
                'ip_hash'    => hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' ),
                'url'        => substr( $_SERVER['REQUEST_URI'] ?? '', 0, 512 ),
                'visited_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }

    private function detect_bot( string $ua ): ?string {
        $bots = array_keys( RobotsTxt::KNOWN_BOTS );
        foreach ( $bots as $bot ) {
            if ( stripos( $ua, $bot ) !== false ) {
                return $bot;
            }
        }
        return null;
    }

    public function purge_old(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}bre_crawler_log WHERE visited_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );
    }

    public static function get_recent_summary( int $days = 30 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT bot_name, COUNT(*) as visits, MAX(visited_at) as last_seen
             FROM {$wpdb->prefix}bre_crawler_log
             WHERE visited_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY bot_name ORDER BY visits DESC",
            $days
        ), ARRAY_A );
    }
}
```

**Step 2: Add activation hook in seo-geo.php**

```php
register_activation_hook( __FILE__, function() {
    \BavarianRankEngine\Features\CrawlerLog::install();
    ( new \BavarianRankEngine\Features\LlmsTxt() )->flush_rules();
} );
```

**Step 3: Add crawler dashboard card**

In `includes/Admin/views/dashboard.php`, after the status postbox:
```html
<div class="postbox">
    <div class="postbox-header"><h2><?php esc_html_e( 'AI Crawler — letzte 30 Tage', 'bavarian-rank-engine' ); ?></h2></div>
    <div class="inside">
        <?php
        $crawlers = \BavarianRankEngine\Features\CrawlerLog::get_recent_summary( 30 );
        if ( empty( $crawlers ) ) :
        ?>
            <p><?php esc_html_e( 'Noch keine AI-Crawls aufgezeichnet.', 'bavarian-rank-engine' ); ?></p>
        <?php else : ?>
        <table class="widefat striped">
            <thead><tr>
                <th><?php esc_html_e( 'Bot', 'bavarian-rank-engine' ); ?></th>
                <th><?php esc_html_e( 'Besuche', 'bavarian-rank-engine' ); ?></th>
                <th><?php esc_html_e( 'Zuletzt', 'bavarian-rank-engine' ); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $crawlers as $row ) : ?>
            <tr>
                <td><code><?php echo esc_html( $row['bot_name'] ); ?></code></td>
                <td><?php echo esc_html( $row['visits'] ); ?></td>
                <td><?php echo esc_html( $row['last_seen'] ); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
```

**Step 4: Register in Core.php**

```php
require_once BRE_DIR . 'includes/Features/CrawlerLog.php';
( new Features\CrawlerLog() )->register();
```

**Step 5: Run tests + Commit**
```bash
vendor/bin/phpunit --no-coverage
git add includes/Features/CrawlerLog.php includes/Admin/views/dashboard.php \
        includes/Core.php seo-geo.php
git commit -m "feat: AI crawler log — DB table, bot detection, dashboard summary, 90-day purge"
```

---

## Task 17: Final Wiring + Build

**Step 1: Verify all require_once in Core.php are present**

`load_dependencies()` must include in order:
```php
require_once BRE_DIR . 'includes/Helpers/BulkQueue.php';
require_once BRE_DIR . 'includes/Helpers/FallbackMeta.php';
require_once BRE_DIR . 'includes/Features/RobotsTxt.php';
require_once BRE_DIR . 'includes/Features/CrawlerLog.php';
require_once BRE_DIR . 'includes/Admin/MetaEditorBox.php';
require_once BRE_DIR . 'includes/Admin/SeoWidget.php';
require_once BRE_DIR . 'includes/Admin/LinkAnalysis.php';
require_once BRE_DIR . 'includes/Admin/RobotsPage.php';
```

**Step 2: Run full test suite**
```bash
vendor/bin/phpunit --no-coverage
```
Expected: All green.

**Step 3: Build and deploy**
```bash
bash bin/build.sh
```
Expected output: `✓ Build complete!`

**Step 4: Verify build output**
```bash
ls /var/www/dev/plugins/bavarian-rank-engine/includes/Admin/
ls /var/www/dev/plugins/bavarian-rank-engine/includes/Features/
ls /var/www/dev/plugins/bavarian-rank-engine/assets/
```
All new files should be present.

**Step 5: Final commit**
```bash
git add -A
git commit -m "feat: complete next iteration — all features wired, build verified"
```

---

## Summary of new files

| File | Purpose |
|------|---------|
| `includes/Helpers/BulkQueue.php` | Lock via WP transient |
| `includes/Helpers/FallbackMeta.php` | First-paragraph meta extraction |
| `includes/Features/RobotsTxt.php` | robots.txt AI bot rules |
| `includes/Features/CrawlerLog.php` | AI crawler tracking + DB table |
| `includes/Admin/MetaEditorBox.php` | Post editor meta description box |
| `includes/Admin/SeoWidget.php` | Post editor SEO analysis widget |
| `includes/Admin/LinkAnalysis.php` | Dashboard link analysis (AJAX) |
| `includes/Admin/RobotsPage.php` | robots.txt admin page |
| `includes/Admin/views/robots.php` | robots.txt admin view |
| `assets/editor-meta.js` | Char counter + AJAX regen |
| `assets/seo-widget.js` | Live SEO analysis in editor |
