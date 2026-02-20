<?php
namespace BavarianRankEngine\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Helpers\KeyVault;

class KeyVaultTest extends TestCase {

    public function test_encrypt_returns_bre1_prefix(): void {
        $encrypted = KeyVault::encrypt( 'sk-test123' );
        $this->assertStringStartsWith( 'bre1:', $encrypted );
    }

    public function test_encrypt_decrypt_roundtrip(): void {
        $original  = 'sk-proj-AbCdEf123456';
        $encrypted = KeyVault::encrypt( $original );
        $decrypted = KeyVault::decrypt( $encrypted );
        $this->assertSame( $original, $decrypted );
    }

    public function test_encrypt_empty_returns_empty(): void {
        $this->assertSame( '', KeyVault::encrypt( '' ) );
    }

    public function test_decrypt_empty_returns_empty(): void {
        $this->assertSame( '', KeyVault::decrypt( '' ) );
    }

    public function test_decrypt_legacy_openssl_returns_empty(): void {
        // A legacy OpenSSL-encrypted value (no bre1: prefix) → return '' so user re-enters key.
        $legacy = base64_encode( str_repeat( 'x', 48 ) ); // looks like old format
        $this->assertSame( '', KeyVault::decrypt( $legacy ) );
    }

    public function test_decrypt_invalid_base64_returns_empty(): void {
        $this->assertSame( '', KeyVault::decrypt( 'bre1:not-valid-base64!!!' ) );
    }

    public function test_mask_shows_last_five_chars(): void {
        $masked = KeyVault::mask( 'sk-AbCdEfGhIj' );
        $this->assertStringEndsWith( 'hIj', $masked ); // last 3 of 5 visible
        $this->assertStringContainsString( '•', $masked );
    }

    public function test_mask_empty_returns_empty(): void {
        $this->assertSame( '', KeyVault::mask( '' ) );
    }

    public function test_encrypted_value_is_not_plaintext(): void {
        $key       = 'sk-super-secret-key';
        $encrypted = KeyVault::encrypt( $key );
        $this->assertStringNotContainsString( $key, $encrypted );
    }

    public function test_different_inputs_produce_different_output(): void {
        $a = KeyVault::encrypt( 'key-aaa' );
        $b = KeyVault::encrypt( 'key-bbb' );
        $this->assertNotSame( $a, $b );
    }
}
