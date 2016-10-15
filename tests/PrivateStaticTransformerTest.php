<?php

use micmania1\config\Transformer\PrivateStaticTransformer;
use micmania1\config\ConfigCollection;
use PHPUnit\Framework\TestCase;

class PrivateStaticTransformerTest extends TestCase
{

    /**
     * Ensure it looks up the private statics correctly and ignores everything else.
     */
    public function testLookup()
    {
        $classes = [PrivateStaticTransformerTest_ClassA::class];
        $collection = new ConfigCollection;
        $transformer = new PrivateStaticTransformer($classes, $collection);

        $transformer->transform();

        // Asert that keys match
        $this->assertEquals(
            [PrivateStaticTransformerTest_ClassA::class],
            $collection->keys()
        );

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
            $collection->get(PrivateStaticTransformerTest_ClassA::class)->getValue()
        );
    }

    /**
     * Ensure that two classes merge to diplsay the correct config.
     */
    public function testMerge()
    {
        $classes = [
            PrivateStaticTransformerTest_ClassA::class,
            PrivateStaticTransformerTest_ClassB::class,
        ];
        $collection = new ConfigCollection;
        $transformer = new PrivateStaticTransformer($classes, $collection);

        $transformer->transform();

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

        $this->assertEquals($classes, $collection->keys());

        $classA = PrivateStaticTransformerTest_ClassA::class;
        $classB = PrivateStaticTransformerTest_ClassB::class;

        $this->assertEquals($expectedA, $collection->get($classA)->getValue());
        $this->assertEquals($expectedB, $collection->get($classB)->getValue());
    }

    /**
     * Test that invalid classes are ignored
     */
    public function testInvalidClass()
    {
        $class = 'SomeNonExistentClass';
        if(class_exists($class)) {
            $this->markTestSkipped($class . ' exists but the test expects it not to.');
        }

        $collection = new ConfigCollection;
        $transformer = new PrivateStaticTransformer([$class], $collection);
        $transformer->transform();
        $this->assertEquals([], $collection->keys());
    }

}

class PrivateStaticTransformerTest_ClassA
{
    private static $myString = 'value';

    private static $myArray = [
        'myThing' => 'myValue',
        'myOtherThing',
    ];

    public static $ignoredPublicStatic = 'ignored';

    protected static $ignoredProtectedStatic = 'ignored';

    private $ignoredPrivate = 'ignored';

    protected $ignoredProtected = 'ignored';

    public $ignoredPublic = 'ignored';
}


class PrivateStaticTransformerTest_ClassB
{
    private static $myString = 'my other string';

    private static $myArray = [
        'test1',
        'test2'
    ];
}
