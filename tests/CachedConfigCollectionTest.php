<?php

use micmania1\config\CachedConfigCollection;
use micmania1\config\ConfigCollection;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

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

    public function testGetSetAndDelete()
    {
        $mockCache = $this->prophet->prophesize(CacheItemPoolInterface::class);
        $mockCache->commit()->shouldBeCalled();

        // Mock item 1 for 'test' key
        $mockCacheItem = $this->prophet->prophesize(CacheItemInterface::class);
        $mockCacheItem->get()->willReturn('value');
        $mockCacheItem->set('value')->shouldBeCalled();
        $mockCache->getItem('test')->willReturn($mockCacheItem->reveal());
        $mockCache->saveDeferred($mockCacheItem->reveal())->shouldBeCalled();
        $mockCache->hasItem('test')->willReturn(true);
        $mockCache->hasItem('test')->shouldBeCalled();
        $mockCache->deleteItem('test')->shouldBeCalled();

        $collection = new CachedConfigCollection($mockCache->reveal());
        $collection->set('test', 'value');
        $this->assertTrue($collection->exists('test'));
        $this->assertEquals('value', $collection->get('test'));

        // Mock item 2 for 'test2' key
        $mockCacheItem2 = $this->prophet->prophesize(CacheItemInterface::class);
        $mockCache->getItem('test2')->willReturn($mockCacheItem2->reveal());
        $mockCache->saveDeferred($mockCacheItem2->reveal())->shouldBeCalled();
        $mockCache->hasItem('test2')->willReturn(true);
        $mockCache->hasItem('test2')->shouldBeCalled();

        $collection->set('test2', 'value');
        $this->assertTrue($collection->exists('test2'));

        // We've deled 'test' so the cache should reflect that
        $mockCache->hasItem('test')->willReturn(false);
        $mockCache->getItem('test')->willReturn(null);

        // Test that our exists and get methods function as expected when no keys exists
        $collection->delete('test');
        $this->assertFalse($collection->exists('test'));
        $this->assertNull($collection->get('test'));
    }

    public function testNoMetadataTracking()
    {
        $mockCache = $this->prophet->prophesize(CacheItemPoolInterface::class);
        $mockCache->commit()->shouldBeCalled();

        $mockCacheItem = $this->prophet->prophesize(CacheItemInterface::class);
        $mockCache->getItem('key1')->willReturn($mockCacheItem->reveal());
        $mockCache->saveDeferred($mockCacheItem->reveal())->shouldBeCalled();

        // Because we're not tracking metadata, we should never call the cache when
        // looking up history or metadata
        $mockCache->getItem(CachedConfigCollection::METADATA_KEY)
            ->shouldNotBeCalled();
        $mockCache->getItem(CachedConfigCollection::HISTORY_KEY)
            ->shouldNotBeCalled();

        // metadata should be turned off by default
        $collection = new CachedConfigCollection($mockCache->reveal());
        $collection->set('key1', 'value1');
        $this->assertEquals([], $collection->getMetadata());
        $this->assertEquals([], $collection->getHistory());

        // We update the value and check that history is still empty
        $collection->set('key1', 'value2');
        $this->assertEquals([], $collection->getMetadata());
        $this->assertEquals([], $collection->getHistory());
    }

    public function testMetadataAndHistoryTracking()
    {
        $mockCache = $this->prophet->prophesize(CacheItemPoolInterface::class);
        $mockCache->commit()->shouldBeCalled();

        // Setup metadata cache item
        $mockMetadataItem = $this->prophet->prophesize(CacheItemInterface::class);
        $mockMetadataItem->get()->willReturn([]);
        // We expect it to look in the cache when getMetadata is called
        $mockCache->getItem(CachedConfigCollection::METADATA_KEY)
            ->willReturn($mockMetadataItem->reveal())
            ->shouldBeCalled();

        // Setup history cache item
        $mockHistoryCacheItem = $this->prophet->prophesize(CacheItemInterface::class);
        $mockHistoryCacheItem->get()->willReturn([]);
        // We expect it to look in the cache when getHistory is called
        $mockCache->getItem(CachedConfigCollection::HISTORY_KEY)
            ->willReturn($mockHistoryCacheItem->reveal())
            ->shouldBeCalled();

        // Create a new instance of cached config collection. We'll test the getMetadata
        // and getHistory methods to assert all of the above is true.
        $collection = new CachedConfigCollection($mockCache->reveal(), true);
        $this->assertEquals([], $collection->getMetadata());
        $this->assertEquals([], $collection->getHistory());

        // Create a mock item for a value
        $mockItem = $this->prophet->prophesize(CacheItemInterface::class);

        // Expectations of the cache pool
        $mockCache->getItem('key1')->willReturn($mockItem->reveal())->shouldBeCalled();
        $mockCache->hasItem('key1')->willReturn(true)->shouldBeCalled();
        $mockCache->saveDeferred($mockItem->reveal())->shouldBeCalled();

        // Expectations around updating meta data
        $mockMetadataItem->set(['key1' => ['metakey' => 'metavalue']])->shouldBeCalled();
        $mockCache->saveDeferred($mockMetadataItem->reveal())->shouldBeCalled();

        // Expectations around updating history. As its the first update, it should be empty.
        $mockHistoryCacheItem->set([])->shouldBeCalled();
        $mockCache->saveDeferred($mockHistoryCacheItem->reveal())->shouldBeCalled();

        // Perform the action
        $collection->set('key1', 'value', ['metakey' => 'metavalue']);
    }

    public function testDeleteAll()
    {
        $mockCache = $this->prophet->prophesize(CacheItemPoolInterface::class);
        $collection = new CachedConfigCollection($mockCache->reveal());

        $mockCache->commit()->shouldBeCalled();
        $mockCache->clear()->shouldBeCalled();
        $collection->deleteAll();
    }
}
