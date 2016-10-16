<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\MergeStrategy\Priority;
use micmania1\config\ConfigCollection;
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
        $myCollection = ['key1' => ['value' => 'importantvalue']];

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', 'lessimportantvalue');

        $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals('importantvalue', $theirCollection->get('key1'));
    }

    public function testMergeSingleArray()
    {
        $myCollection = ['key1' => ['value' => ['array item 1']]];

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', ['array item 2']);

        $this->strategy->merge($myCollection, $theirCollection);
        $expectedValue = ['array item 2', 'array item 1'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1'));
    }

    public function testMergeArrayByKey()
    {
        $myCollection = ['key1' => ['value' => ['arrKey1' => 'val1', 'arrKey2' => 'val2']]];

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', ['arrKey1' => 'lessimportant', 'arrKey3' => 'val3']);

        $this->strategy->merge($myCollection, $theirCollection);
        $expectedValue = ['arrKey1' => 'val1', 'arrKey2' => 'val2', 'arrKey3' => 'val3'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1'));
    }


    public function testMergeArrayToString()
    {
        $myCollection = ['key1' => ['value' => ['array item 1']]];

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', 'my string');

        // We should see the array unchanged as it has higher priority than the string
        $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals(['array item 1'], $theirCollection->get('key1'));
    }

    public function testMergeStringToArray()
    {
        $myCollection = ['key1' => ['value' => 'my string']];

        $theirCollection = new ConfigCollection;
        $theirCollection->set('key1', ['array item 1']);

        // We should see the string untouched as its higher priority.
        $theirCollection = $this->strategy->merge($myCollection, $theirCollection);
        $this->assertEquals('my string', $theirCollection->get('key1'));
    }
}
