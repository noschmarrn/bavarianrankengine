<?php
namespace SeoGeo\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use SeoGeo\Helpers\TokenEstimator;

class TokenEstimatorTest extends TestCase {
    public function test_estimate_tokens_approximation(): void {
        $text   = str_repeat('a', 400); // 400 chars ≈ 100 tokens
        $tokens = TokenEstimator::estimate( $text );
        $this->assertGreaterThan( 80, $tokens );
        $this->assertLessThan( 120, $tokens );
    }

    public function test_truncate_to_token_limit(): void {
        $text      = str_repeat('word ', 500); // 2500 chars ≈ 625 tokens
        $truncated = TokenEstimator::truncate( $text, 100 );
        $this->assertLessThanOrEqual( 100, TokenEstimator::estimate( $truncated ) );
    }

    public function test_estimate_cost_openai_gpt4(): void {
        $cost = TokenEstimator::estimateCost( 1000, 'openai', 'gpt-4.1', 'input' );
        $this->assertIsFloat( $cost );
        $this->assertGreaterThan( 0, $cost );
    }

    public function test_format_cost_small_amount(): void {
        $formatted = TokenEstimator::formatCost( 0.001 );
        $this->assertStringContainsString( '0,01', $formatted );
    }

    public function test_truncate_short_text_unchanged(): void {
        $text      = 'Hello world';
        $truncated = TokenEstimator::truncate( $text, 1000 );
        $this->assertEquals( $text, $truncated );
    }
}
