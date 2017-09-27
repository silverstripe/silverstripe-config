<?php

namespace SilverStripe\Config\Tests\Collections;

use SilverStripe\Config\Collections\MemoryConfigCollection;
use PHPUnit\Framework\TestCase;

class MemoryConfigCollectionTest extends TestCase
{
    public function testGetSetAndDelete()
    {
        $collection = new MemoryConfigCollection;

        $collection->set('test', null, 'value');
        $this->assertTrue($collection->exists('test'));

        $collection->set('test2', null, 'value');
        $this->assertTrue($collection->exists('test2'));

        $collection->remove('test');
        $this->assertFalse($collection->exists('test'));
        $this->assertEquals([], $collection->get('test'));

        $collection->removeAll();
        $this->assertFalse($collection->exists('test2'));
    }

    public function testNoMetadataTracking()
    {
        // metadata should be turned off by default
        $collection = new MemoryConfigCollection;
        $collection->set('key1', null, 'value1');

        $this->assertEquals('value1', $collection->get('key1'));
        $this->assertEquals([], $collection->getMetadata());
        $this->assertEquals([], $collection->getHistory());

        // We update the value and check that history is still empty
        $collection->set('key1', null, 'value2');
        $this->assertEquals('value2', $collection->get('key1'));
        $this->assertEquals([], $collection->getMetadata());
        $this->assertEquals([], $collection->getHistory());
    }

    public function testMetadataAndHistoryTracking()
    {
        $collection = new MemoryConfigCollection(true);

        $collection->set('key1', null, 'value1', ['metakey' => 'metavalue1']);
        $this->assertEquals('value1', $collection->get('key1'));
        $this->assertEquals(
            ['key1' => ['metakey' => 'metavalue1']],
            $collection->getMetadata()
        );
        $this->assertEquals([], $collection->getHistory());

        $collection->set('key1', null, 'value2', ['metakey' => 'metavalue2']);
        $this->assertEquals('value2', $collection->get('key1'));
        $this->assertEquals(
            ['key1' => ['metakey' => 'metavalue2']],
            $collection->getMetadata()
        );
        $this->assertEquals(
            [
                'key1' => [
                    0 => [
                        'value' => 'value1',
                        'metadata' => ['metakey' => 'metavalue1'],
                    ]
                ]
            ],
            $collection->getHistory()
        );

        $collection->set('key1', null, 'value3', ['metakey' => 'metavalue3']);
        $this->assertEquals(
            [
                'key1' => [
                    0 => [
                        'value' => 'value2',
                        'metadata' => ['metakey' => 'metavalue2'],
                    ],
                    1 => [
                        'value' => 'value1',
                        'metadata' => ['metakey' => 'metavalue1'],
                    ]
                ]
            ],
            $collection->getHistory()
        );
    }
}
