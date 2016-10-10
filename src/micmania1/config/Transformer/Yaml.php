<?php

namespace micmania1\config\Transformer;

use micmania1\config\MergeStrategy\Priority;
use Symfony\Component\Yaml\Yaml as YamlParser;
use Symfony\Component\Finder\Finder;
use MJS\TopSort\Implementations\ArraySort;
use Exception;
use Closure;

class Yaml implements TransformerInterface
{
    /**
     * @const string
     */
    const BEFORE_FLAG = 'before';

    /**
     * @const string
     */
    const AFTER_FLAG = 'after';

    /**
     * @var string
     */
    const ONLY_FLAG = 'only';

    /**
     * @var string
     */
    const EXCEPT_FLAG = 'except';

    /**
     * A list of files. Real, full path.
     *
     * @var array
     */
    protected $files = [];

    /**
     * Store the yaml document content as an array.
     *
     * @var array
     */
    protected $documents = [];

    /**
     * @var int
     */
    protected $sort;

    /**
     * Base directory used to find yaml files.
     *
     * @var string
     */
    protected $baseDirectory;

    /**
     * A list of closures to be used in only/except rules.
     *
     * @var Closure[]
     */
    protected $rules = [];

    /**
     * A list of ignored before/after statements.
     *
     * @var array
     */
    protected $ignoreRules = [];

    /**
     * @param string $baseDir directory to scan for yaml files
     * @param Finder $finder
     * @param int $sort
     */
    public function __construct($baseDir, Finder $finder, $sort = 0)
    {
        $this->baseDirectory = $baseDir;
        $this->sort = $sort;

        foreach ($finder as $file) {
            $this->files[$file->getPathname()] = $file->getPathname();
        }
    }

    /**
     * This is responsible for parsing a single yaml file and returning it into a format
     * that Config can understand. Config will then be responsible for turning thie
     * output into the final merged config.
     *
     * @return array
     */
    public function transform()
    {
        $config = [];
        $mergeStrategy = new Priority();

        $documents = $this->getSortedYamlDocuments();
        foreach ($documents as $document) {
            if (!empty($document['content'])) {
                $config = $mergeStrategy->merge($document['content'], $config);
            }
        }

        return [$this->sort => $config];
    }

    /**
     * This allows external rules to be added to only/except checks. Config is only
     * supposed to be setup once, so adding rules is a one-way system. You cannot
     * remove rules after being set. This also prevent built-in rules from being
     * removed.
     *
     * @param string $rule
     * @param Closure $func
     */
    public function addRule($rule, Closure $func)
    {
        $rule = strtolower($rule);
        $this->rules[$rule] = $func;
    }

    /**
     * Checks to see if a rule is present
     *
     * @var string
     *
     * @return boolean
     */
    protected function hasRule($rule)
    {
        $rule = strtolower($rule);
        return isset($this->rules[$rule]);
    }

    /**
     * This allows config to ignore only/except rules that have been set. This enables
     * apps to ignore built-in rules without causing errors where a rule is undefined.
     * This, is a one-way system and is only meant to be configured once. When you
     * ignore a rule, you cannot un-ignore it.
     *
     * @param string $rule
     */
    public function ignoreRule($rule)
    {
        $rule = strtolower($rule);
        $this->ignoreRules[$rule] = $rule;
    }

    /**
     * Checks to see if a rule is ignored
     *
     * @param string $rule
     *
     * @return boolean
     */
    protected function isRuleIgnored($rule)
    {
        $rule = strtolower($rule);

        return isset($this->ignoreRules[$rule]);
    }

    /**
     * Returns an array of YAML documents keyed by name.
     *
     * @return array
     */
    protected function getNamedYamlDocuments()
    {
        $unnamed = $this->splitYamlDocuments();

        $documents = [];
        foreach ($unnamed as $uniqueKey => $document) {
            $header = YamlParser::parse($document['header']) ?: [];
            $header = array_change_key_case($header, CASE_LOWER);

            $content = YamlParser::parse($document['content']);

            if (!isset($header['name'])) {
                // We automatically name this yaml doc. If it clashes with another, an
                // exception will be thrown below.
                $header['name'] = 'anonymous-'.$uniqueKey;
            }

            // Check if a document with that name already exists
            if (isset($documents[$header['name']])) {
                throw new Exception(
                    sprintf('More than one YAML document exists named \'%s\'.', $header['name'])
                );
            }

            $documents[$header['name']] = [
                'filename' => $document['filename'],
                'header' => $header,
                'content' => $content,
            ];
        }

        return $documents;
    }

    /**
     * Because multiple documents aren't supported in symfony/yaml, we have to manually
     * split the files up into their own documents before running them through the parser.
     * Note: This is not a complete implementation of multi-document YAML parsing. There
     * are valid yaml cases where this will fail, however they don't match our use-case.
     *
     * @return array
     */
    protected function splitYamlDocuments()
    {
        $documents = [];
        $key = 0;

        // We need to loop through each file and parse the yaml content
        foreach ($this->files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $firstLine = true;
            $context = 'content';
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                if (!isset($documents[$key])) {
                    $documents[$key] = [
                        'filename' => $file,
                        'header' => '',
                        'content' => '',
                    ];
                }

                if (($context === 'content' || $firstLine) && ($line === '---' || $line === '...')) {

                    // '...' is the end of a document with no more documents after it.
                    if($line === '...') {
                        ++$key;
                        break;
                    }

                    $context = 'header';

                    // If this isn't the first line (and therefor first doc) we'll increase
                    // our document key
                    if (!$firstLine) {
                        ++$key;
                    }
                } elseif ($context === 'header' && $line === '---') {
                    $context = 'content';
                } else {
                    $documents[$key][$context] .= $line.PHP_EOL;
                }

                $firstLine = false;
            }

            // Always increase document count at the end of a file
            ++$key;
        }

        return $documents;
    }

    /**
     * This generates an array of all document depndencies, keyed by document name.
     *
     * @param array $documents
     *
     * @return array
     */
    protected function calculateDependencies($documents)
    {
        $dependencies = [];
        foreach ($documents as $key => $document) {
            $header = $document['header'];

            // If our document isn't yet listed in the dependencies we'll add it
            if (!isset($dependencies[$header['name']])) {
                $dependencies[$header['name']] = [];
            }

            // Add 'after' dependencies
            $dependencies = $this->addDependencies(
                $header,
                self::AFTER_FLAG,
                $dependencies,
                $documents
            );

            // Add 'before' dependencies
            $dependencies = $this->addDependencies(
                $header,
                self::BEFORE_FLAG,
                $dependencies,
                $documents
            );
        }

        return $dependencies;
    }

    /**
     * Incapsulates the logic for adding before/after dependencies.
     *
     * @param array        $header
     * @param string       $flag
     * @param array        $dependencies
     * @param array        $documents
     *
     * @return array
     */
    protected function addDependencies($header, $flag, $dependencies, $documents)
    {
        // If header isn't set then return dependencies
        if(!isset($header[$flag]) || !in_array($flag, [self::BEFORE_FLAG, self::AFTER_FLAG])) {
            return $dependencies;
        }

        // Normalise our input. YAML accpets string or array values.
        if (!is_array($header[$flag])) {
            $header[$flag] = [$header[$flag]];
        }

        foreach ($header[$flag] as $dependency) {
            // Because wildcards and hashes exist, our 'dependency' might actually match
            // multiple blocks and therefore could be multiple dependencies.
            $matchingDocuments = $this->getMatchingDocuments($dependency, $documents);

            foreach ($matchingDocuments as $document) {
                $dependencyName = $document['header']['name'];
                if (!isset($dependencies[$dependencyName])) {
                    $dependencies[$dependencyName] = [];
                }

                if ($flag == self::AFTER_FLAG) {
                    // For 'after' we add the given dependency to the current document
                    $dependencies[$header['name']][] = $dependencyName;
                } else {
                    // For 'before' we add the current document as a dependency to $before
                    $dependencies[$dependencyName][] = $header['name'];
                }
            }
        }

        return $dependencies;
    }

    /**
     * This returns an array of documents which match the given pattern. The pattern is
     * expected to come from before/after blocks of yaml (eg. framwork/*).
     *
     * @param string $pattern
     * @param array
     *
     * @return array
     */
    protected function getMatchingDocuments($pattern, $documents)
    {
        // If the pattern exists as a document name then its just a simple name match
        // and we can return that single document.
        if (isset($documents[$pattern])) {
            return [$documents[$pattern]];
        }

        // If the pattern starts with a hash, it means we're looking for a single document
        // named without the hash.
        if (strpos($pattern, '#') === 0) {
            $name = substr($pattern, 1);
            if (isset($documents[$name])) {
                return [$documents[$name]];
            }

            return [];
        }

        // If we only have an astericks, we'll add all unnamed docs. By excluding named docs
        // we don't run into a circular depndency issue.
        if($pattern === '*') {
            $pattern = 'anonymous-*';
        }

        // Do pattern matching on file names. This requires us to loop through each document
        // and check their filename and maybe their document name, depending on the pattern.
        // We don't want to do any pattern matching after the first hash as the document name
        // is assumed to follow it.
        $firstHash = strpos('#', $pattern);
        $documentName = false;
        if ($firstHash !== false) {
            $documentName = substr($pattern, $firstHash + 1);
            $pattern = substr($pattern, 0, $firstHash);
        }

        // @todo better sanitisation needed for special chars (chars used by preg_match())
        $pattern = str_replace(DIRECTORY_SEPARATOR, '\\'.DIRECTORY_SEPARATOR, $pattern);
        $pattern = str_replace('*', '[^\.][a-zA-Z0-9\-_\/\.]+', $pattern);

        $matchedDocuments = [];
        foreach ($documents as $document) {
            // Ensure filename is relative
            $filename = $this->makeRelative($document['filename']);
            if (preg_match('%^'.$pattern.'%', $filename)) {
                if (!empty($documentName) && $documentName != $document['header']['name']) {
                    // If we're looking for a specific document. If not found we can continue
                    continue;
                }

                $matchedDocuments[] = $document;
            }
        }

        return $matchedDocuments;
    }

    /**
     * We need this to make the path relative from the base directory. We can't use realpath
     * or relative path in Finder because we use a virtual filesystem in testing which
     * doesn't support these methods.
     *
     * @param string $filename
     *
     * @return string
     */
    protected function makeRelative($filename)
    {
        $dir = substr($filename, 0, strlen($this->baseDirectory));
        if ($dir == $this->baseDirectory) {
            return trim(substr($filename, strlen($this->baseDirectory)), DIRECTORY_SEPARATOR);
        }

        return trim($filename, DIRECTORY_SEPARATOR);
    }

    /**
     * This method gets all headers and all yaml documents and stores them respectively.
     *
     * @return array a list of sorted yaml documents
     */
    protected function getSortedYamlDocuments()
    {
        $documents = $this->filterByOnlyAndExcept();
        $dependencies = $this->calculateDependencies($documents);

        // Now that we've built up our dependencies, we can pass them into
        // a topological sort and return the headers.
        $sorter = new ArraySort();
        $sorter->set($dependencies);
        $sorted = $sorter->sort();

        $orderedDocuments = [];
        foreach ($sorted as $name) {
            if (!empty($documents[$name])) {
                $orderedDocuments[$name] = $documents[$name];
            }
        }

        return $orderedDocuments;
    }

    /**
     * This filteres out any yaml documents which don't pass their only
     * or except statement tests.
     *
     * @return array
     */
    protected function filterByOnlyAndExcept()
    {
        $documents = $this->getNamedYamlDocuments();
        $filtered = [];
        foreach($documents as $key => $document) {
            // If not all rules match, then we exclude this document
            if(!$this->testRules($document['header'], self::ONLY_FLAG)) {
                continue;
            }

            // If all rules pass, then we exclude this document
            if($this->testRules($document['header'], self::EXCEPT_FLAG)) {
                continue;
            }

            $filtered[$key] = $document;
        }

        return $filtered;
    }

    /**
     * Tests the only except rules for a header.
     *
     * @param array $header
     * @param string $flag
     *
     * @return boolean
     */
    protected function testRules($header, $flag)
    {
        // If flag is not set, then it has no tests
        if(!isset($header[$flag])) {
            // We want only to pass, except to fail
            return $flag === self::ONLY_FLAG;
        }

        if(!is_array($header[$flag])) {
            throw new Exception(sprintf('\'%s\' statements must be an array', $flag));
        }

        foreach($header[$flag] as $rule => $params) {
            if($this->isRuleIgnored($rule)) {
                // If checking only, then return true. Otherwise, return false.
                return $flag === self::ONLY_FLAG;
            }

            if(!$this->testSingleRule($rule, $params)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tests a rule against the given expected result.
     *
     * @param string $rule
     * @param string|array $params
     *
     * @return boolean
     */
    protected function testSingleRule($rule, $params)
    {
        if(!$this->hasRule($rule)) {
            throw new Exception(sprintf('Rule \'%s\' doesn\'t exist.', $rule));
        }

        if(!is_array($params)) {
            return $this->rules[$rule]($params);
        }

        // If its an array, we'll loop through each parameter
        foreach($params as $key => $value) {
            if(!$this->rules[$rule]($key, $value)) {
                return false;
            }
        }

        return true;
    }
}
