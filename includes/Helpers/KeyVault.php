<?php
namespace BavarianRankEngine\Helpers;

/**
 * Obfuscates API keys for database storage using XOR with a derived WP-salt key.
 *
 * No OpenSSL or other PHP extensions required — only core string functions.
 * Keys stored as "bre1:<base64(xor(plaintext, salt))>".
 *
 * Note: XOR with a static salt is obfuscation, not encryption. It prevents
 * plain-text keys from appearing in database backups or export files, but
 * does not protect against an attacker with access to both the database
 * AND the wp-config.php salts. For stronger protection, users can define
 * BRE_<PROVIDER>_KEY constants in wp-config.php and leave the DB field empty.
 */
class KeyVault {
    private const PREFIX = 'bre1:';

    /**
     * Obfuscate a plain API key for database storage.
     */
    public static function encrypt( string $key ): string {
        if ( $key === '' ) {
            return '';
        }
        return self::PREFIX . base64_encode( self::xor( $key, self::salt() ) );
    }

    /**
     * Recover the plain API key from a stored obfuscated value.
     * Returns empty string if the stored value is not in bre1: format (legacy/invalid).
     */
    public static function decrypt( string $stored ): string {
        if ( $stored === '' ) {
            return '';
        }
        if ( ! str_starts_with( $stored, self::PREFIX ) ) {
            // Legacy OpenSSL-encrypted value or unknown format — return empty so user re-enters.
            return '';
        }
        $raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
        if ( $raw === false ) {
            return '';
        }
        return self::xor( $raw, self::salt() );
    }

    /**
     * Returns masked version for display: ••••••Ab3c9
     */
    public static function mask( string $plain ): string {
        if ( $plain === '' ) {
            return '';
        }
        return str_repeat( '•', max( 0, mb_strlen( $plain ) - 5 ) ) . mb_substr( $plain, -5 );
    }

    /**
     * XOR each byte of $data with the corresponding byte of $key (wrapping).
     */
    private static function xor( string $data, string $key ): string {
        $out    = '';
        $keyLen = strlen( $key );
        for ( $i = 0, $n = strlen( $data ); $i < $n; $i++ ) {
            $out .= $data[ $i ] ^ $key[ $i % $keyLen ];
        }
        return $out;
    }

    /**
     * Derives a 64-character hex salt from WP's AUTH_KEY and SECURE_AUTH_KEY.
     * Falls back to known strings if the constants are not defined (local dev / unit tests).
     */
    private static function salt(): string {
        $a = defined( 'AUTH_KEY' )        ? AUTH_KEY        : 'bre-fallback-a';
        $b = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'bre-fallback-b';
        return hash( 'sha256', $a . $b ); // 64 hex chars, no extension needed
    }
}
