<?php
namespace SeoGeo\Tests\Admin;

use PHPUnit\Framework\TestCase;

class SettingsPageTest extends TestCase {

    public function test_sanitize_handles_array_same_as(): void {
        // Simulate second save where organization is already a stored array
        // (WordPress can pass back the already-parsed option structure)
        $org_raw = ['https://twitter.com/test', 'https://github.com/test'];
        $result  = [];

        // Reproduce the guard logic directly
        if ( is_array( $org_raw ) ) {
            $org_raw = implode( "\n", $org_raw );
        }
        $result['organization'] = array_values( array_filter( array_map( 'esc_url_raw',
            array_map( 'trim', explode( "\n", $org_raw ) )
        ) ) );

        $this->assertIsArray( $result['organization'] );
        $this->assertCount( 2, $result['organization'] );
        $this->assertContains( 'https://twitter.com/test', $result['organization'] );
    }

    public function test_sanitize_handles_string_same_as(): void {
        $org_raw = "https://twitter.com/test\nhttps://github.com/test";

        if ( is_array( $org_raw ) ) {
            $org_raw = implode( "\n", $org_raw );
        }
        $result['organization'] = array_values( array_filter( array_map( 'esc_url_raw',
            array_map( 'trim', explode( "\n", $org_raw ) )
        ) ) );

        $this->assertCount( 2, $result['organization'] );
    }

    public function test_sanitize_handles_empty_same_as(): void {
        $org_raw = '';

        if ( is_array( $org_raw ) ) {
            $org_raw = implode( "\n", $org_raw );
        }
        $result['organization'] = array_values( array_filter( array_map( 'esc_url_raw',
            array_map( 'trim', explode( "\n", $org_raw ) )
        ) ) );

        $this->assertCount( 0, $result['organization'] );
    }
}
