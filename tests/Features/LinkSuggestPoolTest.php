<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\LinkSuggest;

class LinkSuggestPoolTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['bre_transients']    = [];
        $GLOBALS['bre_test_options']  = [];
    }

    public function test_get_settings_returns_defaults(): void {
        $settings = LinkSuggest::getSettings();
        $this->assertSame( 'manual', $settings['trigger'] );
        $this->assertSame( 2, $settings['interval_min'] );
        $this->assertSame( [], $settings['excluded_posts'] );
        $this->assertSame( [], $settings['boosted_posts'] );
        $this->assertSame( 20, $settings['ai_candidates'] );
        $this->assertSame( 400, $settings['ai_max_tokens'] );
    }

    public function test_get_settings_merges_saved_values(): void {
        $GLOBALS['bre_test_options']['bre_link_suggest_settings'] = [
            'trigger'      => 'save',
            'interval_min' => 5,
        ];
        $settings = LinkSuggest::getSettings();
        $this->assertSame( 'save', $settings['trigger'] );
        $this->assertSame( 5, $settings['interval_min'] );
        $this->assertSame( 20, $settings['ai_candidates'] );
    }

    public function test_build_boost_map_returns_id_factor_map(): void {
        $boosted = [
            [ 'id' => 10, 'boost' => 2.0 ],
            [ 'id' => 20, 'boost' => 1.5 ],
        ];
        $map = LinkSuggest::buildBoostMap( $boosted );
        $this->assertSame( 2.0, $map[10] );
        $this->assertSame( 1.5, $map[20] );
    }

    public function test_build_boost_map_clamps_boost_to_minimum_one(): void {
        $map = LinkSuggest::buildBoostMap( [ [ 'id' => 5, 'boost' => 0.5 ] ] );
        $this->assertSame( 1.0, $map[5] );
    }

    public function test_build_boost_map_ignores_invalid_ids(): void {
        $map = LinkSuggest::buildBoostMap( [ [ 'id' => 0, 'boost' => 2.0 ] ] );
        $this->assertEmpty( $map );
    }

    public function test_invalidate_cache_deletes_transient(): void {
        $GLOBALS['bre_transients']['bre_link_candidate_pool'] = [ 'some' => 'data' ];
        ( new LinkSuggest() )->invalidate_cache();
        $this->assertFalse( get_transient( 'bre_link_candidate_pool' ) );
    }
}
