<?php

namespace SilverStripe\Config\Tests\Collections;

use PHPUnit\Framework\TestCase;
use SilverStripe\Config\Collections\DeltaConfigCollection;
use SilverStripe\Config\Collections\MemoryConfigCollection;

class DeltaConfigCollectionTest extends TestCase
{
    /*
     * @return DeltaConfigCollection
     */
    protected function scaffoldCollection()
    {
        $baseCollection = new MemoryConfigCollection();
        $baseCollection->set('First', null, [
            'array' => [
                'one' => 1,
                'two' => 2,
            ],
            'string' => 'bob',
            'bool' => false,
        ]);
        $baseCollection->set('Second', null, [
            'array' => [
                'one' => 1,
                'two' => 2,
            ],
            'string' => 'bob',
            'bool' => false,
        ]);
        return DeltaConfigCollection::createFromCollection($baseCollection);
    }

    public function testSet()
    {
        $collection = $this->scaffoldCollection();
        $collection->set('First', null, ['replacement' => 'notdelta']);
        $collection->set('First', null, ['replacement' => 'someval']);
        $collection->set('First', 'key', 'notdelta');
        $collection->set('First', 'key', 'value');

        // Check values
        $this->assertEquals(
            [
                'array' => [
                    'one' => 1,
                    'two' => 2,
                ],
                'string' => 'bob',
                'bool' => false,
            ],
            $collection->get('Second')
        );
        $this->assertEquals(
            [
                'replacement' => 'someval',
                'key' => 'value',
            ],
            $collection->get('First')
        );
        $this->assertEquals('value', $collection->get('First', 'key'));

        // Check deltas
        $this->assertEquals(
            [
                [
                    'type' => DeltaConfigCollection::REPLACE,
                    'config' => ['replacement' => 'someval'],
                ],
                [
                    'type' => DeltaConfigCollection::SET,
                    'config' => ['key' => 'value'],
                ]
            ],
            $collection->getDeltas('First')
        );
        $this->assertEquals(
            [],
            $collection->getDeltas('Second')
        );
    }

    public function testMerge()
    {
        $collection = $this->scaffoldCollection();
        $collection->merge('First', null, ['replacement' => 'someval']);
        $collection->merge('First', 'array', ['three' => 3]);

        // Check values
        $this->assertEquals(
            [
                'array' => [
                    'one' => 1,
                    'two' => 2,
                    'three' => 3,
                ],
                'string' => 'bob',
                'bool' => false,
                'replacement' => 'someval',
            ],
            $collection->get('First')
        );

        // Check deltas
        $this->assertEquals(
            [
                [
                    'type' => DeltaConfigCollection::MERGE,
                    'config' => ['replacement' => 'someval'],
                ],
                [
                    'type' => DeltaConfigCollection::MERGE,
                    'config' => ['array' => ['three' => 3]],
                ]
            ],
            $collection->getDeltas('First')
        );
    }

    public function testRemove()
    {
        $collection = $this->scaffoldCollection();
        $collection->merge('First', 'key', 'value');
        $collection->remove('First');
        $collection->merge('Second', 'string', 'bobnew');
        $collection->merge('Second', 'array', ['four' => 4]);
        $collection->remove('Second', 'array');

        // Empty first collection, with redundant prior deltas cleared
        $this->assertTrue($collection->isDeltaReset('First')); // Full class reset
        $this->assertEquals(
            [],
            $collection->get('First')
        );
        $this->assertEquals(
            [['type' => DeltaConfigCollection::CLEAR]],
            $collection->getDeltas('First')
        );

        // Second collection maintains iterative values,
        // but safely clears obsolete deltas
        $this->assertFalse($collection->isDeltaReset('Second')); // Only partial reset so false
        $this->assertEquals(
            [
                'string' => 'bobnew',
                'bool' => false,
            ],
            $collection->get('Second')
        );
        $this->assertEquals(
            [
                [
                    'type' => 'merge',
                    'config' => ['string' => 'bobnew'],
                ],
                [
                    'type' => DeltaConfigCollection::REMOVE,
                    'config' => ['array' => true],
                ]
            ],
            $collection->getDeltas('Second')
        );
    }

    public function testClear()
    {
        $collection = $this->scaffoldCollection();
        $collection->merge('First', 'key', 'value');
        $collection->remove('First', 'string');
        $collection->removeAll();

        $this->assertTrue($collection->isDeltaReset());
        $this->assertEquals([], $collection->get('First'));
        $this->assertEquals([], $collection->get('Second'));
        $this->assertEquals([], $collection->getDeltas('First'));
        $this->assertEquals([], $collection->getDeltas('Second'));
    }
}
