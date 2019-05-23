<?php declare(strict_types=1);

namespace SilverStripe\Config\Tests\AnnotationTransformer;

class TestClassConstructor
{
    /**
     * @ignore some fake annotation
     * @Foo
     * @Bar(123)
     */
    public function __construct()
    {
    }
}
