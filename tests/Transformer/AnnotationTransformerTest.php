<?php

namespace SilverStripe\Config\Tests\Transformer;

use PHPUnit\Framework\TestCase;
use SilverStripe\Config\Collections\MemoryConfigCollection;
use SilverStripe\Config\Tests\AnnotationTransformer\TestClass;
use SilverStripe\Config\Tests\AnnotationTransformer\TestClassConstructor;
use SilverStripe\Config\Tests\AnnotationTransformer\TestDefinition;
use SilverStripe\Config\Tests\AnnotationTransformer\TestEverything;
use SilverStripe\Config\Tests\AnnotationTransformer\TestMethods;
use SilverStripe\Config\Transformer\AnnotationTransformer;

class AnnotationTransformerTest extends TestCase
{
    public function testAnnotationsAreCollectedFromClassDocBlocks()
    {
        $classResolver = function() {
            return [TestClass::class];
        };

        $collection = new MemoryConfigCollection;
        $transformer = new AnnotationTransformer($classResolver, [new TestDefinition()]);

        $collection->transform([$transformer]);

        $this->assertTrue($collection->get(TestClass::class, 'Foo'));
        $this->assertSame('123', $collection->get(TestClass::class, 'Bar'));
    }

    public function testAnnotationsAreCollectedFromConstructorDocBlocks()
    {
        $classResolver = function() {
            return [TestClassConstructor::class];
        };

        $collection = new MemoryConfigCollection;
        $transformer = new AnnotationTransformer($classResolver, [new TestDefinition()]);

        $collection->transform([$transformer]);

        $this->assertTrue($collection->get(TestClassConstructor::class, 'Foo'));
        $this->assertSame('123', $collection->get(TestClassConstructor::class, 'Bar'));
    }

    public function testAnnotationsAreCollectedFromMethodDocBlocks()
    {
        $classResolver = function() {
            return [TestMethods::class];
        };

        $collection = new MemoryConfigCollection;
        $transformer = new AnnotationTransformer($classResolver, [new TestDefinition()]);

        $collection->transform([$transformer]);

        $config = $collection->get(TestMethods::class, 'someMethod');

        $this->assertInternalType('array', $config);
        $this->assertSame([
            'Foo' => true,
            'Bar' => '123',
        ], $config);
    }

    public function testClassesCanHaveManyAnnotations()
    {
        $classResolver = function() {
            return [TestEverything::class];
        };

        $collection = new MemoryConfigCollection;
        $transformer = new AnnotationTransformer($classResolver, [new TestDefinition()]);

        $collection->transform([$transformer]);

        $foo = $collection->get(TestEverything::class, 'Foo');
        $this->assertTrue($foo);

        $bar = $collection->get(TestEverything::class, 'Bar');
        $this->assertSame(['class', '123'], $bar);

        $a = $collection->get(TestEverything::class, 'a');
        $this->assertInternalType('array', $a);
        $this->assertSame([
            'Foo' => true,
        ], $a);

        $b = $collection->get(TestEverything::class, 'b');
        $this->assertInternalType('array', $b);
        $this->assertSame([
            'Bar' => 'b',
        ], $b);
    }
}
