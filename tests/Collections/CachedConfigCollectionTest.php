<?php

namespace SilverStripe\Config\Tests\Collections;

use BadMethodCallException;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Config\Collections\CachedConfigCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use SilverStripe\Config\Collections\ConfigCollectionInterface;

class CachedConfigCollectionTest extends TestCase
{
    /**
     * @var Prophet
     */
    protected $prophet;

    protected function setUp()
    {
        $this->prophet = new Prophet;
    }

    protected function tearDown()
    {
        $this->prophet->checkPredictions();
    }

    /**
     * Test loading from cache
     */
    public function testCacheHit()
    {
        /** @var CacheInterface|ObjectProphecy $mockCache */
        $mockCache = $this->prophet->prophesize(CacheInterface::class);

        /** @var ConfigCollectionInterface|ObjectProphecy $mockCollection */
        $mockCollection = $this->prophet->prophesize(ConfigCollectionInterface::class);
        $mockCollection->get('test', 'name', 0)->willReturn('value');
        $mockCollection->exists('test', 'name', 0)->willReturn(true);

        // Get will return hit
        $mockCache
            ->get(CachedConfigCollection::CACHE_KEY)
            ->willReturn($mockCollection->reveal())
            ->shouldBeCalled();

        // Set called in __destruct
        $mockCache
            ->set(CachedConfigCollection::CACHE_KEY, $mockCollection->reveal())
            ->shouldBeCalledTimes(1);

        $collection = new CachedConfigCollection();
        $collection->setCollectionCreator(function () {
            $this->fail("Invalid cache miss");
        });
        $collection->setCache($mockCache->reveal());

        // Check
        $this->assertTrue($collection->exists('test', 'name'));
        $this->assertEquals('value', $collection->get('test', 'name'));

        // Write back changes to cache
        $collection->__destruct();
    }

    public function testCacheMiss()
    {
        /** @var CacheInterface|ObjectProphecy $mockCache */
        $mockCache = $this->prophet->prophesize(CacheInterface::class);

        // Miss
        $mockCache
            ->get(CachedConfigCollection::CACHE_KEY)
            ->willReturn(null)
            ->shouldBeCalled();

        /** @var ConfigCollectionInterface|ObjectProphecy $mockCollection */
        $mockCollection = $this->prophet->prophesize(ConfigCollectionInterface::class);
        $mockCollection->get('test', 'name', 0)->willReturn('value');
        $mockCollection->exists('test', 'name', 0)->willReturn(true);

        // Cache will be generated, saved, and then saved again on __destruct()
        $mockCache
            ->set(CachedConfigCollection::CACHE_KEY, $mockCollection->reveal())
            ->shouldBeCalledTimes(2);

        $collection = new CachedConfigCollection();
        $collection->setCollectionCreator(function () use ($mockCollection) {
            return $mockCollection->reveal();
        });
        $collection->setCache($mockCache->reveal());

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
        /** @var CacheInterface|ObjectProphecy $mockCache */
        $mockCache = $this->prophet->prophesize(CacheInterface::class);
        $mockCache
            ->get(CachedConfigCollection::CACHE_KEY)
            ->willReturn(null)
            ->shouldBeCalled();

        // Build new config
        $collection = new CachedConfigCollection();
        $collection->setCache($mockCache->reveal());
        $collection->setCollectionCreator(function () use ($collection) {
            $collection->getCollection();
        });
        $collection->getCollection();
        $this->fail("Expected exception");
    }
}
