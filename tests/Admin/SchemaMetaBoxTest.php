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
}
