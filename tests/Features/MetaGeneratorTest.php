<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\MetaGenerator;

class MetaGeneratorTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['bre_test_meta'] = [];
    }

    public function test_has_existing_meta_returns_false_when_no_meta(): void {
        $gen = new MetaGenerator();
        $this->assertFalse( $gen->hasExistingMeta( 999 ) );
    }

    public function test_has_existing_meta_returns_true_when_bre_meta_set(): void {
        $GLOBALS['bre_test_meta'] = [ 42 => [ '_bre_meta_description' => 'some desc' ] ];
        $gen = new MetaGenerator();
        $this->assertTrue( $gen->hasExistingMeta( 42 ) );
    }

    public function test_has_existing_meta_returns_true_when_rank_math_set(): void {
        $GLOBALS['bre_test_meta'] = [ 77 => [ 'rank_math_description' => 'rm desc' ] ];
        $gen = new MetaGenerator();
        $this->assertTrue( $gen->hasExistingMeta( 77 ) );
    }
}
