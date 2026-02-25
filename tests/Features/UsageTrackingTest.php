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
