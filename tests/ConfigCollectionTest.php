<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\ConfigCollection;

class ConfigCollectionTest extends TestCase
{
    public function testSettingAndGettingValues()
    {
        $collection = new ConfigCollection();

        $collection->set('test1', 'value1');
        $collection->set('test2', ['test2' => 'test2']);

        $this->assertTrue($collection->hasKey('test1'));
        $this->assertEquals($collection->getValue('test1'), 'value1');
        $this->assertEquals($collection->getValue('test2'), ['test2' => 'test2']);

        // Test a key which doesn't exist
        $this->assertFalse($collection->hasKey('none'));
        $this->assertNull($collection->getValue('none'));

        // Test getValues returns the correct keys
        $this->assertEquals($collection->getKeys(), ['test1', 'test2']);
    }


    public function testGetAll()
    {
        $collection = new ConfigCollection;

        $metaData = ['filename' => 'filename1'];
        $collection->set('test1', 'value1', $metaData);

        $this->assertEquals($collection->getMetaData('test1'), $metaData);

        // Test everything
        $all = ['value' => 'value1', 'metadata' => $metaData, 'history' => []];
        $this->assertEquals($collection->get('test1'), $all);

        // Test something that doesn't exist
        $this->assertNull($collection->getMetaData('none'));
        $this->assertNull($collection->get('none'));
    }

    public function testClear()
    {
        $collection = new ConfigCollection;

        $collection->set('test1', 'value1');
        $this->assertTrue($collection->hasKey('test1'));

        $collection->clear('test1');
        $this->assertFalse($collection->hasKey('test1'));
    }

    public function testHistory()
    {
        $collection = new ConfigCollection;

        $collection->set('test1', 'value1');

        // History should be empty on the first run
        $this->assertEquals($collection->getHistory('test1'), []);

        // Now we set the same key again and the history changes.
        $collection->set('test1', 'value2');
        $history = [
            ['value' => 'value1', 'metadata' => []]
        ];
        $this->assertEquals($collection->getHistory('test1'), $history);
    }

}
