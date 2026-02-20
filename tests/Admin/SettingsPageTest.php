<?php
namespace BavarianRankEngine\Tests\Admin;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Admin\SettingsPage;

class SettingsPageTest extends TestCase {

    private function make_base_input( array $override = [] ): array {
        return array_merge( [
            'provider'          => 'openai',
            'meta_auto_enabled' => '1',
            'token_mode'        => 'limit',
            'token_limit'       => '1000',
            'prompt'            => 'Test prompt',
            'api_keys'          => [],
            'models'            => [],
            'meta_post_types'   => [],
            'schema_enabled'    => [],
            'schema_same_as'    => [ 'organization' => '' ],
        ], $override );
    }

    public function test_sanitize_handles_array_same_as(): void {
        $page  = new SettingsPage();
        $input = $this->make_base_input( [
            'schema_same_as' => [ 'organization' => [
                'https://twitter.com/test',
                'https://github.com/test',
            ] ],
        ] );

        $result = $page->sanitize_settings( $input );

        $this->assertIsArray( $result['schema_same_as']['organization'] );
        $this->assertCount( 2, $result['schema_same_as']['organization'] );
        $this->assertContains( 'https://twitter.com/test', $result['schema_same_as']['organization'] );
    }

    public function test_sanitize_handles_string_same_as(): void {
        $page  = new SettingsPage();
        $input = $this->make_base_input( [
            'schema_same_as' => [ 'organization' => "https://twitter.com/test\nhttps://github.com/test" ],
        ] );

        $result = $page->sanitize_settings( $input );

        $this->assertCount( 2, $result['schema_same_as']['organization'] );
    }

    public function test_sanitize_handles_empty_same_as(): void {
        $page  = new SettingsPage();
        $input = $this->make_base_input( [
            'schema_same_as' => [ 'organization' => '' ],
        ] );

        $result = $page->sanitize_settings( $input );

        $this->assertCount( 0, $result['schema_same_as']['organization'] );
    }
}
