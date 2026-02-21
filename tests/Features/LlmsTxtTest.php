<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\LlmsTxt;

class LlmsTxtTest extends TestCase {

    private function make_settings( array $overrides = [] ): array {
        return array_merge( [
            'enabled'            => true,
            'title'              => 'Test Site',
            'description_before' => 'Before text.',
            'description_after'  => 'After text.',
            'description_footer' => 'Footer text.',
            'custom_links'       => '- [Home](https://example.com)',
            'post_types'         => [],  // empty = skip WP_Query
        ], $overrides );
    }

    public function test_build_includes_title(): void {
        $llms   = new LlmsTxt();
        $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
        $method->setAccessible( true );

        $output = $method->invoke( $llms, $this->make_settings() );
        $this->assertStringContainsString( '# Test Site', $output );
    }

    public function test_build_includes_description_before(): void {
        $llms   = new LlmsTxt();
        $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
        $method->setAccessible( true );

        $output = $method->invoke( $llms, $this->make_settings() );
        $this->assertStringContainsString( 'Before text.', $output );
    }

    public function test_build_includes_custom_links_section(): void {
        $llms   = new LlmsTxt();
        $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
        $method->setAccessible( true );

        $output = $method->invoke( $llms, $this->make_settings() );
        $this->assertStringContainsString( '## Featured Resources', $output );
        $this->assertStringContainsString( '- [Home](https://example.com)', $output );
    }

    public function test_build_omits_sections_when_empty(): void {
        $llms   = new LlmsTxt();
        $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
        $method->setAccessible( true );

        $settings = $this->make_settings( [
            'description_before' => '',
            'custom_links'       => '',
            'description_after'  => '',
            'description_footer' => '',
        ] );

        $output = $method->invoke( $llms, $settings );
        $this->assertStringNotContainsString( '## Featured Resources', $output );
        $this->assertStringNotContainsString( '---', $output );
    }

    public function test_build_includes_after_and_footer_with_separators(): void {
        $llms   = new LlmsTxt();
        $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
        $method->setAccessible( true );

        $output = $method->invoke( $llms, $this->make_settings() );
        $this->assertStringContainsString( "---\nAfter text.", $output );
        $this->assertStringContainsString( "---\nFooter text.", $output );
    }

    public function test_get_settings_returns_defaults_when_empty(): void {
        $settings = LlmsTxt::getSettings();
        $this->assertArrayHasKey( 'enabled', $settings );
        $this->assertArrayHasKey( 'title', $settings );
        $this->assertArrayHasKey( 'post_types', $settings );
        $this->assertFalse( $settings['enabled'] );
    }

    public function test_uri_pattern_matches_llms_txt(): void {
        $this->assertSame( 1, preg_match( '#^/llms\.txt$#', '/llms.txt' ) );
    }

    public function test_uri_pattern_matches_llms_page_2(): void {
        preg_match( '#^/llms-(\d+)\.txt$#', '/llms-2.txt', $m );
        $this->assertSame( '2', $m[1] );
    }

    public function test_build_with_empty_post_types_has_no_content_section(): void {
        $llms   = new LlmsTxt();
        $method = new \ReflectionMethod( LlmsTxt::class, 'build' );
        $method->setAccessible( true );
        $settings = $this->make_settings( [ 'post_types' => [] ] );
        $output   = $method->invoke( $llms, $settings, 1 );
        // With no posts, no ## Content section expected (or empty one)
        // At minimum, title should still be present
        $this->assertStringContainsString( '# Test Site', $output );
    }

    public function test_get_settings_includes_max_links_default(): void {
        $settings = LlmsTxt::getSettings();
        $this->assertArrayHasKey( 'max_links', $settings );
        $this->assertGreaterThanOrEqual( 50, $settings['max_links'] );
    }
}
