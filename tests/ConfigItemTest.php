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
        $this->assertEquals([], $item->getMetaData());

        // Test array value with meta data
        $item = new ConfigItem(['key' => 'val'], ['metakey' => 'metaval']);
        $this->assertEquals(['key' => 'val'], $item->getValue());
        $this->assertEquals(['metakey' => 'metaval'], $item->getMetaData());

        // Test history
        $item = new ConfigItem('value');
        $this->assertEquals('value', $item->getValue());

        // Make a copy of the current value, change it then check that history is correct
        $historicalItem = new ConfigItem($item->getValue(), $item->getMetaData());
        $item->set('value2');
        $this->assertEquals('value2', $item->getValue());
        $this->assertEquals([$historicalItem], $item->getHistory());

        // To ensure getHistory works as it should, we'll ensure it fails also
        $falseItem = new ConfigItem('value1');
        $this->assertNotEquals([$falseItem], $item->getHistory());
    }
}
