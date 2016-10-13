<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\ConfigCollection;
use micmania1\config\ConfigItem;
use micmania1\config\ConfigItemInterface;

class ConfigCollectionTest extends TestCase
{
    private $prophet;

    protected function setUp()
    {
        $this->prophet = new \Prophecy\Prophet;
    }

    public function testCollection()
    {
        $collection = new ConfigCollection;

        $item = $this->createMockItem('test');
        $collection->set('test', $item);

        $this->assertTrue($collection->exists('test'));
        $this->assertInstanceOf(ConfigItemInterface::class, $collection->get('test'));
        $this->assertEquals(['test'], $collection->keys());
        $this->assertCount(1, $collection->all());

        $item2 = $this->createMockItem('test2');
        $collection->set('test2', $item2);

        $this->assertTrue($collection->exists('test2'));
        $this->assertInstanceOf(ConfigItemInterface::class, $collection->get('test2'));
        $this->assertEquals(['test', 'test2'], $collection->keys());
        $this->assertCount(2, $collection->all());

        $collection->clear('test');
        $this->assertFalse($collection->exists('test'));
        $this->assertNull($collection->get('test'));
        $this->assertCount(1, $collection->all());
    }

    private function createMockItem($value, $metaData = [])
    {
        $item = $this->prophet->prophesize(ConfigItemInterface::class);

        // Expected method calls
        $item->getValue()->willReturn($value);
        $item->getMetaData()->willReturn($metaData);
        $item->set($value, $metaData)->willReturn(null);

        return $item->reveal();
    }
}
