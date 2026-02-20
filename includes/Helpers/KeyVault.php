<?php
namespace BavarianRankEngine\Helpers;

class KeyVault {
    private const CIPHER = 'AES-256-CBC';

    public static function encrypt( string $key ): string {
        if ( empty( $key ) ) return '';
        $iv     = random_bytes( 16 );
        $cipher = openssl_encrypt( $key, self::CIPHER, self::derivedKey(), OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }

    public static function decrypt( string $stored ): string {
        if ( empty( $stored ) ) return '';
        $raw = base64_decode( $stored, true );
        if ( $raw === false || strlen( $raw ) < 17 ) return '';
        $plain = openssl_decrypt( substr( $raw, 16 ), self::CIPHER, self::derivedKey(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
        return $plain !== false ? $plain : '';
    }

    /** Returns last 5 chars prefixed with bullets, e.g. "••••••••••Ab3c9" */
    public static function mask( string $plain ): string {
        if ( empty( $plain ) ) return '';
        return str_repeat( '•', max( 0, mb_strlen( $plain ) - 5 ) ) . mb_substr( $plain, -5 );
    }

    private static function derivedKey(): string {
        $a = defined( 'AUTH_KEY' )        ? AUTH_KEY        : 'seo-geo-a';
        $b = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'seo-geo-b';
        return hash( 'sha256', $a . $b, true ); // 32 bytes → AES-256
    }
}
