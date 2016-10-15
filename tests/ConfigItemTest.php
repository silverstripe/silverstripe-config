<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\ConfigItem;

class ConfigItemTest extends TestCase
{
    public function testConfigItem()
    {
        // Test string value
        $item = new ConfigItem('test');
        $this->assertEquals('test', $item->getValue());
        $this->assertEquals([], $item->getMetadata());
    }

    public function testMetaData()
    {
        // Test array value with meta data
        $item = new ConfigItem(['key' => 'val'], ['metakey' => 'metaval'], true);
        $this->assertEquals(['key' => 'val'], $item->getValue());
        $this->assertEquals(['metakey' => 'metaval'], $item->getMetadata());

        // Ensure we don't track by default
        $item = new ConfigItem('test');
        $this->assertEquals('test', $item->getValue());
        $this->assertEquals([], $item->getMetadata());
    }

    public function testHistory()
    {
        // Test history
        $item = new ConfigItem('value', ['filename' => 'test'], true);
        $this->assertEquals('value', $item->getValue());

        // Make a copy of the current value, change it then check that history is correct
        $historicalItem1 = new ConfigItem($item->getValue(), $item->getMetadata(), true);
        $item->set('value2');
        $this->assertEquals('value2', $item->getValue());
        $this->assertEquals([$historicalItem1], $item->getHistory());

        $historicalItem2 = new ConfigItem($item->getValue(), $item->getMetadata(), true);
        $item->set('value3');
        $this->assertEquals('value3', $item->getValue());
        $this->assertEquals([$historicalItem2, $historicalItem1], $item->getHistory());

        // To ensure getHistory works as it should, we'll ensure it fails also
        $falseItem = new ConfigItem('value1');
        $this->assertNotEquals([$falseItem], $item->getHistory());

        // Ensure we don't track history by default
        $item = new ConfigItem('test');
        $item->set('test2');
        $this->assertEquals([], $item->getHistory());
    }
}
