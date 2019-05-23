<?php declare(strict_types=1);

namespace SilverStripe\Config\Tests\AnnotationTransformer;

/**
 * @Foo
 * @Bar(class)
 */
class TestEverything
{
    /**
     * @Bar(123)
     */
    public function __construct()
    {
    }

    /**
     * @Foo
     */
    public function a()
    {

    }

    /**
     * @Bar(b)
     */
    public function b()
    {

    }
}
