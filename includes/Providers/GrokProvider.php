<?php
namespace BavarianRankEngine\Providers;

class GrokProvider implements ProviderInterface {
    private const API_URL = 'https://api.x.ai/v1/chat/completions';

    public function getId(): string { return 'grok'; }
    public function getName(): string { return 'xAI Grok'; }

    public function getModels(): array {
        return [
            'grok-3'      => 'Grok 3 (Empfohlen)',
            'grok-3-mini' => 'Grok 3 Mini (Günstig)',
        ];
    }

    public function testConnection( string $api_key ): array {
        try {
            $this->generateText( 'Say "ok"', $api_key, 'grok-3-mini', 5 );
            return [ 'success' => true, 'message' => 'Verbindung erfolgreich' ];
        } catch ( \RuntimeException $e ) {
            return [ 'success' => false, 'message' => $e->getMessage() ];
        }
    }

    public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => $model,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                'max_tokens' => $max_tokens,
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

        return trim( $body['choices'][0]['message']['content'] ?? '' );
    }
}
