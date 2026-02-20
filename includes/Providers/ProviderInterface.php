<?php
namespace SeoGeo\Providers;

interface ProviderInterface {
    /** Unique machine-readable ID, e.g. 'openai' */
    public function getId(): string;

    /** Human-readable label for dropdowns */
    public function getName(): string;

    /**
     * Available models as ['model-id' => 'Human Label']
     * Ordered from most capable to least expensive
     */
    public function getModels(): array;

    /**
     * Test API connectivity with minimal cost.
     * Returns ['success' => bool, 'message' => string]
     */
    public function testConnection( string $api_key ): array;

    /**
     * Generate text from a prompt.
     *
     * @param string $prompt    The full prompt to send
     * @param string $api_key   Provider API key
     * @param string $model     Model ID from getModels()
     * @param int    $max_tokens Maximum tokens in response (0 = provider default)
     * @return string           Generated text or empty string on failure
     * @throws \RuntimeException on API error
     */
    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string;
}
