<?php
namespace BavarianRankEngine\Tests\Admin;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Admin\SchemaMetaBox;

class SchemaMetaBoxTest extends TestCase {
    public function test_sanitize_data_returns_valid_type_or_empty(): void {
        $clean = SchemaMetaBox::sanitizeData( array( 'schema_type' => 'howto' ) );
        $this->assertEquals( 'howto', $clean['schema_type'] );

        $clean = SchemaMetaBox::sanitizeData( array( 'schema_type' => 'invalid' ) );
        $this->assertEquals( '', $clean['schema_type'] );
    }

    public function test_sanitize_data_accepts_all_valid_types(): void {
        foreach ( array( 'howto', 'review', 'recipe', 'event', '' ) as $type ) {
            $clean = SchemaMetaBox::sanitizeData( array( 'schema_type' => $type ) );
            $this->assertEquals( $type, $clean['schema_type'] );
        }
    }

    public function test_sanitize_data_howto_extracts_steps(): void {
        $input = array(
            'schema_type' => 'howto',
            'howto_name'  => 'Pasta kochen',
            'howto_steps' => "Wasser kochen\nPasta hinzufügen\nAbtropfen",
        );
        $clean = SchemaMetaBox::sanitizeData( $input );
        $this->assertEquals( 'howto',          $clean['schema_type'] );
        $this->assertEquals( 'Pasta kochen',   $clean['data']['howto']['name'] );
        $this->assertCount( 3,                 $clean['data']['howto']['steps'] );
        $this->assertEquals( 'Wasser kochen',  $clean['data']['howto']['steps'][0] );
    }
}
