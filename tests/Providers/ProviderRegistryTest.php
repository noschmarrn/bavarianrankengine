<?php
namespace SeoGeo\Tests\Providers;

use PHPUnit\Framework\TestCase;
use SeoGeo\ProviderRegistry;
use SeoGeo\Providers\ProviderInterface;

class ProviderRegistryTest extends TestCase {
    public function test_register_and_get_provider(): void {
        $registry = new ProviderRegistry();
        $mock = $this->createMock( ProviderInterface::class );
        $mock->method('getId')->willReturn('test');
        $mock->method('getName')->willReturn('Test Provider');
        $registry->register( $mock );
        $this->assertSame( $mock, $registry->get('test') );
    }

    public function test_get_nonexistent_returns_null(): void {
        $registry = new ProviderRegistry();
        $this->assertNull( $registry->get('nonexistent') );
    }

    public function test_get_select_options(): void {
        $registry = new ProviderRegistry();
        $mock = $this->createMock( ProviderInterface::class );
        $mock->method('getId')->willReturn('openai');
        $mock->method('getName')->willReturn('OpenAI');
        $registry->register( $mock );
        $options = $registry->getSelectOptions();
        $this->assertArrayHasKey('openai', $options);
        $this->assertEquals('OpenAI', $options['openai']);
    }
}
