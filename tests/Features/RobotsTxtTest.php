<?php
namespace BavarianRankEngine\Tests\Features;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Features\RobotsTxt;

class RobotsTxtTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['bre_test_options'] = [];
    }

    public function test_known_bots_list_not_empty(): void {
        $this->assertNotEmpty( RobotsTxt::KNOWN_BOTS );
        $this->assertArrayHasKey( 'GPTBot', RobotsTxt::KNOWN_BOTS );
        $this->assertArrayHasKey( 'ClaudeBot', RobotsTxt::KNOWN_BOTS );
    }

    public function test_get_settings_returns_defaults(): void {
        $settings = RobotsTxt::getSettings();
        $this->assertArrayHasKey( 'blocked_bots', $settings );
        $this->assertIsArray( $settings['blocked_bots'] );
        $this->assertEmpty( $settings['blocked_bots'] );
    }

    public function test_append_rules_adds_disallow_for_blocked_bot(): void {
        $GLOBALS['bre_test_options']['bre_robots_settings'] = [ 'blocked_bots' => [ 'GPTBot' ] ];
        $robots = new RobotsTxt();
        $method = new \ReflectionMethod( RobotsTxt::class, 'append_rules' );
        $method->setAccessible( true );
        $output = $method->invoke( $robots, "User-agent: *\nAllow: /\n", true );
        $this->assertStringContainsString( 'User-agent: GPTBot', $output );
        $this->assertStringContainsString( 'Disallow: /', $output );
    }

    public function test_append_rules_ignores_unknown_bots(): void {
        $GLOBALS['bre_test_options']['bre_robots_settings'] = [ 'blocked_bots' => [ 'EvilBot99' ] ];
        $robots = new RobotsTxt();
        $method = new \ReflectionMethod( RobotsTxt::class, 'append_rules' );
        $method->setAccessible( true );
        $output = $method->invoke( $robots, '', true );
        $this->assertStringNotContainsString( 'EvilBot99', $output );
    }

    public function test_append_rules_unchanged_when_no_bots_blocked(): void {
        $robots  = new RobotsTxt();
        $method  = new \ReflectionMethod( RobotsTxt::class, 'append_rules' );
        $method->setAccessible( true );
        $input   = "User-agent: *\nAllow: /\n";
        $output  = $method->invoke( $robots, $input, true );
        $this->assertSame( $input, $output );
    }
}
