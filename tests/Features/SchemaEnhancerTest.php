<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\SchemaEnhancer;

class SchemaEnhancerTest extends TestCase {
    public function test_faq_pairs_to_schema_returns_null_for_empty(): void {
        $this->assertNull( SchemaEnhancer::faqPairsToSchema( [] ) );
    }

    public function test_faq_pairs_to_schema_builds_correct_structure(): void {
        $faq = [
            [ 'q' => 'Was ist das?', 'a' => 'Das ist ein Test.' ],
            [ 'q' => 'Warum?',       'a' => 'Weil ja.' ],
        ];
        $schema = SchemaEnhancer::faqPairsToSchema( $faq );

        $this->assertEquals( 'https://schema.org',      $schema['@context'] );
        $this->assertEquals( 'FAQPage',                  $schema['@type'] );
        $this->assertCount( 2,                           $schema['mainEntity'] );
        $this->assertEquals( 'Question',                 $schema['mainEntity'][0]['@type'] );
        $this->assertEquals( 'Was ist das?',             $schema['mainEntity'][0]['name'] );
        $this->assertEquals( 'Answer',                   $schema['mainEntity'][0]['acceptedAnswer']['@type'] );
        $this->assertEquals( 'Das ist ein Test.',        $schema['mainEntity'][0]['acceptedAnswer']['text'] );
    }

    public function test_faq_pairs_skips_incomplete_items(): void {
        $faq = [
            [ 'q' => 'Vollständig', 'a' => 'Ja.' ],
            [ 'q' => 'Nur Frage'                  ],
            [ 'a' => 'Nur Antwort'                ],
        ];
        $schema = SchemaEnhancer::faqPairsToSchema( $faq );
        $this->assertCount( 1, $schema['mainEntity'] );
    }

    public function test_minutes_to_iso_duration_values(): void {
        $this->assertEquals( 'PT90M', SchemaEnhancer::minutesToIsoDuration( 90 ) );
        $this->assertEquals( 'PT5M',  SchemaEnhancer::minutesToIsoDuration( 5 ) );
        $this->assertEquals( 'PT0M',  SchemaEnhancer::minutesToIsoDuration( 0 ) );
    }
}
