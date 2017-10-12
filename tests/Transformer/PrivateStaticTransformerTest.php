<?php

namespace SilverStripe\Config\Tests\Transformer;

use LogicException;
use SilverStripe\Config\Tests\PrivateStaticTransformerTest\ClassA;
use SilverStripe\Config\Tests\PrivateStaticTransformerTest\ClassB;
use SilverStripe\Config\Tests\PrivateStaticTransformerTest\ClassK;
use SilverStripe\Config\Tests\PrivateStaticTransformerTest\ClassL;
use SilverStripe\Config\Transformer\PrivateStaticTransformer;
use SilverStripe\Config\Collections\MemoryConfigCollection;
use PHPUnit\Framework\TestCase;

class PrivateStaticTransformerTest extends TestCase
{

    /**
     * Ensure it looks up the private statics correctly and ignores everything else.
     */
    public function testLookup()
    {
        $classes = [
            ClassA::class
        ];
        $collection = new MemoryConfigCollection;
        $transformer = new PrivateStaticTransformer($classes);
        $collection->transform([$transformer]);

        // Assert that the value matches
        $expected = [
            'myString' => 'value',
            'myArray' => [
                'myThing' => 'myValue',
                0 => 'myOtherThing',
            ],
        ];

        $this->assertEquals(
            $expected,
            $collection->get(ClassA::class)
        );
    }

    /**
     * Ensure that two classes merge to diplsay the correct config.
     */
    public function testMerge()
    {
        $classes = [
            ClassA::class,
            ClassB::class,
        ];
        $collection = new MemoryConfigCollection;
        $transformer = new PrivateStaticTransformer($classes);

        $collection->transform([$transformer]);

        $expectedA = [
            'myString' => 'value',
            'myArray' => [
                'myThing' => 'myValue',
                0 => 'myOtherThing',
            ],
        ];

        $expectedB = [
            'myString' => 'my other string',
            'myArray' => [
                0 => 'test1',
                1 => 'test2',
            ],
        ];

        $classA = ClassA::class;
        $classB = ClassB::class;

        $this->assertEquals($expectedA, $collection->get($classA));
        $this->assertEquals($expectedB, $collection->get($classB));
    }

    /**
     * Test that invalid classes are ignored
     */
    public function testInvalidClass()
    {
        $class = 'SomeNonExistentClass';
        if (class_exists($class)) {
            $this->markTestSkipped($class . ' exists but the test expects it not to.');
        }

        $collection = new MemoryConfigCollection;
        $transformer = new PrivateStaticTransformer([$class], $collection);
        $collection->transform([$transformer]);
        $this->assertFalse($collection->exists($class));
    }


    public function testInvalidConfigError()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            ClassL::class . '::option is not private but overrides a private static '
            . 'config in parent class ' . ClassK::class
        );
        $classes = [
            ClassK::class,
            ClassL::class,
        ];
        $collection = new MemoryConfigCollection;
        $transformer = new PrivateStaticTransformer($classes, $collection);
        $collection->transform([$transformer]);
    }
}
