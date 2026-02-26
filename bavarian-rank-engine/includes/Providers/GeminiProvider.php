<?php
namespace BavarianRankEngine\Providers;

class GeminiProvider implements ProviderInterface {
	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	public function getId(): string {
		return 'gemini'; }
	public function getName(): string {
		return 'Google Gemini'; }

	public function getModels(): array {
		return array(
			'gemini-2.0-flash'      => 'Gemini 2.0 Flash (Empfohlen)',
			'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (Günstig)',
			'gemini-1.5-pro'        => 'Gemini 1.5 Pro',
		);
	}

	public function testConnection( string $api_key ): array {
		try {
			$this->generateText( 'Say "ok"', $api_key, 'gemini-2.0-flash-lite', 5 );
			return array(
				'success' => true,
				'message' => 'Verbindung erfolgreich',
			);
		} catch ( \RuntimeException $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
		$url      = self::API_BASE . $model . ':generateContent';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
						'generationConfig' => array( 'maxOutputTokens' => $max_tokens ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( esc_html( $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $body['error']['message'] ?? "HTTP $code";
			throw new \RuntimeException( esc_html( $msg ) );
		}

		return trim( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}
}
