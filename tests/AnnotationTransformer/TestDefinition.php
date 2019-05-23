<?php

namespace SilverStripe\Config\Tests\AnnotationTransformer;

use ReflectionClass;
use ReflectionMethod;
use SilverStripe\Config\Transformer\AnnotationTransformer\AnnotationDefinitionInterface;

class TestDefinition implements AnnotationDefinitionInterface
{
    public function shouldCollect(string $className): bool
    {
        return true;
    }

    public function defineCollectionScopes(): int
    {
        return AnnotationDefinitionInterface::COLLECT_CLASS | AnnotationDefinitionInterface::COLLECT_CONSTRUCTOR
            | AnnotationDefinitionInterface::COLLECT_METHODS;
    }

    public function shouldCollectFromMethod(ReflectionClass $class, ReflectionMethod $method): bool
    {
        return true;
    }

    public function getAnnotationStrings(): array
    {
        return ['Foo', 'Bar'];
    }

    public function createConfigForAnnotation(
        string $annotation,
        array $arguments,
        int $context,
        ?string $contextDetail = null
    ): array
    {
        switch ($annotation) {
            case 'Foo':
                $config = ['Foo' => true];
                break;
            case 'Bar':
                $config = ['Bar' => reset($arguments)];
                break;
            default:
                return [];
        }

        if ($context === AnnotationDefinitionInterface::COLLECT_METHODS && is_string($contextDetail)) {
            return [$contextDetail => $config];
        }

        return $config;
    }
}
