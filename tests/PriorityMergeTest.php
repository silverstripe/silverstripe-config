<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\MergeStrategy\Priority;
use micmania1\config\ConfigCollection;
use micmania1\config\ConfigItem;
use Prophecy\Prophet;
use Prophecy\Argument;

class PriorityTest extends TestCase
{
    /**
     * @var Priorty
     */
    protected $strategy;

    protected function setUp()
    {
        $this->strategy = new Priority;
    }

    public function testMergeSingleItem()
    {
        $myItem = new ConfigItem('importantvalue');
        $myCollection = new ConfigCollection(['key1' => $myItem]);

        $theirItem = new ConfigItem('lessimportantvalue');
        $theirCollection = new ConfigCollection(['key1' => $theirItem]);

        $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals('importantvalue', $theirCollection->get('key1')->getValue());
    }

    public function testMergeSingleArray()
    {
        $myItem = new ConfigItem(['array item 1']);
        $myCollection = new ConfigCollection(['key1' => $myItem]);

        $theirItem = new ConfigItem(['array item 2']);
        $theirCollection = new ConfigCollection(['key1' => $theirItem]);

        $this->strategy->merge($myCollection, $theirCollection);
        $expectedValue = ['array item 2', 'array item 1'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1')->getValue());
    }

    public function testMergeArrayByKey()
    {
        $myItem = new ConfigItem(['arrKey1' => 'val1', 'arrKey2' => 'val2']);
        $myCollection = new ConfigCollection(['key1' => $myItem]);

        $theirItem = new ConfigItem(['arrKey1' => 'lessimportant', 'arrKey3' => 'val3']);
        $theirCollection = new ConfigCollection(['key1' => $theirItem]);

        $this->strategy->merge($myCollection, $theirCollection);
        $expectedValue = ['arrKey1' => 'val1', 'arrKey2' => 'val2', 'arrKey3' => 'val3'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1')->getValue());
    }


    public function testMergeArrayToString()
    {
        $myItem = new ConfigItem(['array item 1']);
        $myCollection = new ConfigCollection(['key1' => $myItem]);

        $theirItem = new ConfigItem('my string');
        $theirCollection = new ConfigCollection(['key1' => $theirItem]);

        // We should see the array unchanged as it has higher priority than the string
        $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals(['array item 1'], $theirCollection->get('key1')->getValue());
    }

    public function testMergeStringToArray()
    {
        $myItem = new ConfigItem('my string');
        $myCollection = new ConfigCollection(['key1' => $myItem]);

        $theirItem = new ConfigItem(['array item 1']);
        $theirCollection = new ConfigCollection(['key1' => $theirItem]);

        // We should see the string untouched as its higher priority.
        $theirCollection = $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals('my string', $theirCollection->get('key1')->getValue());
    }
}
