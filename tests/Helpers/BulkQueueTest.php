<?php
namespace BavarianRankEngine\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use BavarianRankEngine\Helpers\BulkQueue;

class BulkQueueTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['bre_transients'] = [];
    }

    public function test_is_locked_false_initially(): void {
        $this->assertFalse( BulkQueue::isLocked() );
    }

    public function test_acquire_sets_lock(): void {
        $this->assertTrue( BulkQueue::acquire() );
        $this->assertTrue( BulkQueue::isLocked() );
    }

    public function test_acquire_fails_when_already_locked(): void {
        BulkQueue::acquire();
        $this->assertFalse( BulkQueue::acquire() );
    }

    public function test_release_clears_lock(): void {
        BulkQueue::acquire();
        BulkQueue::release();
        $this->assertFalse( BulkQueue::isLocked() );
    }

    public function test_lock_age_is_zero_when_not_locked(): void {
        $this->assertSame( 0, BulkQueue::lockAge() );
    }

    public function test_lock_age_is_positive_after_acquire(): void {
        BulkQueue::acquire();
        $this->assertGreaterThanOrEqual( 0, BulkQueue::lockAge() );
    }
}
