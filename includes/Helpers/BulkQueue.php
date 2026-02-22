<?php
namespace BavarianRankEngine\Helpers;

class BulkQueue {
	private const LOCK_KEY = 'bre_bulk_running';
	private const LOCK_TTL = 900; // 15 minutes

	public static function acquire(): bool {
		if ( self::isLocked() ) {
			return false;
		}
		set_transient( self::LOCK_KEY, time(), self::LOCK_TTL );
		return true;
	}

	public static function release(): void {
		delete_transient( self::LOCK_KEY );
	}

	public static function isLocked(): bool {
		return get_transient( self::LOCK_KEY ) !== false;
	}

	public static function lockAge(): int {
		$started = get_transient( self::LOCK_KEY );
		return $started !== false ? ( time() - (int) $started ) : 0;
	}
}
