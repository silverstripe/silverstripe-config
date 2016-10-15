<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\ConfigCollection;
use micmania1\config\ConfigItem;
use Prophecy\Prophet;

class ConfigCollectionTest extends TestCase
{
    private $prophet;

    protected function setUp()
    {
        $this->prophet = new Prophet;
    }

    public function testCollection()
    {
        $collection = new ConfigCollection;

        $item = new ConfigItem('value');
        $collection->set('test', $item);

        $this->assertTrue($collection->exists('test'));
        $this->assertEquals(['test'], $collection->keys());
        $this->assertCount(1, $collection->all());

        $item2 = new ConfigItem('value');
        $collection->set('test2', $item2);

        $this->assertTrue($collection->exists('test2'));
        $this->assertEquals(['test', 'test2'], $collection->keys());
        $this->assertCount(2, $collection->all());

        $collection->clear('test');
        $this->assertFalse($collection->exists('test'));
        $this->assertNull($collection->get('test'));
        $this->assertCount(1, $collection->all());
    }

}
