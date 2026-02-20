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
}
