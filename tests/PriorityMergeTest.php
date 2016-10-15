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
        $myCollection = new ConfigCollection;
        $myCollection->set('key1', new ConfigItem('importantvalue'));

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', new ConfigItem('lessimportantvalue'));

        $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals('importantvalue', $theirCollection->get('key1')->getValue());
    }

    public function testMergeSingleArray()
    {
        $myCollection = new ConfigCollection;
        $myCollection->set('key1', new ConfigItem(['array item 1']));

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', new ConfigItem(['array item 2']));

        $this->strategy->merge($myCollection, $theirCollection);
        $expectedValue = ['array item 2', 'array item 1'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1')->getValue());
    }

    public function testMergeArrayByKey()
    {
        $myCollection = new ConfigCollection;
        $myItem = new ConfigItem(['arrKey1' => 'val1', 'arrKey2' => 'val2']);
        $myCollection->set('key1', $myItem);

        $theirCollection = new ConfigCollection;
        $theirItem = new ConfigItem(['arrKey1' => 'lessimportant', 'arrKey3' => 'val3']);
        $theirCollection->set('key1', $theirItem);

        $this->strategy->merge($myCollection, $theirCollection);
        $expectedValue = ['arrKey1' => 'val1', 'arrKey2' => 'val2', 'arrKey3' => 'val3'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1')->getValue());
    }


    public function testMergeArrayToString()
    {
        $myCollection = new ConfigCollection;
        $myCollection->set('key1', new ConfigItem(['array item 1']));

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', new ConfigItem('my string'));

        // We should see the array unchanged as it has higher priority than the string
        $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals(['array item 1'], $theirCollection->get('key1')->getValue());
    }

    public function testMergeStringToArray()
    {
        $myCollection = new ConfigCollection;
        $myCollection->set('key1', new ConfigItem('my string'));

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', new ConfigItem(['array item 1']));

        // We should see the string untouched as its higher priority.
        $theirCollection = $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals('my string', $theirCollection->get('key1')->getValue());
    }
}
