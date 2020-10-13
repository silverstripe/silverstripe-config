<?php

namespace SilverStripe\Config\Tests\Collections;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Config\Collections\CachedConfigCollection;
use SilverStripe\Config\Collections\ConfigCollectionInterface;

class CachedConfigCollectionTest extends TestCase
{
    /**
     * Test loading from cache
     */
    public function testCacheHit()
    {
        $mockCache = $this->getMockBuilder(CacheInterface::class)->getMock();

        $mockCollection = $this->getMockBuilder(ConfigCollectionInterface::class)->getMock();
        $mockCollection
            ->expects($this->once())
            ->method('get')
            ->with('test', 'name', 0)
            ->willReturn('value');
        $mockCollection
            ->expects($this->once())
            ->method('exists')
            ->with('test', 'name', 0)
            ->willReturn(true);

        // Get will return hit
        $mockCache
            ->expects($this->once())
            ->method('get')
            ->with(CachedConfigCollection::CACHE_KEY)
            ->willReturn($mockCollection);

        // Set called in __destruct
        $mockCache
            ->expects($this->once())
            ->method('set')
            ->with(CachedConfigCollection::CACHE_KEY, $mockCollection);

        $collection = new CachedConfigCollection();
        $collection->setCollectionCreator(function () {
            $this->fail("Invalid cache miss");
        });
        $collection->setCache($mockCache);

        // Check
        $this->assertTrue($collection->exists('test', 'name'));
        $this->assertEquals('value', $collection->get('test', 'name'));

        // Write back changes to cache
        $collection->__destruct();
    }

    public function testCacheMiss()
    {
        $mockCache = $this->getMockBuilder(CacheInterface::class)->getMock();

        $mockCache
            ->expects($this->once())
            ->method('get')
            ->with(CachedConfigCollection::CACHE_KEY)
            ->willReturn(null);

        $mockCollection = $this->getMockBuilder(ConfigCollectionInterface::class)->getMock();

        $mockCollection
            ->expects($this->atLeastOnce())
            ->method('get')
            ->with('test', 'name', 0)
            ->willReturn('value');

        $mockCollection
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->with('test', 'name', 0)
            ->willReturn(true);

        // Cache will be generated, saved, and then saved again on __destruct()
        $mockCache
            ->expects($this->exactly(2))
            ->method('set')
            ->with(CachedConfigCollection::CACHE_KEY, $mockCollection);

        $collection = new CachedConfigCollection();
        $collection->setCollectionCreator(function () use ($mockCollection) {
            return $mockCollection;
        });
        $collection->setCache($mockCache);

        // Check
        $this->assertTrue($collection->exists('test', 'name'));
        $this->assertEquals('value', $collection->get('test', 'name'));

        // Write back changes to cache
        $collection->__destruct();
    }

    public function testInfiniteLoop()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage("Infinite loop detected. Config could not be bootstrapped.");

        // Mock cache
        $mockCache = $this->getMockBuilder(CacheInterface::class)->getMock();
        $mockCache
            ->expects($this->any())
            ->method('get')
            ->with(CachedConfigCollection::CACHE_KEY)
            ->willReturn(null);

        // Build new config
        $collection = new CachedConfigCollection();
        $collection->setCache($mockCache);
        $collection->setCollectionCreator(function () use ($collection) {
            $collection->getCollection();
        });
        $collection->getCollection();
        $this->fail("Expected exception");
    }
}
