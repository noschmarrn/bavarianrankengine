<?php
namespace BavarianRankEngine\Tests\Providers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\ProviderRegistry;
use BavarianRankEngine\Providers\ProviderInterface;

class ProviderRegistryTest extends TestCase {
    protected function setUp(): void {
        ProviderRegistry::reset();
    }

    protected function tearDown(): void {
        ProviderRegistry::reset();
    }

    public function test_register_and_get_provider(): void {
        $registry = ProviderRegistry::instance();
        $mock = $this->createMock( ProviderInterface::class );
        $mock->method('getId')->willReturn('test');
        $mock->method('getName')->willReturn('Test Provider');
        $registry->register( $mock );
        $this->assertSame( $mock, $registry->get('test') );
    }

    public function test_get_nonexistent_returns_null(): void {
        $this->assertNull( ProviderRegistry::instance()->get('nonexistent') );
    }

    public function test_get_select_options(): void {
        $registry = ProviderRegistry::instance();
        $mock = $this->createMock( ProviderInterface::class );
        $mock->method('getId')->willReturn('openai');
        $mock->method('getName')->willReturn('OpenAI');
        $registry->register( $mock );
        $options = $registry->getSelectOptions();
        $this->assertArrayHasKey('openai', $options);
        $this->assertEquals('OpenAI', $options['openai']);
    }
}
