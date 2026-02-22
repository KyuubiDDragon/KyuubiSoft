<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Modules\AI\Providers\AIProviderInterface;
use App\Modules\AI\Providers\AnthropicProvider;
use App\Modules\AI\Providers\OllamaProvider;
use App\Modules\AI\Providers\OpenAIProvider;
use App\Modules\AI\Providers\OpenRouterProvider;
use App\Modules\AI\Providers\CustomProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OpenAIProviderTest extends TestCase
{
    // ─── OpenAIProvider ───────────────────────────────────────────────────────

    public function testOpenAIProviderImplementsInterface(): void
    {
        $provider = new OpenAIProvider();
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testOpenAIGetNameReturnsOpenai(): void
    {
        $provider = new OpenAIProvider();
        $this->assertEquals('openai', $provider->getName());
    }

    public function testOpenAIEndpointUrl(): void
    {
        $provider = new OpenAIProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getEndpointUrl');
        $method->setAccessible(true);

        $url = $method->invoke($provider, null);
        $this->assertEquals('https://api.openai.com/v1/chat/completions', $url);
    }

    public function testOpenAIHeadersContainBearerToken(): void
    {
        $provider = new OpenAIProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($provider, 'sk-test-key');
        $this->assertContains('Authorization: Bearer sk-test-key', $headers);
    }

    // ─── OpenRouterProvider ───────────────────────────────────────────────────

    public function testOpenRouterGetNameReturnsOpenrouter(): void
    {
        $provider = new OpenRouterProvider();
        $this->assertEquals('openrouter', $provider->getName());
    }

    public function testOpenRouterEndpointUrl(): void
    {
        $provider = new OpenRouterProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getEndpointUrl');
        $method->setAccessible(true);

        $url = $method->invoke($provider, null);
        $this->assertStringContainsString('openrouter.ai', $url);
    }

    // ─── AnthropicProvider ────────────────────────────────────────────────────

    public function testAnthropicProviderImplementsInterface(): void
    {
        $provider = new AnthropicProvider();
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testAnthropicGetNameReturnsAnthropic(): void
    {
        $provider = new AnthropicProvider();
        $this->assertEquals('anthropic', $provider->getName());
    }

    // ─── OllamaProvider ──────────────────────────────────────────────────────

    public function testOllamaProviderImplementsInterface(): void
    {
        $provider = new OllamaProvider();
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testOllamaGetNameReturnsOllama(): void
    {
        $provider = new OllamaProvider();
        $this->assertEquals('ollama', $provider->getName());
    }

    // ─── CustomProvider ───────────────────────────────────────────────────────

    public function testCustomProviderImplementsInterface(): void
    {
        $provider = new CustomProvider();
        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCustomGetNameReturnsCustom(): void
    {
        $provider = new CustomProvider();
        $this->assertEquals('custom', $provider->getName());
    }

    // ─── All providers ────────────────────────────────────────────────────────

    /**
     * @dataProvider providerNameProvider
     */
    public function testAllProvidersHaveNonEmptyName(AIProviderInterface $provider): void
    {
        $this->assertNotEmpty($provider->getName());
    }

    /**
     * @dataProvider providerNameProvider
     */
    public function testAllProvidersHaveUniqueName(AIProviderInterface $provider): void
    {
        // Names must be lowercase alphanumeric (used as array keys)
        $this->assertMatchesRegularExpression('/^[a-z]+$/', $provider->getName());
    }

    public static function providerNameProvider(): array
    {
        return [
            'openai'      => [new OpenAIProvider()],
            'openrouter'  => [new OpenRouterProvider()],
            'anthropic'   => [new AnthropicProvider()],
            'ollama'      => [new OllamaProvider()],
            'custom'      => [new CustomProvider()],
        ];
    }
}
