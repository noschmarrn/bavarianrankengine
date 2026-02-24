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

    public function test_extract_video_detects_youtube_embed_url(): void {
        $content = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
        $result  = SchemaEnhancer::extractVideoFromContent( $content );
        $this->assertEquals( 'dQw4w9WgXcQ', $result['videoId'] );
        $this->assertEquals( 'youtube',     $result['platform'] );
    }

    public function test_extract_video_detects_youtu_be_url(): void {
        $content = '<a href="https://youtu.be/dQw4w9WgXcQ">Video</a>';
        $result  = SchemaEnhancer::extractVideoFromContent( $content );
        $this->assertEquals( 'dQw4w9WgXcQ', $result['videoId'] );
    }

    public function test_extract_video_detects_vimeo(): void {
        $content = '<iframe src="https://player.vimeo.com/video/123456789"></iframe>';
        $result  = SchemaEnhancer::extractVideoFromContent( $content );
        $this->assertEquals( '123456789', $result['videoId'] );
        $this->assertEquals( 'vimeo',     $result['platform'] );
    }

    public function test_extract_video_returns_null_for_no_video(): void {
        $this->assertNull( SchemaEnhancer::extractVideoFromContent( '<p>No video here.</p>' ) );
    }

    public function test_build_howto_from_data_returns_correct_structure(): void {
        $schema = SchemaEnhancer::buildHowToFromData( 'Pasta kochen', array( 'Wasser kochen', 'Pasta hinzufügen', 'Abtropfen' ) );
        $this->assertEquals( 'HowTo',         $schema['@type'] );
        $this->assertEquals( 'Pasta kochen',  $schema['name'] );
        $this->assertCount( 3,                $schema['step'] );
        $this->assertEquals( 'HowToStep',     $schema['step'][0]['@type'] );
        $this->assertEquals( 'Wasser kochen', $schema['step'][0]['name'] );
    }

    public function test_build_howto_filters_empty_steps(): void {
        $schema = SchemaEnhancer::buildHowToFromData( 'Test', array( 'Schritt 1', '', '  ', 'Schritt 2' ) );
        $this->assertCount( 2, $schema['step'] );
    }

    public function test_build_review_from_data_correct_structure(): void {
        $schema = SchemaEnhancer::buildReviewFromData( 'Sony WH-1000XM5', 4, 'Max Muster' );
        $this->assertEquals( 'Review',          $schema['@type'] );
        $this->assertEquals( 'Sony WH-1000XM5', $schema['itemReviewed']['name'] );
        $this->assertEquals( 4,                 $schema['reviewRating']['ratingValue'] );
        $this->assertEquals( 5,                 $schema['reviewRating']['bestRating'] );
        $this->assertEquals( 'Max Muster',      $schema['author']['name'] );
    }

    public function test_build_review_clamps_rating_between_1_and_5(): void {
        $schema = SchemaEnhancer::buildReviewFromData( 'X', 0, 'A' );
        $this->assertEquals( 1, $schema['reviewRating']['ratingValue'] );
        $schema = SchemaEnhancer::buildReviewFromData( 'X', 10, 'A' );
        $this->assertEquals( 5, $schema['reviewRating']['ratingValue'] );
    }

    public function test_build_recipe_from_data_correct_structure(): void {
        $d = array(
            'name'         => 'Spaghetti Bolognese',
            'prep'         => 15,
            'cook'         => 30,
            'servings'     => '4 Portionen',
            'ingredients'  => array( '400g Spaghetti', '250g Hackfleisch' ),
            'instructions' => array( 'Wasser kochen', 'Sauce anbraten' ),
        );
        $schema = SchemaEnhancer::buildRecipeFromData( $d );
        $this->assertEquals( 'Recipe',              $schema['@type'] );
        $this->assertEquals( 'Spaghetti Bolognese', $schema['name'] );
        $this->assertEquals( 'PT15M',               $schema['prepTime'] );
        $this->assertEquals( 'PT30M',               $schema['cookTime'] );
        $this->assertEquals( '4 Portionen',         $schema['recipeYield'] );
        $this->assertCount( 2,                      $schema['recipeIngredient'] );
        $this->assertEquals( 'HowToStep',           $schema['recipeInstructions'][0]['@type'] );
        $this->assertEquals( 'Wasser kochen',       $schema['recipeInstructions'][0]['text'] );
    }
}
