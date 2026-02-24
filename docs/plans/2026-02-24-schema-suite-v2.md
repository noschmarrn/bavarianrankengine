# Schema-Suite v2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add 8 new Schema.org types to BRE (FAQPage, BlogPosting, ImageObject, VideoObject, HowTo, Review, Recipe, Event) — auto-types need no UI, manual types use a new post-editor Metabox.

**Architecture:** All builders stay in `SchemaEnhancer.php` with public static pure-function helpers for testability. A new `SchemaMetaBox` class handles the post-editor UI. Settings whitelist extended in `MetaPage::sanitize()`. No new Feature class needed.

**Tech Stack:** PHP 8.1+, WordPress 6.0+, Schema.org JSON-LD, PHPUnit (`php composer.phar exec phpunit` from `bre-dev/`)

---

## Reference: Key Constants & Paths

- **Source truth:** `bre-dev/` — never touch `bavarian-rank-engine/`
- **New post-meta keys:**
  - `_bre_schema_type` → `'howto'|'review'|'recipe'|'event'|''`
  - `_bre_schema_data` → JSON blob with per-type data
- **New schema_enabled slugs:** `faq_schema`, `blog_posting`, `image_object`, `video_object`, `howto`, `review`, `recipe`, `event`
- **Settings whitelist:** `MetaPage::sanitize()` line 58 — `$schema_types` array must include new slugs
- **Schema labels:** `MetaPage::render()` line 90 — `$schema_labels` array must include new labels
- **Core registration:** `Core.php` — `load_dependencies()` + `register_hooks()`
- **Bootstrap stubs:** `tests/bootstrap.php` — add missing WP function stubs as needed

---

## Task 1: Test bootstrap — add missing WP stubs

**Files:**
- Modify: `tests/bootstrap.php`

**Step 1: Add stubs** after the existing stubs block

```php
if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() {
        return $GLOBALS['bre_current_post_id'] ?? 0;
    }
}
if ( ! function_exists( 'is_singular' ) ) {
    function is_singular( $post_types = '' ) {
        return $GLOBALS['bre_is_singular'] ?? false;
    }
}
if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type( $post = null ) {
        return $GLOBALS['bre_post_type'] ?? 'post';
    }
}
if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo( $show = '', $filter = 'raw' ) {
        $map = [ 'name' => 'Test Blog', 'url' => 'https://example.com' ];
        return $map[ $show ] ?? '';
    }
}
if ( ! function_exists( 'get_the_author' ) ) {
    function get_the_author() {
        return $GLOBALS['bre_author_name'] ?? 'Test Author';
    }
}
if ( ! function_exists( 'get_the_author_meta' ) ) {
    function get_the_author_meta( $field, $user_id = 0 ) {
        return $GLOBALS['bre_author_meta'][ $field ] ?? '';
    }
}
if ( ! function_exists( 'get_author_posts_url' ) ) {
    function get_author_posts_url( $author_id, $author_nicename = '' ) {
        return 'https://example.com/author/' . $author_id;
    }
}
if ( ! function_exists( 'has_post_thumbnail' ) ) {
    function has_post_thumbnail( $post = null ) {
        return $GLOBALS['bre_has_thumbnail'] ?? false;
    }
}
if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
    function get_post_thumbnail_id( $post = null ) {
        return $GLOBALS['bre_thumbnail_id'] ?? 0;
    }
}
if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
    function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail', $icon = false ) {
        return $GLOBALS['bre_attachment_src'] ?? false;
    }
}
if ( ! function_exists( 'get_the_modified_date' ) ) {
    function get_the_modified_date( $format = '', $post = null ) {
        return '2024-06-01';
    }
}
if ( ! function_exists( 'get_the_excerpt' ) ) {
    function get_the_excerpt( $post = null ) {
        return '';
    }
}
```

**Step 2: Run test suite — verify still green**

```bash
cd bre-dev && php composer.phar exec phpunit
```
Expected: all existing tests PASS.

**Step 3: Commit**

```bash
git add tests/bootstrap.php
git commit -m "test: add WP stubs needed for schema-suite v2 tests"
```

---

## Task 2: FAQPage schema — auto-type from GEO data

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Create: `bre-dev/tests/Features/SchemaEnhancerTest.php`

**Step 1: Write failing test**

```php
<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\SchemaEnhancer;

class SchemaEnhancerTest extends TestCase {
    public function test_faq_pairs_to_schema_returns_null_for_empty(): void {
        $this->assertNull( SchemaEnhancer::faqPairsToSchema( [] ) );
    }

    public function test_faq_pairs_to_schema_builds_correct_structure(): void {
        $faq = [
            [ 'q' => 'Was ist das?', 'a' => 'Das ist ein Test.' ],
            [ 'q' => 'Warum?',       'a' => 'Weil ja.' ],
        ];
        $schema = SchemaEnhancer::faqPairsToSchema( $faq );

        $this->assertEquals( 'https://schema.org',      $schema['@context'] );
        $this->assertEquals( 'FAQPage',                  $schema['@type'] );
        $this->assertCount( 2,                           $schema['mainEntity'] );
        $this->assertEquals( 'Question',                 $schema['mainEntity'][0]['@type'] );
        $this->assertEquals( 'Was ist das?',             $schema['mainEntity'][0]['name'] );
        $this->assertEquals( 'Answer',                   $schema['mainEntity'][0]['acceptedAnswer']['@type'] );
        $this->assertEquals( 'Das ist ein Test.',        $schema['mainEntity'][0]['acceptedAnswer']['text'] );
    }

    public function test_faq_pairs_skips_incomplete_items(): void {
        $faq = [
            [ 'q' => 'Vollständig', 'a' => 'Ja.' ],
            [ 'q' => 'Nur Frage'                  ], // no 'a' key
            [ 'a' => 'Nur Antwort'                ], // no 'q' key
        ];
        $schema = SchemaEnhancer::faqPairsToSchema( $faq );
        $this->assertCount( 1, $schema['mainEntity'] );
    }
}
```

**Step 2: Run test — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php
```
Expected: FAIL — `SchemaEnhancer::faqPairsToSchema` does not exist.

**Step 3: Add public static method to SchemaEnhancer**

Add after the closing brace of `buildBreadcrumbSchema()` and before the final `}`:

```php
/**
 * Pure helper — converts GEO FAQ pairs to FAQPage schema.
 * Returns null when the list is empty (skip empty schemas).
 *
 * @param array $faq  Array of ['q' => string, 'a' => string] pairs.
 */
public static function faqPairsToSchema( array $faq ): ?array {
    $entities = [];
    foreach ( $faq as $item ) {
        if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
            continue;
        }
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $item['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $item['a'],
            ],
        ];
    }
    if ( empty( $entities ) ) {
        return null;
    }
    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

/**
 * WP-dependent wrapper: reads from GeoBlock post meta.
 */
private function buildFaqSchema(): ?array {
    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return null;
    }
    $meta = \BavarianRankEngine\Features\GeoBlock::getMeta( $post_id );
    return self::faqPairsToSchema( $meta['faq'] ?? [] );
}
```

**Step 4: Run test — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php
```
Expected: PASS.

**Step 5: Commit**

```bash
git add includes/Features/SchemaEnhancer.php tests/Features/SchemaEnhancerTest.php
git commit -m "feat: add FAQPage schema builder (reads from GEO block)"
```

---

## Task 3: BlogPosting + ImageObject — auto-types

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Modify: `bre-dev/tests/Features/SchemaEnhancerTest.php`

**Step 1: Write failing tests** (append to SchemaEnhancerTest):

```php
public function test_minutes_to_iso_duration_hours_and_minutes(): void {
    $this->assertEquals( 'PT90M', SchemaEnhancer::minutesToIsoDuration( 90 ) );
    $this->assertEquals( 'PT5M',  SchemaEnhancer::minutesToIsoDuration( 5 ) );
    $this->assertEquals( 'PT0M',  SchemaEnhancer::minutesToIsoDuration( 0 ) );
}
```

(BlogPosting and ImageObject builders depend on too many WP globals to unit-test directly — they are covered by integration: the pure `minutesToIsoDuration` helper is the only new pure function here. BlogPosting/ImageObject builders will be smoke-tested in Task 12.)

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php
```

**Step 3: Add helpers + builders to SchemaEnhancer**

```php
/**
 * Converts integer minutes to ISO 8601 duration string (e.g. 90 → "PT90M").
 */
public static function minutesToIsoDuration( int $minutes ): string {
    return 'PT' . $minutes . 'M';
}

/**
 * BlogPosting (or Article for non-post types) with embedded author + image.
 */
private function buildBlogPosting(): array {
    $type   = get_post_type() === 'post' ? 'BlogPosting' : 'Article';
    $schema = [
        '@context'      => 'https://schema.org',
        '@type'         => $type,
        'headline'      => get_the_title(),
        'url'           => get_permalink(),
        'datePublished' => get_the_date( 'c' ),
        'dateModified'  => get_the_modified_date( 'c' ),
        'description'   => get_post_meta( get_the_ID(), '_bre_meta_description', true )
                           ?: get_the_excerpt(),
        'publisher'     => [
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        ],
        'author'        => [
            '@type' => 'Person',
            'name'  => get_the_author(),
            'url'   => get_author_posts_url( (int) get_the_author_meta( 'ID' ) ),
        ],
    ];
    $img = $this->buildImageObject();
    if ( $img ) {
        $schema['image'] = $img;
    }
    return $schema;
}

/**
 * ImageObject from featured image. Returns null when no thumbnail is set.
 */
private function buildImageObject(): ?array {
    if ( ! has_post_thumbnail() ) {
        return null;
    }
    $src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
    if ( ! $src ) {
        return null;
    }
    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'ImageObject',
        'contentUrl' => $src[0],
    ];
    if ( ! empty( $src[1] ) ) {
        $schema['width'] = (int) $src[1];
    }
    if ( ! empty( $src[2] ) ) {
        $schema['height'] = (int) $src[2];
    }
    return $schema;
}
```

**Step 4: Run — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php
```

**Step 5: Commit**

```bash
git add includes/Features/SchemaEnhancer.php tests/Features/SchemaEnhancerTest.php
git commit -m "feat: add BlogPosting and ImageObject schema builders"
```

---

## Task 4: VideoObject — auto-type via content regex

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Modify: `bre-dev/tests/Features/SchemaEnhancerTest.php`

**Step 1: Write failing tests** (append to SchemaEnhancerTest):

```php
public function test_extract_video_detects_youtube_embed_url(): void {
    $content = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
    $result  = SchemaEnhancer::extractVideoFromContent( $content );
    $this->assertEquals( 'dQw4w9WgXcQ', $result['videoId'] );
    $this->assertEquals( 'youtube',     $result['platform'] );
}

public function test_extract_video_detects_youtu_be_url(): void {
    $content = '<a href="https://youtu.be/dQw4w9WgXcQ">Video</a>';
    $result  = SchemaEnhancer::extractVideoFromContent( $content );
    $this->assertEquals( 'dQw4w9WgXcQ', $result['videoId'] );
}

public function test_extract_video_detects_vimeo(): void {
    $content = '<iframe src="https://player.vimeo.com/video/123456789"></iframe>';
    $result  = SchemaEnhancer::extractVideoFromContent( $content );
    $this->assertEquals( '123456789', $result['videoId'] );
    $this->assertEquals( 'vimeo',     $result['platform'] );
}

public function test_extract_video_returns_null_for_no_video(): void {
    $this->assertNull( SchemaEnhancer::extractVideoFromContent( '<p>No video here.</p>' ) );
}
```

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php
```

**Step 3: Add helpers + builder**

```php
/**
 * Extracts first YouTube or Vimeo video from HTML content.
 * Returns ['platform' => 'youtube'|'vimeo', 'videoId' => string] or null.
 */
public static function extractVideoFromContent( string $content ): ?array {
    // YouTube embed or youtu.be
    if ( preg_match(
        '#(?:youtube\.com/embed/|youtu\.be/)([a-zA-Z0-9_\-]{11})#',
        $content,
        $m
    ) ) {
        return [ 'platform' => 'youtube', 'videoId' => $m[1] ];
    }
    // Vimeo
    if ( preg_match( '#player\.vimeo\.com/video/(\d+)#', $content, $m ) ) {
        return [ 'platform' => 'vimeo', 'videoId' => $m[1] ];
    }
    return null;
}

/**
 * WP-dependent wrapper: builds VideoObject from first video found in post content.
 */
private function buildVideoObject(): ?array {
    global $post;
    $content = $post->post_content ?? '';
    $video   = self::extractVideoFromContent( $content );
    if ( ! $video ) {
        return null;
    }
    if ( $video['platform'] === 'youtube' ) {
        $embed_url     = 'https://www.youtube.com/embed/' . $video['videoId'];
        $thumbnail_url = 'https://i.ytimg.com/vi/' . $video['videoId'] . '/hqdefault.jpg';
    } else {
        $embed_url     = 'https://player.vimeo.com/video/' . $video['videoId'];
        $thumbnail_url = ''; // Vimeo requires API for thumbnails — omit gracefully
    }
    $schema = [
        '@context'     => 'https://schema.org',
        '@type'        => 'VideoObject',
        'name'         => get_the_title(),
        'embedUrl'     => $embed_url,
        'uploadDate'   => get_the_date( 'c' ),
    ];
    if ( $thumbnail_url ) {
        $schema['thumbnailUrl'] = $thumbnail_url;
    }
    return $schema;
}
```

**Step 4: Run — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php
```

**Step 5: Commit**

```bash
git add includes/Features/SchemaEnhancer.php tests/Features/SchemaEnhancerTest.php
git commit -m "feat: add VideoObject schema builder with YouTube/Vimeo regex"
```

---

## Task 5: Settings — extend whitelist + view labels

**Files:**
- Modify: `bre-dev/includes/Admin/MetaPage.php` (line 58 and line 90)

**Step 1: Extend the schema types whitelist** in `MetaPage::sanitize()` at line 58:

```php
// Old:
$schema_types = array( 'organization', 'author', 'speakable', 'article_about', 'breadcrumb', 'ai_meta_tags' );

// New:
$schema_types = array(
    'organization', 'author', 'speakable', 'article_about', 'breadcrumb', 'ai_meta_tags',
    'faq_schema', 'blog_posting', 'image_object', 'video_object',
    'howto', 'review', 'recipe', 'event',
);
```

**Step 2: Add labels** in `MetaPage::render()` — extend `$schema_labels`:

```php
// Append after 'ai_meta_tags' entry:
'faq_schema'   => __( 'FAQPage (aus GEO Quick Overview — automatisch)', 'bavarian-rank-engine' ),
'blog_posting' => __( 'BlogPosting / Article (mit eingebettetem Author + Image)', 'bavarian-rank-engine' ),
'image_object' => __( 'ImageObject (Featured Image)', 'bavarian-rank-engine' ),
'video_object' => __( 'VideoObject (YouTube/Vimeo automatisch erkennen)', 'bavarian-rank-engine' ),
'howto'        => __( 'HowTo (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
'review'       => __( 'Review mit Bewertung (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
'recipe'       => __( 'Recipe (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
'event'        => __( 'Event (Metabox im Post-Editor)', 'bavarian-rank-engine' ),
```

**Step 3: Run full test suite**

```bash
cd bre-dev && php composer.phar exec phpunit
```
Expected: all PASS.

**Step 4: Commit**

```bash
git add includes/Admin/MetaPage.php
git commit -m "feat: extend schema_enabled whitelist and labels for v2 types"
```

---

## Task 6: SchemaMetaBox skeleton — register, nonce, save

**Files:**
- Create: `bre-dev/includes/Admin/SchemaMetaBox.php`
- Create: `bre-dev/includes/Admin/views/schema-meta-box.php`
- Create: `bre-dev/tests/Admin/SchemaMetaBoxTest.php`

**Step 1: Write failing test**

```php
<?php
namespace BavarianRankEngine\Tests\Admin;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Admin\SchemaMetaBox;

class SchemaMetaBoxTest extends TestCase {
    public function test_sanitize_data_returns_valid_type_or_empty(): void {
        $clean = SchemaMetaBox::sanitizeData( [ 'schema_type' => 'howto' ] );
        $this->assertEquals( 'howto', $clean['schema_type'] );

        $clean = SchemaMetaBox::sanitizeData( [ 'schema_type' => 'invalid' ] );
        $this->assertEquals( '', $clean['schema_type'] );
    }

    public function test_sanitize_data_accepts_all_valid_types(): void {
        foreach ( [ 'howto', 'review', 'recipe', 'event', '' ] as $type ) {
            $clean = SchemaMetaBox::sanitizeData( [ 'schema_type' => $type ] );
            $this->assertEquals( $type, $clean['schema_type'] );
        }
    }
}
```

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Admin/SchemaMetaBoxTest.php
```

**Step 3: Create SchemaMetaBox.php**

```php
<?php
namespace BavarianRankEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SchemaMetaBox {
    public const META_TYPE    = '_bre_schema_type';
    public const META_DATA    = '_bre_schema_data';
    private const VALID_TYPES = [ 'howto', 'review', 'recipe', 'event', '' ];

    public function register(): void {
        add_action( 'add_meta_boxes', [ $this, 'addMetaBox' ] );
        add_action( 'save_post',      [ $this, 'savePost' ], 10, 2 );
    }

    public function addMetaBox(): void {
        $settings = SettingsPage::getSettings();
        $enabled  = $settings['schema_enabled'] ?? [];
        $needs_box = array_intersect( [ 'howto', 'review', 'recipe', 'event' ], $enabled );
        if ( empty( $needs_box ) ) {
            return;
        }
        add_meta_box(
            'bre-schema-meta-box',
            __( 'BRE Schema', 'bavarian-rank-engine' ),
            [ $this, 'renderMetaBox' ],
            [ 'post', 'page' ],
            'side',
            'default'
        );
    }

    public function renderMetaBox( \WP_Post $post ): void {
        $type     = get_post_meta( $post->ID, self::META_TYPE, true ) ?: '';
        $raw_data = get_post_meta( $post->ID, self::META_DATA, true ) ?: '{}';
        $data     = json_decode( $raw_data, true ) ?: [];
        $settings = SettingsPage::getSettings();
        $enabled  = $settings['schema_enabled'] ?? [];
        wp_nonce_field( 'bre_schema_meta_box', '_bre_schema_nonce' );
        include BRE_DIR . 'includes/Admin/views/schema-meta-box.php';
    }

    public function savePost( int $post_id, \WP_Post $post ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['_bre_schema_nonce'] )
            || ! wp_verify_nonce( sanitize_key( $_POST['_bre_schema_nonce'] ), 'bre_schema_meta_box' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        $input = $_POST['bre_schema'] ?? [];
        $clean = self::sanitizeData( $input );
        update_post_meta( $post_id, self::META_TYPE, $clean['schema_type'] );
        update_post_meta(
            $post_id,
            self::META_DATA,
            wp_json_encode( $clean['data'], JSON_UNESCAPED_UNICODE )
        );
    }

    /**
     * Pure sanitizer — public static for testability.
     *
     * @param array $input Raw $_POST['bre_schema'] data.
     * @return array ['schema_type' => string, 'data' => array]
     */
    public static function sanitizeData( array $input ): array {
        $type = sanitize_key( $input['schema_type'] ?? '' );
        if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
            $type = '';
        }
        // Per-type sanitization handled in Tasks 7–10.
        // Here we just pass raw data through for now (will be replaced).
        $data = [];
        return [
            'schema_type' => $type,
            'data'        => $data,
        ];
    }
}
```

**Step 4: Create skeleton view** `includes/Admin/views/schema-meta-box.php`:

```php
<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="bre-schema-metabox">
    <p>
        <label for="bre-schema-type"><strong><?php esc_html_e( 'Schema-Typ', 'bavarian-rank-engine' ); ?></strong></label><br>
        <select name="bre_schema[schema_type]" id="bre-schema-type">
            <option value="" <?php selected( $type, '' ); ?>><?php esc_html_e( '— Kein Schema —', 'bavarian-rank-engine' ); ?></option>
            <?php if ( in_array( 'howto', $enabled, true ) ) : ?>
            <option value="howto"   <?php selected( $type, 'howto' ); ?>><?php esc_html_e( 'HowTo Anleitung', 'bavarian-rank-engine' ); ?></option>
            <?php endif; ?>
            <?php if ( in_array( 'review', $enabled, true ) ) : ?>
            <option value="review"  <?php selected( $type, 'review' ); ?>><?php esc_html_e( 'Review / Bewertung', 'bavarian-rank-engine' ); ?></option>
            <?php endif; ?>
            <?php if ( in_array( 'recipe', $enabled, true ) ) : ?>
            <option value="recipe"  <?php selected( $type, 'recipe' ); ?>><?php esc_html_e( 'Rezept', 'bavarian-rank-engine' ); ?></option>
            <?php endif; ?>
            <?php if ( in_array( 'event', $enabled, true ) ) : ?>
            <option value="event"   <?php selected( $type, 'event' ); ?>><?php esc_html_e( 'Event', 'bavarian-rank-engine' ); ?></option>
            <?php endif; ?>
        </select>
    </p>
    <!-- Type-specific fields will be injected by Tasks 7–10 -->
</div>
<script>
(function(){
    var sel = document.getElementById('bre-schema-type');
    function toggle() {
        document.querySelectorAll('.bre-schema-fields').forEach(function(el){
            el.style.display = el.dataset.breType === sel.value ? '' : 'none';
        });
    }
    if (sel) { sel.addEventListener('change', toggle); toggle(); }
})();
</script>
```

**Step 5: Run test — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Admin/SchemaMetaBoxTest.php
```

**Step 6: Commit**

```bash
git add includes/Admin/SchemaMetaBox.php includes/Admin/views/schema-meta-box.php tests/Admin/SchemaMetaBoxTest.php
git commit -m "feat: SchemaMetaBox skeleton — register, nonce, save, sanitize"
```

---

## Task 7: HowTo schema

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Modify: `bre-dev/includes/Admin/SchemaMetaBox.php`
- Modify: `bre-dev/includes/Admin/views/schema-meta-box.php`
- Modify: `bre-dev/tests/Features/SchemaEnhancerTest.php`
- Modify: `bre-dev/tests/Admin/SchemaMetaBoxTest.php`

**Step 1: Write failing tests**

In `SchemaEnhancerTest.php`, append:
```php
public function test_build_howto_from_data_returns_correct_structure(): void {
    $schema = SchemaEnhancer::buildHowToFromData( 'Pasta kochen', [ 'Wasser kochen', 'Pasta hinzufügen', 'Abtropfen' ] );
    $this->assertEquals( 'HowTo',        $schema['@type'] );
    $this->assertEquals( 'Pasta kochen', $schema['name'] );
    $this->assertCount( 3,               $schema['step'] );
    $this->assertEquals( 'HowToStep',    $schema['step'][0]['@type'] );
    $this->assertEquals( 'Wasser kochen',$schema['step'][0]['name'] );
}

public function test_build_howto_filters_empty_steps(): void {
    $schema = SchemaEnhancer::buildHowToFromData( 'Test', [ 'Schritt 1', '', '  ', 'Schritt 2' ] );
    $this->assertCount( 2, $schema['step'] );
}
```

In `SchemaMetaBoxTest.php`, append:
```php
public function test_sanitize_data_howto_extracts_steps(): void {
    $input = [
        'schema_type'   => 'howto',
        'howto_name'    => 'Pasta kochen',
        'howto_steps'   => "Wasser kochen\nPasta hinzufügen\nAbtropfen",
    ];
    $clean = SchemaMetaBox::sanitizeData( $input );
    $this->assertEquals( 'howto',                     $clean['schema_type'] );
    $this->assertEquals( 'Pasta kochen',               $clean['data']['howto']['name'] );
    $this->assertCount( 3,                             $clean['data']['howto']['steps'] );
    $this->assertEquals( 'Wasser kochen',              $clean['data']['howto']['steps'][0] );
}
```

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 3: Add to SchemaEnhancer.php**

```php
/**
 * Pure builder for HowTo schema.
 *
 * @param string   $name  The how-to title.
 * @param string[] $steps Each step as a string.
 */
public static function buildHowToFromData( string $name, array $steps ): array {
    $how_to_steps = [];
    foreach ( array_filter( array_map( 'trim', $steps ) ) as $step ) {
        $how_to_steps[] = [
            '@type' => 'HowToStep',
            'name'  => $step,
        ];
    }
    return [
        '@context' => 'https://schema.org',
        '@type'    => 'HowTo',
        'name'     => $name,
        'step'     => $how_to_steps,
    ];
}

/** WP-dependent: builds HowTo from post meta. */
private function buildHowToSchema(): ?array {
    $post_id  = get_the_ID();
    $raw_data = get_post_meta( $post_id, Admin\SchemaMetaBox::META_DATA, true ) ?: '{}';
    $data     = json_decode( $raw_data, true )['howto'] ?? [];
    $name     = $data['name'] ?? '';
    $steps    = $data['steps'] ?? [];
    if ( empty( $name ) || empty( $steps ) ) {
        return null;
    }
    return self::buildHowToFromData( $name, $steps );
}
```

**Step 4: Update SchemaMetaBox::sanitizeData()** — replace the `$data = [];` and `return` block:

```php
$data = [];

if ( $type === 'howto' ) {
    $raw_steps      = sanitize_textarea_field( $input['howto_steps'] ?? '' );
    $steps          = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_steps ) ) ) );
    $data['howto']  = [
        'name'  => sanitize_text_field( $input['howto_name'] ?? '' ),
        'steps' => $steps,
    ];
}

return [
    'schema_type' => $type,
    'data'        => $data,
];
```

**Step 5: Add HowTo fields to schema-meta-box.php view** — after the closing `</p>` of the dropdown:

```php
<div class="bre-schema-fields" data-bre-type="howto" style="display:none;">
    <p>
        <label><strong><?php esc_html_e( 'Name der Anleitung', 'bavarian-rank-engine' ); ?></strong><br>
        <input type="text" name="bre_schema[howto_name]"
               value="<?php echo esc_attr( $data['howto']['name'] ?? '' ); ?>"
               class="widefat"></label>
    </p>
    <p>
        <label><strong><?php esc_html_e( 'Schritte (eine Zeile = ein Schritt)', 'bavarian-rank-engine' ); ?></strong><br>
        <textarea name="bre_schema[howto_steps]" rows="5" class="widefat"><?php
            echo esc_textarea( implode( "\n", $data['howto']['steps'] ?? [] ) );
        ?></textarea></label>
    </p>
</div>
```

**Step 6: Run — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 7: Commit**

```bash
git add includes/Features/SchemaEnhancer.php includes/Admin/SchemaMetaBox.php includes/Admin/views/schema-meta-box.php tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
git commit -m "feat: HowTo schema builder + metabox fields"
```

---

## Task 8: Review schema

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Modify: `bre-dev/includes/Admin/SchemaMetaBox.php`
- Modify: `bre-dev/includes/Admin/views/schema-meta-box.php`
- Modify: `bre-dev/tests/Features/SchemaEnhancerTest.php`
- Modify: `bre-dev/tests/Admin/SchemaMetaBoxTest.php`

**Step 1: Write failing tests** (append to respective test files):

`SchemaEnhancerTest.php`:
```php
public function test_build_review_from_data_correct_structure(): void {
    $schema = SchemaEnhancer::buildReviewFromData( 'Sony WH-1000XM5', 4, 'Max Muster' );
    $this->assertEquals( 'Review',           $schema['@type'] );
    $this->assertEquals( 'Sony WH-1000XM5',  $schema['itemReviewed']['name'] );
    $this->assertEquals( 4,                  $schema['reviewRating']['ratingValue'] );
    $this->assertEquals( 5,                  $schema['reviewRating']['bestRating'] );
    $this->assertEquals( 'Max Muster',       $schema['author']['name'] );
}

public function test_build_review_clamps_rating_between_1_and_5(): void {
    $schema = SchemaEnhancer::buildReviewFromData( 'X', 0, 'A' );
    $this->assertEquals( 1, $schema['reviewRating']['ratingValue'] );
    $schema = SchemaEnhancer::buildReviewFromData( 'X', 10, 'A' );
    $this->assertEquals( 5, $schema['reviewRating']['ratingValue'] );
}
```

`SchemaMetaBoxTest.php`:
```php
public function test_sanitize_data_review_extracts_fields(): void {
    $input = [
        'schema_type'    => 'review',
        'review_item'    => 'Sony Kopfhörer',
        'review_rating'  => '4',
    ];
    $clean = SchemaMetaBox::sanitizeData( $input );
    $this->assertEquals( 'Sony Kopfhörer', $clean['data']['review']['item'] );
    $this->assertEquals( 4,                $clean['data']['review']['rating'] );
}
```

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 3: Add to SchemaEnhancer.php**

```php
/**
 * Pure builder for Review schema.
 *
 * @param string $item    Name of the reviewed item.
 * @param int    $rating  Rating 1–5.
 * @param string $author  Reviewer name.
 */
public static function buildReviewFromData( string $item, int $rating, string $author ): array {
    $rating = max( 1, min( 5, $rating ) );
    return [
        '@context'     => 'https://schema.org',
        '@type'        => 'Review',
        'itemReviewed' => [
            '@type' => 'Thing',
            'name'  => $item,
        ],
        'reviewRating' => [
            '@type'       => 'Rating',
            'ratingValue' => $rating,
            'bestRating'  => 5,
            'worstRating' => 1,
        ],
        'author' => [
            '@type' => 'Person',
            'name'  => $author,
        ],
    ];
}

/** WP-dependent: builds Review from post meta. */
private function buildReviewSchema(): ?array {
    $post_id  = get_the_ID();
    $raw_data = get_post_meta( $post_id, Admin\SchemaMetaBox::META_DATA, true ) ?: '{}';
    $data     = json_decode( $raw_data, true )['review'] ?? [];
    $item     = $data['item'] ?? '';
    $rating   = (int) ( $data['rating'] ?? 0 );
    if ( empty( $item ) || $rating < 1 ) {
        return null;
    }
    return self::buildReviewFromData( $item, $rating, get_the_author() );
}
```

**Step 4: Add Review to SchemaMetaBox::sanitizeData()** — extend the if-blocks:

```php
if ( $type === 'review' ) {
    $data['review'] = [
        'item'   => sanitize_text_field( $input['review_item'] ?? '' ),
        'rating' => max( 1, min( 5, (int) ( $input['review_rating'] ?? 3 ) ) ),
    ];
}
```

**Step 5: Add Review fields to schema-meta-box.php view**

```php
<div class="bre-schema-fields" data-bre-type="review" style="display:none;">
    <p>
        <label><strong><?php esc_html_e( 'Bewertetes Produkt / Dienst', 'bavarian-rank-engine' ); ?></strong><br>
        <input type="text" name="bre_schema[review_item]"
               value="<?php echo esc_attr( $data['review']['item'] ?? '' ); ?>"
               class="widefat"></label>
    </p>
    <p>
        <label><strong><?php esc_html_e( 'Bewertung (1–5)', 'bavarian-rank-engine' ); ?></strong><br>
        <input type="number" name="bre_schema[review_rating]" min="1" max="5" step="1"
               value="<?php echo esc_attr( $data['review']['rating'] ?? 3 ); ?>"
               style="width:60px;"></label>
    </p>
</div>
```

**Step 6: Run — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 7: Commit**

```bash
git add includes/Features/SchemaEnhancer.php includes/Admin/SchemaMetaBox.php includes/Admin/views/schema-meta-box.php tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
git commit -m "feat: Review schema builder + metabox fields"
```

---

## Task 9: Recipe schema

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Modify: `bre-dev/includes/Admin/SchemaMetaBox.php`
- Modify: `bre-dev/includes/Admin/views/schema-meta-box.php`
- Modify: `bre-dev/tests/Features/SchemaEnhancerTest.php`
- Modify: `bre-dev/tests/Admin/SchemaMetaBoxTest.php`

**Step 1: Write failing tests**

`SchemaEnhancerTest.php`:
```php
public function test_build_recipe_from_data_correct_structure(): void {
    $d = [
        'name'         => 'Spaghetti Bolognese',
        'prep'         => 15,
        'cook'         => 30,
        'servings'     => '4 Portionen',
        'ingredients'  => [ '400g Spaghetti', '250g Hackfleisch' ],
        'instructions' => [ 'Wasser kochen', 'Sauce anbraten' ],
    ];
    $schema = SchemaEnhancer::buildRecipeFromData( $d );
    $this->assertEquals( 'Recipe',               $schema['@type'] );
    $this->assertEquals( 'Spaghetti Bolognese',  $schema['name'] );
    $this->assertEquals( 'PT15M',                $schema['prepTime'] );
    $this->assertEquals( 'PT30M',                $schema['cookTime'] );
    $this->assertEquals( '4 Portionen',          $schema['recipeYield'] );
    $this->assertCount( 2,                       $schema['recipeIngredient'] );
    $this->assertEquals( 'HowToStep',            $schema['recipeInstructions'][0]['@type'] );
    $this->assertEquals( 'Wasser kochen',        $schema['recipeInstructions'][0]['text'] );
}
```

`SchemaMetaBoxTest.php`:
```php
public function test_sanitize_data_recipe_extracts_fields(): void {
    $input = [
        'schema_type'          => 'recipe',
        'recipe_name'          => 'Pasta',
        'recipe_prep'          => '10',
        'recipe_cook'          => '20',
        'recipe_servings'      => '2',
        'recipe_ingredients'   => "400g Pasta\n2 Tomaten",
        'recipe_instructions'  => "Kochen\nAbgießen",
    ];
    $clean = SchemaMetaBox::sanitizeData( $input );
    $this->assertEquals( 'Pasta',     $clean['data']['recipe']['name'] );
    $this->assertEquals( 10,          $clean['data']['recipe']['prep'] );
    $this->assertCount( 2,            $clean['data']['recipe']['ingredients'] );
    $this->assertCount( 2,            $clean['data']['recipe']['instructions'] );
}
```

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 3: Add to SchemaEnhancer.php**

```php
/**
 * Pure builder for Recipe schema.
 *
 * @param array $d Keys: name, prep (int minutes), cook (int minutes),
 *                 servings (string), ingredients (string[]), instructions (string[])
 */
public static function buildRecipeFromData( array $d ): array {
    $steps = [];
    foreach ( array_filter( array_map( 'trim', $d['instructions'] ?? [] ) ) as $step ) {
        $steps[] = [ '@type' => 'HowToStep', 'text' => $step ];
    }
    $schema = [
        '@context'           => 'https://schema.org',
        '@type'              => 'Recipe',
        'name'               => $d['name'] ?? '',
        'recipeIngredient'   => array_values( array_filter( array_map( 'trim', $d['ingredients'] ?? [] ) ) ),
        'recipeInstructions' => $steps,
    ];
    if ( ! empty( $d['prep'] ) ) {
        $schema['prepTime'] = self::minutesToIsoDuration( (int) $d['prep'] );
    }
    if ( ! empty( $d['cook'] ) ) {
        $schema['cookTime'] = self::minutesToIsoDuration( (int) $d['cook'] );
    }
    if ( ! empty( $d['servings'] ) ) {
        $schema['recipeYield'] = $d['servings'];
    }
    return $schema;
}

/** WP-dependent: builds Recipe from post meta. */
private function buildRecipeSchema(): ?array {
    $post_id  = get_the_ID();
    $raw_data = get_post_meta( $post_id, Admin\SchemaMetaBox::META_DATA, true ) ?: '{}';
    $data     = json_decode( $raw_data, true )['recipe'] ?? [];
    if ( empty( $data['name'] ) ) {
        return null;
    }
    return self::buildRecipeFromData( $data );
}
```

**Step 4: Add Recipe to SchemaMetaBox::sanitizeData()**

```php
if ( $type === 'recipe' ) {
    $raw_ing  = sanitize_textarea_field( $input['recipe_ingredients']  ?? '' );
    $raw_inst = sanitize_textarea_field( $input['recipe_instructions'] ?? '' );
    $data['recipe'] = [
        'name'         => sanitize_text_field( $input['recipe_name'] ?? '' ),
        'prep'         => max( 0, (int) ( $input['recipe_prep']     ?? 0 ) ),
        'cook'         => max( 0, (int) ( $input['recipe_cook']     ?? 0 ) ),
        'servings'     => sanitize_text_field( $input['recipe_servings'] ?? '' ),
        'ingredients'  => array_values( array_filter( array_map( 'trim', explode( "\n", $raw_ing ) ) ) ),
        'instructions' => array_values( array_filter( array_map( 'trim', explode( "\n", $raw_inst ) ) ) ),
    ];
}
```

**Step 5: Add Recipe fields to schema-meta-box.php view**

```php
<div class="bre-schema-fields" data-bre-type="recipe" style="display:none;">
    <p>
        <label><strong><?php esc_html_e( 'Rezeptname', 'bavarian-rank-engine' ); ?></strong><br>
        <input type="text" name="bre_schema[recipe_name]"
               value="<?php echo esc_attr( $data['recipe']['name'] ?? '' ); ?>"
               class="widefat"></label>
    </p>
    <p style="display:flex;gap:8px;">
        <label style="flex:1;"><?php esc_html_e( 'Vorbereitung (Min)', 'bavarian-rank-engine' ); ?><br>
        <input type="number" name="bre_schema[recipe_prep]" min="0"
               value="<?php echo esc_attr( $data['recipe']['prep'] ?? '' ); ?>"
               style="width:100%;"></label>
        <label style="flex:1;"><?php esc_html_e( 'Kochzeit (Min)', 'bavarian-rank-engine' ); ?><br>
        <input type="number" name="bre_schema[recipe_cook]" min="0"
               value="<?php echo esc_attr( $data['recipe']['cook'] ?? '' ); ?>"
               style="width:100%;"></label>
    </p>
    <p>
        <label><?php esc_html_e( 'Portionen', 'bavarian-rank-engine' ); ?><br>
        <input type="text" name="bre_schema[recipe_servings]"
               value="<?php echo esc_attr( $data['recipe']['servings'] ?? '' ); ?>"
               class="widefat"></label>
    </p>
    <p>
        <label><strong><?php esc_html_e( 'Zutaten (eine pro Zeile)', 'bavarian-rank-engine' ); ?></strong><br>
        <textarea name="bre_schema[recipe_ingredients]" rows="4" class="widefat"><?php
            echo esc_textarea( implode( "\n", $data['recipe']['ingredients'] ?? [] ) );
        ?></textarea></label>
    </p>
    <p>
        <label><strong><?php esc_html_e( 'Anleitung (ein Schritt pro Zeile)', 'bavarian-rank-engine' ); ?></strong><br>
        <textarea name="bre_schema[recipe_instructions]" rows="5" class="widefat"><?php
            echo esc_textarea( implode( "\n", $data['recipe']['instructions'] ?? [] ) );
        ?></textarea></label>
    </p>
</div>
```

**Step 6: Run — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 7: Commit**

```bash
git add includes/Features/SchemaEnhancer.php includes/Admin/SchemaMetaBox.php includes/Admin/views/schema-meta-box.php tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
git commit -m "feat: Recipe schema builder + metabox fields"
```

---

## Task 10: Event schema

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`
- Modify: `bre-dev/includes/Admin/SchemaMetaBox.php`
- Modify: `bre-dev/includes/Admin/views/schema-meta-box.php`
- Modify: `bre-dev/tests/Features/SchemaEnhancerTest.php`
- Modify: `bre-dev/tests/Admin/SchemaMetaBoxTest.php`

**Step 1: Write failing tests**

`SchemaEnhancerTest.php`:
```php
public function test_build_event_offline_location(): void {
    $d = [
        'name'     => 'WordCamp München',
        'start'    => '2026-06-01',
        'end'      => '2026-06-02',
        'location' => 'Munich, Germany',
        'online'   => false,
    ];
    $schema = SchemaEnhancer::buildEventFromData( $d );
    $this->assertEquals( 'Event',           $schema['@type'] );
    $this->assertEquals( 'WordCamp München',$schema['name'] );
    $this->assertEquals( '2026-06-01',      $schema['startDate'] );
    $this->assertEquals( 'Place',           $schema['location']['@type'] );
    $this->assertEquals( 'EventScheduled',  $schema['eventStatus'] );
}

public function test_build_event_online_uses_virtual_location(): void {
    $d = [ 'name' => 'Webinar', 'start' => '2026-05-01', 'end' => '', 'location' => 'https://zoom.us/j/123', 'online' => true ];
    $schema = SchemaEnhancer::buildEventFromData( $d );
    $this->assertEquals( 'VirtualLocation', $schema['location']['@type'] );
}
```

`SchemaMetaBoxTest.php`:
```php
public function test_sanitize_data_event_extracts_fields(): void {
    $input = [
        'schema_type'    => 'event',
        'event_name'     => 'WordCamp',
        'event_start'    => '2026-06-01',
        'event_end'      => '2026-06-02',
        'event_location' => 'Munich',
        'event_online'   => '1',
    ];
    $clean = SchemaMetaBox::sanitizeData( $input );
    $this->assertEquals( 'WordCamp',   $clean['data']['event']['name'] );
    $this->assertTrue(                 $clean['data']['event']['online'] );
}
```

**Step 2: Run — verify FAIL**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 3: Add to SchemaEnhancer.php**

```php
/**
 * Pure builder for Event schema.
 *
 * @param array $d Keys: name, start (date string), end (date string),
 *                 location (string), online (bool)
 */
public static function buildEventFromData( array $d ): array {
    $location_type = ! empty( $d['online'] ) ? 'VirtualLocation' : 'Place';
    $location      = [ '@type' => $location_type, 'name' => $d['location'] ?? '' ];
    if ( ! empty( $d['online'] ) && ! empty( $d['location'] ) ) {
        $location['url'] = $d['location'];
    }
    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Event',
        'name'        => $d['name'] ?? '',
        'startDate'   => $d['start'] ?? '',
        'location'    => $location,
        'eventStatus' => 'https://schema.org/EventScheduled',
    ];
    if ( ! empty( $d['end'] ) ) {
        $schema['endDate'] = $d['end'];
    }
    return $schema;
}

/** WP-dependent: builds Event from post meta. */
private function buildEventSchema(): ?array {
    $post_id  = get_the_ID();
    $raw_data = get_post_meta( $post_id, Admin\SchemaMetaBox::META_DATA, true ) ?: '{}';
    $data     = json_decode( $raw_data, true )['event'] ?? [];
    if ( empty( $data['name'] ) || empty( $data['start'] ) ) {
        return null;
    }
    return self::buildEventFromData( $data );
}
```

**Step 4: Add Event to SchemaMetaBox::sanitizeData()**

```php
if ( $type === 'event' ) {
    $data['event'] = [
        'name'     => sanitize_text_field( $input['event_name']     ?? '' ),
        'start'    => sanitize_text_field( $input['event_start']    ?? '' ),
        'end'      => sanitize_text_field( $input['event_end']      ?? '' ),
        'location' => sanitize_text_field( $input['event_location'] ?? '' ),
        'online'   => ! empty( $input['event_online'] ),
    ];
}
```

**Step 5: Add Event fields to schema-meta-box.php view**

```php
<div class="bre-schema-fields" data-bre-type="event" style="display:none;">
    <p>
        <label><strong><?php esc_html_e( 'Event-Name', 'bavarian-rank-engine' ); ?></strong><br>
        <input type="text" name="bre_schema[event_name]"
               value="<?php echo esc_attr( $data['event']['name'] ?? '' ); ?>"
               class="widefat"></label>
    </p>
    <p>
        <label><?php esc_html_e( 'Startdatum', 'bavarian-rank-engine' ); ?><br>
        <input type="date" name="bre_schema[event_start]"
               value="<?php echo esc_attr( $data['event']['start'] ?? '' ); ?>"></label>
    </p>
    <p>
        <label><?php esc_html_e( 'Enddatum (optional)', 'bavarian-rank-engine' ); ?><br>
        <input type="date" name="bre_schema[event_end]"
               value="<?php echo esc_attr( $data['event']['end'] ?? '' ); ?>"></label>
    </p>
    <p>
        <label><?php esc_html_e( 'Ort oder URL', 'bavarian-rank-engine' ); ?><br>
        <input type="text" name="bre_schema[event_location]"
               value="<?php echo esc_attr( $data['event']['location'] ?? '' ); ?>"
               class="widefat"></label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="bre_schema[event_online]" value="1"
                   <?php checked( ! empty( $data['event']['online'] ) ); ?>>
            <?php esc_html_e( 'Online-Event', 'bavarian-rank-engine' ); ?>
        </label>
    </p>
</div>
```

**Step 6: Run — verify PASS**

```bash
cd bre-dev && php composer.phar exec phpunit tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
```

**Step 7: Commit**

```bash
git add includes/Features/SchemaEnhancer.php includes/Admin/SchemaMetaBox.php includes/Admin/views/schema-meta-box.php tests/Features/SchemaEnhancerTest.php tests/Admin/SchemaMetaBoxTest.php
git commit -m "feat: Event schema builder + metabox fields"
```

---

## Task 11: Wire all new types into SchemaEnhancer::outputJsonLd()

**Files:**
- Modify: `bre-dev/includes/Features/SchemaEnhancer.php`

**Step 1: Replace the `outputJsonLd()` method body**

The current `is_singular()` block in `outputJsonLd()` looks like:

```php
if ( is_singular() ) {
    if ( in_array( 'article_about', $enabled, true ) ) { ... }
    if ( in_array( 'author', $enabled, true ) ) { ... }
    if ( in_array( 'speakable', $enabled, true ) ) { ... }
}
```

After the `speakable` block, **inside** the `is_singular()` block, add:

```php
// Auto-types
if ( in_array( 'faq_schema', $enabled, true ) ) {
    $faq = $this->buildFaqSchema();
    if ( $faq ) {
        $schemas[] = $faq;
    }
}
if ( in_array( 'blog_posting', $enabled, true ) ) {
    $schemas[] = $this->buildBlogPosting();
}
if ( in_array( 'image_object', $enabled, true ) ) {
    $img = $this->buildImageObject();
    if ( $img ) {
        $schemas[] = $img;
    }
}
if ( in_array( 'video_object', $enabled, true ) ) {
    $vid = $this->buildVideoObject();
    if ( $vid ) {
        $schemas[] = $vid;
    }
}

// Metabox-types — only output if type matches _bre_schema_type
$schema_type = get_post_meta( get_the_ID(), \BavarianRankEngine\Admin\SchemaMetaBox::META_TYPE, true );
if ( $schema_type === 'howto' && in_array( 'howto', $enabled, true ) ) {
    $howto = $this->buildHowToSchema();
    if ( $howto ) {
        $schemas[] = $howto;
    }
}
if ( $schema_type === 'review' && in_array( 'review', $enabled, true ) ) {
    $review = $this->buildReviewSchema();
    if ( $review ) {
        $schemas[] = $review;
    }
}
if ( $schema_type === 'recipe' && in_array( 'recipe', $enabled, true ) ) {
    $recipe = $this->buildRecipeSchema();
    if ( $recipe ) {
        $schemas[] = $recipe;
    }
}
if ( $schema_type === 'event' && in_array( 'event', $enabled, true ) ) {
    $event = $this->buildEventSchema();
    if ( $event ) {
        $schemas[] = $event;
    }
}
```

**Step 2: Add `use` statement** at the top of SchemaEnhancer.php (after existing `use` line):

```php
use BavarianRankEngine\Admin\SchemaMetaBox;
```

Then replace `Admin\SchemaMetaBox::META_TYPE` and `Admin\SchemaMetaBox::META_DATA` references to just `SchemaMetaBox::META_TYPE` / `SchemaMetaBox::META_DATA`.

**Step 3: Run full test suite**

```bash
cd bre-dev && php composer.phar exec phpunit
```
Expected: all PASS.

**Step 4: Commit**

```bash
git add includes/Features/SchemaEnhancer.php
git commit -m "feat: wire all v2 schema types into outputJsonLd()"
```

---

## Task 12: Core.php — load and register SchemaMetaBox

**Files:**
- Modify: `bre-dev/includes/Core.php`

**Step 1: Add to `load_dependencies()`** — after the GeoEditorBox line:

```php
require_once BRE_DIR . 'includes/Admin/SchemaMetaBox.php';
```

**Step 2: Add to `register_hooks()`** — inside the `is_admin()` block, after GeoEditorBox:

```php
( new Admin\SchemaMetaBox() )->register();
```

**Step 3: Run full test suite**

```bash
cd bre-dev && php composer.phar exec phpunit
```
Expected: all PASS.

**Step 4: Commit**

```bash
git add includes/Core.php
git commit -m "feat: register SchemaMetaBox in Core"
```

---

## Task 13: Version bump to 1.2.0

**Files:**
- Modify: `bre-dev/seo-geo.php` — Plugin Header `Version:` + `BRE_VERSION` constant
- Modify: `bre-dev/readme.txt` — Stable tag + Changelog
- Modify: `bre-dev/CHANGELOG.md` (if it exists)

**Step 1: Update version in seo-geo.php**

Change:
```php
 * Version: 1.1.1
```
And:
```php
define( 'BRE_VERSION', '1.1.1' );
```
To `1.2.0`.

**Step 2: Update readme.txt**

Add to Changelog section:
```
= 1.2.0 =
* Neu: Schema-Suite v2 — FAQPage (auto aus GEO), BlogPosting, ImageObject, VideoObject
* Neu: Post-Editor Metabox für HowTo, Review, Recipe, Event Schema
```

**Step 3: Run full test suite one final time**

```bash
cd bre-dev && php composer.phar exec phpunit
```
Expected: all PASS.

**Step 4: Commit**

```bash
git add seo-geo.php readme.txt
git commit -m "release: v1.2.0 — Schema-Suite v2"
```

---

## Final Verification Checklist

- [ ] All PHPUnit tests pass
- [ ] FAQPage schema outputs when GEO FAQ data exists and `faq_schema` is enabled
- [ ] BlogPosting outputs `BlogPosting` for `post` type, `Article` for `page`
- [ ] VideoObject detected for YouTube embeds
- [ ] MetaBox appears in post editor when a metabox type is enabled in settings
- [ ] HowTo/Review/Recipe/Event only output when both enabled in settings AND type matches `_bre_schema_type`
- [ ] No JSON-LD output for disabled types
- [ ] `bavarian-rank-engine/` is only updated via `bash bin/build.sh`
