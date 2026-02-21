<?php
namespace BavarianRankEngine\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Helpers\FallbackMeta;

class FallbackMetaTest extends TestCase {

    private function make_post( string $content ): \stdClass {
        $post               = new \stdClass();
        $post->post_content = $content;
        return $post;
    }

    public function test_extracts_plain_text_from_html(): void {
        $post   = $this->make_post( '<p>Hello world. This is a test.</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertStringNotContainsString( '<p>', $result );
        $this->assertStringContainsString( 'Hello world', $result );
    }

    public function test_result_is_max_160_chars(): void {
        $long   = str_repeat( 'This is a test sentence. ', 20 );
        $post   = $this->make_post( '<p>' . $long . '</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertLessThanOrEqual( 160, mb_strlen( $result ) );
    }

    public function test_short_content_returned_as_is(): void {
        $post   = $this->make_post( '<p>Short.</p>' );
        $result = FallbackMeta::extract( $post );
        $this->assertSame( 'Short.', $result );
    }

    public function test_empty_content_returns_empty_string(): void {
        $post   = $this->make_post( '' );
        $result = FallbackMeta::extract( $post );
        $this->assertSame( '', $result );
    }

    public function test_does_not_cut_mid_word(): void {
        // 200 chars of 'abcdefghij ' repeated
        $content = '<p>' . str_repeat( 'abcdefghij ', 20 ) . '</p>';
        $post    = $this->make_post( $content );
        $result  = FallbackMeta::extract( $post );
        // Must not end with a partial 10-char word fragment
        $this->assertLessThanOrEqual( 160, mb_strlen( $result ) );
        // Must not end mid-word (last char should be . or … or a letter at a word end)
        $this->assertMatchesRegularExpression( '/[\w\.…]$/', $result );
    }

    public function test_sentence_boundary_preferred_over_word_boundary(): void {
        // Construct text where a sentence fits nicely under 160 chars
        $sentence = str_repeat( 'Word ', 25 ) . '. '; // ~127 chars sentence
        $more     = str_repeat( 'Extra ', 30 );       // would push over 160
        $post     = $this->make_post( '<p>' . $sentence . $more . '</p>' );
        $result   = FallbackMeta::extract( $post );
        // Should end at the sentence boundary (the period)
        $this->assertLessThanOrEqual( 160, mb_strlen( $result ) );
    }
}
