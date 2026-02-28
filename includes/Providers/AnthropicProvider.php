<?php
namespace BavarianRankEngine\Providers;

class AnthropicProvider implements ProviderInterface {
	private const API_URL = 'https://api.anthropic.com/v1/messages';

	public function getId(): string {
		return 'anthropic'; }
	public function getName(): string {
		return 'Anthropic (Claude)'; }

	public function getModels(): array {
		return array(
			'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (' . __( 'Recommended', 'bavarian-rank-engine' ) . ')',
			'claude-opus-4-6'           => 'Claude Opus 4.6 (' . __( 'Powerful', 'bavarian-rank-engine' ) . ')',
			'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (' . __( 'Fast & cheap', 'bavarian-rank-engine' ) . ')',
		);
	}

	public function testConnection( string $api_key ): array {
		try {
			$this->generateText( 'Say "ok"', $api_key, 'claude-haiku-4-5-20251001', 5 );
			return array(
				'success' => true,
				'message' => __( 'Connection successful', 'bavarian-rank-engine' ),
			);
		} catch ( \RuntimeException $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	public function generateText( string $prompt, string $api_key, string $model, int $max_tokens = 300 ): string {
		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $model,
						'max_tokens' => $max_tokens,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
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

		return trim( $body['content'][0]['text'] ?? '' );
	}
}
