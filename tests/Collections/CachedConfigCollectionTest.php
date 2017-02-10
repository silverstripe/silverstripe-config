<?php

namespace SilverStripe\Config\Tests\Collections;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use SilverStripe\Config\Collections\CachedConfigCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use SilverStripe\Config\Collections\ConfigCollectionInterface;
use SilverStripe\Config\Collections\MemoryConfigCollection;

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
        /** @var CacheItemPoolInterface|ObjectProphecy $mockCache */
        $mockCache = $this->prophet->prophesize(CacheItemPoolInterface::class);

        /** @var ConfigCollectionInterface|ObjectProphecy $mockCollection */
        $mockCollection = $this->prophet->prophesize(ConfigCollectionInterface::class);
        $mockCollection->get('test', 'name', 0)->willReturn('value');
        $mockCollection->exists('test', 'name', 0)->willReturn(true);

        // Mock item for collection key
        /** @var CacheItemInterface|ObjectProphecy $mockCacheItem */
        $mockCacheItem = $this->prophet->prophesize(CacheItemInterface::class);
        $mockCacheItem
            ->get()
            ->willReturn($mockCollection->reveal())
            ->shouldBeCalled();
        $mockCacheItem
            ->isHit()
            ->willReturn(true)
            ->shouldBeCalled();
        $mockCacheItem->set($mockCollection->reveal())->shouldBeCalled();

        $mockCache
            ->getItem(CachedConfigCollection::CACHE_KEY)
            ->willReturn($mockCacheItem->reveal())
            ->shouldBeCalled();

        // In __destruct cache is saved
        $mockCache->save($mockCacheItem->reveal())->shouldBeCalledTimes(1);

        $collection = new CachedConfigCollection();
        $collection->setCollectionCreator(function(){
            $this->fail("Invalid cache miss");
        });
        $collection->setPool($mockCache->reveal());

        // Check
        $this->assertTrue($collection->exists('test', 'name'));
        $this->assertEquals('value', $collection->get('test', 'name'));

        // Write back changes to cache
        $collection->__destruct();
    }

    public function testCacheMiss()
    {
        /** @var CacheItemPoolInterface|ObjectProphecy $mockCache */
        $mockCache = $this->prophet->prophesize(CacheItemPoolInterface::class);

        /** @var ConfigCollectionInterface|ObjectProphecy $mockCollection */
        $mockCollection = $this->prophet->prophesize(ConfigCollectionInterface::class);
        $mockCollection->get('test', 'name', 0)->willReturn('value');
        $mockCollection->exists('test', 'name', 0)->willReturn(true);

        // Mock item for collection key
        /** @var CacheItemInterface|ObjectProphecy $mockCacheItem */
        $mockCacheItem = $this->prophet->prophesize(CacheItemInterface::class);
        $mockCacheItem->get()->shouldNotBeCalled();
        $mockCacheItem
            ->isHit()
            ->willReturn(false)
            ->shouldBeCalled();

        // Save immediately, save again on __destruct
        $mockCacheItem->set($mockCollection->reveal())->shouldBeCalledTimes(2);

        $mockCache
            ->getItem(CachedConfigCollection::CACHE_KEY)
            ->willReturn($mockCacheItem->reveal())
            ->shouldBeCalled();

        // Save immediately after generating, save again on __destruct
        $mockCache->save($mockCacheItem->reveal())->shouldBeCalledTimes(2);

        $collection = new CachedConfigCollection();
        $collection->setCollectionCreator(function() use ($mockCollection) {
            return $mockCollection->reveal();
        });
        $collection->setPool($mockCache->reveal());

        // Check
        $this->assertTrue($collection->exists('test', 'name'));
        $this->assertEquals('value', $collection->get('test', 'name'));

        // Write back changes to cache
        $collection->__destruct();
    }
}
