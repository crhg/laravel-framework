<?php

namespace Illuminate\Tests\Integration\Cache;

use Illuminate\Support\Carbon;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;

/**
 * @group integration
 */
class RedisCacheLockTest extends TestCase
{
    use InteractsWithRedis;

    public function setUp()
    {
        parent::setUp();

        $this->setUpRedis();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->tearDownRedis();
    }

    public function test_redis_locks_can_be_acquired_and_released()
    {
        Cache::store('redis')->lock('foo')->forceRelease();

        $lock = Cache::store('redis')->lock('foo', 10);
        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::store('redis')->lock('foo', 10)->get());
        $lock->release();

        $lock = Cache::store('redis')->lock('foo', 10);
        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::store('redis')->lock('foo', 10)->get());
        Cache::store('redis')->lock('foo')->release();
    }

    public function test_redis_locks_can_block_for_seconds()
    {
        Carbon::setTestNow();

        Cache::store('redis')->lock('foo')->forceRelease();
        $this->assertEquals('taylor', Cache::store('redis')->lock('foo', 10)->block(1, function () {
            return 'taylor';
        }));

        Cache::store('redis')->lock('foo')->forceRelease();
        $this->assertTrue(Cache::store('redis')->lock('foo', 10)->block(1));
    }

    public function test_concurrent_redis_locks_are_released_safely()
    {
        Cache::store('redis')->lock('foo')->forceRelease();

        $firstLock = Cache::store('redis')->lock('foo', 1);
        $this->assertTrue($firstLock->get());
        sleep(2);

        $secondLock = Cache::store('redis')->lock('foo', 10);
        $this->assertTrue($secondLock->get());

        $firstLock->release();

        $this->assertFalse(Cache::store('redis')->lock('foo')->get());
    }

    public function test_redis_locks_can_be_released_using_owner_token()
    {
        Cache::store('redis')->lock('foo')->forceRelease();

        $firstLock = Cache::store('redis')->lock('foo', 10);
        $this->assertTrue($firstLock->get());
        $owner = $firstLock->owner();

        $secondLock = Cache::store('redis')->restoreLock('foo', $owner);
        $secondLock->release();

        $this->assertTrue(Cache::store('redis')->lock('foo')->get());
    }
}
