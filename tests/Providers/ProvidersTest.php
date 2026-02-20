<?php
namespace BavarianRankEngine\Tests\Providers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Providers\OpenAIProvider;
use BavarianRankEngine\Providers\AnthropicProvider;
use BavarianRankEngine\Providers\GeminiProvider;
use BavarianRankEngine\Providers\GrokProvider;
use BavarianRankEngine\Providers\ProviderInterface;

class ProvidersTest extends TestCase {
    public function test_openai_implements_interface(): void {
        $this->assertInstanceOf( ProviderInterface::class, new OpenAIProvider() );
    }

    public function test_openai_id_and_models(): void {
        $p = new OpenAIProvider();
        $this->assertEquals('openai', $p->getId());
        $this->assertArrayHasKey('gpt-4.1', $p->getModels());
    }

    public function test_anthropic_implements_interface(): void {
        $this->assertInstanceOf( ProviderInterface::class, new AnthropicProvider() );
    }

    public function test_anthropic_id_and_models(): void {
        $p = new AnthropicProvider();
        $this->assertEquals('anthropic', $p->getId());
        $this->assertArrayHasKey('claude-sonnet-4-6', $p->getModels());
    }

    public function test_gemini_implements_interface(): void {
        $this->assertInstanceOf( ProviderInterface::class, new GeminiProvider() );
    }

    public function test_gemini_id_and_models(): void {
        $p = new GeminiProvider();
        $this->assertEquals('gemini', $p->getId());
        $this->assertArrayHasKey('gemini-2.0-flash', $p->getModels());
    }

    public function test_grok_implements_interface(): void {
        $this->assertInstanceOf( ProviderInterface::class, new GrokProvider() );
    }

    public function test_grok_id_and_models(): void {
        $p = new GrokProvider();
        $this->assertEquals('grok', $p->getId());
        $this->assertArrayHasKey('grok-3', $p->getModels());
    }
}
