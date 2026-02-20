<?php
namespace BavarianRankEngine\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Helpers\KeyVault;

class KeyVaultTest extends TestCase {
    public function test_encrypt_decrypt_roundtrip(): void {
        $original  = 'sk-test-abcdefghijklmnop1234567890';
        $encrypted = KeyVault::encrypt( $original );

        $this->assertNotEmpty( $encrypted );
        $this->assertNotEquals( $original, $encrypted );
        $this->assertEquals( $original, KeyVault::decrypt( $encrypted ) );
    }

    public function test_mask_shows_last_five_chars(): void {
        $key    = 'sk-abcdefg12345';
        $masked = KeyVault::mask( $key );

        $this->assertStringEndsWith( '12345', $masked );
        $this->assertStringContainsString( '•', $masked );
    }

    public function test_mask_empty_returns_empty(): void {
        $this->assertEquals( '', KeyVault::mask( '' ) );
    }

    public function test_decrypt_invalid_returns_empty(): void {
        $this->assertEquals( '', KeyVault::decrypt( 'notbase64!!!' ) );
    }

    public function test_decrypt_empty_returns_empty(): void {
        $this->assertEquals( '', KeyVault::decrypt( '' ) );
    }
}
