<?php declare(strict_types=1);

namespace SilverStripe\Config\Transformer;

use InvalidArgumentException;
use ReflectionClass;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Config\Transformer\AnnotationTransformer\AnnotationDefinitionInterface;

class AnnotationTransformer implements TransformerInterface
{
    /**
     * Set a callable that can be used to resolve the classes to collect annotations from
     *
     * @var callable
     */
    protected $classResolver;

    /**
     * An array of @see AnnotationDefinitionInterface that indicate what annotations are collected from doc blocks and
     * how those annotations are converted to config
     *
     * @var AnnotationDefinitionInterface[]
     */
    protected $annotationDefinitions;

    /**
     * The list of resolved class names once the resolver has been called
     *
     * @var array|null
     */
    protected $classes;

    /**
     * AnnotationTransformer constructor.
     * @param callable $classResolver
     * @param array $annotationDefinitions
     */
    public function __construct(callable $classResolver, array $annotationDefinitions)
    {
        $this->classResolver = $classResolver;

        foreach ($annotationDefinitions as $annotationDefinition) {
            if (!$annotationDefinition instanceof AnnotationDefinitionInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Annotation definitions provided to %s must implement %s',
                    __CLASS__,
                    AnnotationDefinitionInterface::class
                ));
            }
        }

        $this->annotationDefinitions = $annotationDefinitions;
    }

    /**
     * This is responsible for parsing a single yaml file and returning it into a format
     * that Config can understand. Config will then be responsible for turning thie
     * output into the final merged config.
     *
     * @param  MutableConfigCollectionInterface $collection
     * @return MutableConfigCollectionInterface
     * @throws \ReflectionException
     */
    public function transform(MutableConfigCollectionInterface $collection)
    {
        if (empty($this->annotationDefinitions)) {
            return $collection;
        }

        foreach ($this->getClasses() as $className) {
            // Skip classes that don't exist
            if (!class_exists($className)) {
                continue;
            }

            $config = [];

            foreach ($this->annotationDefinitions as $definition) {
                // Check if this class should be affected at all
                if (!$definition->shouldCollect($className)) {
                    continue;
                }

                $classReflector = new ReflectionClass($className);
                $scopes = $definition->defineCollectionScopes();

                $config = [];

                // Collect from class docblocks
                if (
                    $scopes & AnnotationDefinitionInterface::COLLECT_CLASS
                    && ($doc = $classReflector->getDocComment())
                ) {
                    $config = $this->augmentConfigForBlock(
                        $config,
                        $doc,
                        $definition,
                        AnnotationDefinitionInterface::COLLECT_CLASS
                    );
                }

                // Collect from constructors separately to other methods
                if (
                    $scopes & AnnotationDefinitionInterface::COLLECT_CONSTRUCTOR
                    && ($constructor = $classReflector->getConstructor())
                    && ($doc = $constructor->getDocComment())
                ) {
                    $config = array_merge($config, $this->augmentConfigForBlock(
                        $config,
                        $doc,
                        $definition,
                        AnnotationDefinitionInterface::COLLECT_CONSTRUCTOR
                    ));
                }

                // Collect from methods
                if ($scopes & AnnotationDefinitionInterface::COLLECT_METHODS) {
                    foreach ($classReflector->getMethods() as $method) {
                        if (
                            $method->isConstructor()
                            || !$definition->shouldCollectFromMethod($classReflector, $method)
                            || !($docBlock = $method->getDocComment())
                        ) {
                            continue;
                        }

                        $config = array_merge($config, $this->augmentConfigForBlock(
                            $config,
                            $docBlock,
                            $definition,
                            AnnotationDefinitionInterface::COLLECT_METHODS,
                            $method->getName()
                        ));
                    }
                }
            }

            // Add the config to the collection
            foreach ($config as $name => $item) {
                $collection->set($className, $name, $item);
            }
        }

        return $collection;
    }

    /**
     * Returns the list of classnames - executing the class resolver for them if it has not yet been called
     *
     * @return array
     */
    protected function getClasses(): array
    {
        if (!$this->classes) {
            $this->classes = call_user_func($this->classResolver) ?: [];
        }

        return $this->classes;
    }

    /**
     * Parse a string of arguments added to an annotation (eg. @Annotation(something=1,b)) into an array
     *
     * @param string $arguments
     * @return array
     */
    protected function parseAnnotationArguments(string $arguments): array
    {
        if (empty($arguments)) {
            return [];
        }

        $parts = explode(',', $arguments);

        $arguments = [];
        foreach ($parts as $part) {
            if (!strpos($part, '=')) {
                // Trim in the case of `=arg` (0th index `=`)
                $arguments[] = trim(trim($part, '='));
                continue;
            }

            list ($key, $value) = explode('=', $part, 2);

            $arguments[trim($key)] = trim($value);
        }

        return $arguments;
    }

    protected function augmentConfigForBlock(
        array $config,
        string $docBlock,
        AnnotationDefinitionInterface $definition,
        int $context,
        ?string $contextDetail = null
    ): array {
        $annotationMatches = implode('|', array_map('preg_quote', $definition->getAnnotationStrings()));
        $pattern = '/^\s*\*\s*@(' . $annotationMatches . ')(?:\(([^)]+)\))?\s*$/m';

        $matchCount = preg_match_all($pattern, $docBlock, $matches);

        for ($i = 0; $i < $matchCount; $i++) {
            $config = array_merge_recursive($config, $definition->createConfigForAnnotation(
                $matches[1][$i],
                $this->parseAnnotationArguments($matches[2][$i]),
                $context,
                $contextDetail
            ));
        }

        return $config;
    }
}
