<?php declare(strict_types=1);

namespace SilverStripe\Config\Transformer\AnnotationTransformer;

use ReflectionClass;
use ReflectionMethod;

interface AnnotationDefinitionInterface
{
    const COLLECT_CLASS = 1;
    const COLLECT_CONSTRUCTOR = 2;
    const COLLECT_METHODS = 4;

    /**
     * Return a bitwise integer combining COLLECT_* constants indicating what doc blocks to collect annotations from
     *
     * @return int
     */
    public function defineCollectionScopes(): int;

    /**
     * Indicates whether annotations should be collected from the given class
     *
     * @param string $className
     * @return bool
     */
    public function shouldCollect(string $className): bool;

    /**
     * Indicates whether annotations should be collected from the given method within the given class
     *
     * @param ReflectionClass $class
     * @param ReflectionMethod $method
     * @return bool
     */
    public function shouldCollectFromMethod(ReflectionClass $class, ReflectionMethod $method): bool;

    /**
     * Get an array of annotations to look for. For example 'Foo' would indicate that '@Foo' should be matched
     *
     * @return array
     */
    public function getAnnotationStrings(): array;

    /**
     * Create config from a matched annotation.
     *
     * @param string $annotation The annotation string that was matched (defined in @see getAnnotationStrings)
     * @param array $arguments An array of strings that were passed as arguments (eg. @Foo(argument1,argument2)
     * @param int $context A COLLECT_* constant that indicates what context this annotation was found in
     * @param string|null $contextDetail A method name, provided the context of the annotation was a method
     * @return array
     */
    public function createConfigForAnnotation(
        string $annotation,
        array $arguments,
        int $context,
        ?string $contextDetail = null
    ): array;
}
