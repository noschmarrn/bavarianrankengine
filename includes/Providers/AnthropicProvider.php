<?php
namespace BavarianRankEngine\Providers;

class AnthropicProvider implements ProviderInterface {
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function getId(): string { return 'anthropic'; }
    public function getName(): string { return 'Anthropic (Claude)'; }

    public function getModels(): array {
        return [
            'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (Empfohlen)',
            'claude-opus-4-6'           => 'Claude Opus 4.6 (Leistungsstark)',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (Schnell & günstig)',
        ];
    }

    public function testConnection( string $api_key ): array {
        try {
            $this->generateText( 'Say "ok"', $api_key, 'claude-haiku-4-5-20251001', 5 );
            return [ 'success' => true, 'message' => 'Verbindung erfolgreich' ];
        } catch ( \RuntimeException $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            throw new \RuntimeException( $msg );
        }

        return trim( $body['content'][0]['text'] ?? '' );
    }
}
