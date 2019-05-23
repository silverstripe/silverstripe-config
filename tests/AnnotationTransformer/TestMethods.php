<?php declare(strict_types=1);

namespace SilverStripe\Config\Tests\AnnotationTransformer;

class TestMethods
{
    /**
     * @ignore some fake annotation
     * @Foo
     * @Bar(123)
     */
    public function someMethod()
    {

    }
}
