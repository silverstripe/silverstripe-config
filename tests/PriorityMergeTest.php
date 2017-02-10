<?php

namespace SilverStripe\Config\Tests;

use PHPUnit\Framework\TestCase;
use SilverStripe\Config\MergeStrategy\Priority;
use SilverStripe\Config\Collections\MemoryConfigCollection;

class PriorityTest extends TestCase
{
    public function testMergeSingleItem()
    {
        $myCollection = ['key1' => ['value' => ['propname' => 'importantvalue']]];
        $theirCollection = new MemoryConfigCollection;
        $theirCollection->set('key1', 'propname', 'lessimportantvalue');

        Priority::merge($myCollection, $theirCollection);
        $this->assertEquals('importantvalue', $theirCollection->get('key1', 'propname'));
    }

    public function testMergeSingleArray()
    {
        $myCollection = ['key1' => ['value' => ['propname' => ['array item 1']]]];

        $theirCollection = new MemoryConfigCollection;
        $theirCollection->set('key1', 'propname', ['array item 2']);

        Priority::merge($myCollection, $theirCollection);
        $expectedValue = ['array item 2', 'array item 1'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1', 'propname'));
    }

    public function testMergeArrayByKey()
    {
        $myCollection = ['key1' => ['value' => ['propname' => ['arrKey1' => 'val1', 'arrKey2' => 'val2']]]];

        $theirCollection = new MemoryConfigCollection;
        $theirCollection->set('key1', 'propname', ['arrKey1' => 'lessimportant', 'arrKey3' => 'val3']);

        Priority::merge($myCollection, $theirCollection);
        $expectedValue = ['arrKey1' => 'val1', 'arrKey2' => 'val2', 'arrKey3' => 'val3'];
        $this->assertEquals($expectedValue, $theirCollection->get('key1', 'propname'));
    }


    public function testMergeArrayToString()
    {
        $myCollection = ['key1' => ['value' => ['propname' => ['array item 1']]]];

        $theirCollection = new MemoryConfigCollection;
        $theirCollection->set('key1', 'propname', 'my string');

        // We should see the array unchanged as it has higher priority than the string
        Priority::merge($myCollection, $theirCollection);
        $this->assertEquals(['array item 1'], $theirCollection->get('key1', 'propname'));
    }

    public function testMergeStringToArray()
    {
        $myCollection = ['key1' => ['value' => ['propname' => 'my string']]];

        $theirCollection = new MemoryConfigCollection;
        $theirCollection->set('key1', 'propname', ['array item 1']);

        // We should see the string untouched as its higher priority.
        $theirCollection = Priority::merge($myCollection, $theirCollection);
        $this->assertEquals('my string', $theirCollection->get('key1', 'propname'));
    }

    public function testMergeFalsey()
    {
        $myCollection = ['key1' => ['value' => [
            'nullprop' => null,
            'arrayprop' => [
                'nested' => false
            ]
        ]]];

        $theirCollection = new MemoryConfigCollection;
        $theirCollection->set('key1', 'nullprop', 'nonnull');
        $theirCollection->set('key1', 'arrayprop', [
            'nested' => 'somevalue',
            'othernested' => 'anotherValue'
        ]);

        // We should see the string untouched as its higher priority.
        $theirCollection = Priority::merge($myCollection, $theirCollection);
        $this->assertEquals(null, $theirCollection->get('key1', 'nullprop'));
        $this->assertEquals(
            ['nested' => false, 'othernested' => 'anotherValue'],
            $theirCollection->get('key1', 'arrayprop')
        );
    }
}
