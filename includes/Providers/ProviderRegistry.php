<?php
namespace BavarianRankEngine;

use BavarianRankEngine\Providers\ProviderInterface;

class ProviderRegistry {
	private static ?ProviderRegistry $instance = null;
	private array $providers                   = array();

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register( ProviderInterface $provider ): void {
		$this->providers[ $provider->getId() ] = $provider;
	}

	public function get( string $id ): ?ProviderInterface {
		return $this->providers[ $id ] ?? null;
	}

	/** @return ProviderInterface[] */
	public function all(): array {
		return $this->providers;
	}

	/** Reset singleton — for use in tests only */
	public static function reset(): void {
		self::$instance = null;
	}

	/** Returns ['id' => 'Name'] for dropdowns */
	public function getSelectOptions(): array {
		$options = array();
		foreach ( $this->providers as $id => $provider ) {
			$options[ $id ] = $provider->getName();
		}
		return $options;
	}
}
