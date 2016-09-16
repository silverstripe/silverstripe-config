<?php

use micmania1\config\Transformer\PrivateStaticTransformer;
use PHPUnit\Framework\TestCase;

class PrivateStaticTransformerTest extends TestCase
{

    /**
     * Ensure it looks up the private statics correctly and ignores everything else.
     */
    public function testLookup()
    {
        $classes = [PrivateStaticTransformerTest_ClassA::class];
        $transformer = new PrivateStaticTransformer($classes);

        $expected = [0 => [
            PrivateStaticTransformerTest_ClassA::class => [
                'myString' => 'value',
                'myArray' => [
                    'myThing' => 'myValue',
                    0 => 'myOtherThing',
                ],
            ],
        ]];
        $this->assertEquals($expected, $transformer->transform());
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
        $transformer = new PrivateStaticTransformer($classes);

        $expected = [0 => [
            PrivateStaticTransformerTest_ClassA::class => [
                'myString' => 'value',
                'myArray' => [
                    'myThing' => 'myValue',
                    0 => 'myOtherThing',
                ],
            ],
            PrivateStaticTransformerTest_ClassB::class => [
                'myString' => 'my other string',
                'myArray' => [
                    0 => 'test1',
                    1 => 'test2',
                ],
            ],
        ]];
        $this->assertEquals($expected, $transformer->transform());
    }

    /**
     * Test that invalid classes are ignored
     */
    public function testInvalidClass()
    {
        $class = 'SomeNonExistentClass';
        if(class_exists($class)) {
            $this->markTestSkipped($class . ' exists but the test expects it not to');
        }

        $config = (new PrivateStaticTransformer([$class]))->transform();
        $this->assertEquals([0 => []], $config);
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
