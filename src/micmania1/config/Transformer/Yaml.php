<?php

namespace micmania1\config\Transformer;

use micmania1\config\MergeStrategy\Priority;
use Symfony\Component\Yaml\Yaml as YamlParser;
use Symfony\Component\Finder\Finder;
use MJS\TopSort\Implementations\ArraySort;
use Exception;

class Yaml
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
     * @param string $dir directory to scan for yaml files
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
        $this->merged = [];

        if (empty($this->files)) {
            return merged;
        }

        $documents = $this->getSortedYamlDocuments();
        $config = [];
        $mergeStrategy = new Priority();
        foreach ($documents as $document) {
            if (!empty($document['content'])) {
                $config = $mergeStrategy->merge($document['content'], $config);
            }
        }

        return [$this->sort => $config];
    }

    /**
     * Returns an array of YAML documents keyed by name.
     *
     * @return array;
     */
    protected function getNamedYamlDocuments()
    {
        $unnamed = $this->splitYamlDocuments();

        if (empty($unnamed)) {
            return [];
        }

        $documents = [];
        foreach ($unnamed as $uniqueKey => $document) {
            $header = YamlParser::parse($document['header']);
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

                if (($context === 'content' || $firstLine) && $line === '---') {
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
            $content = $document['content'];

            // If the document doesn't have a name, we'll generate one
            if (!isset($header['name'])) {
                $header['name'] = md5($document['filename']).'-'.$key;
            }

            // If our document isn't yet listed in the dependencies we'll add it
            if (!isset($dependencies[$header['name']])) {
                $dependencies[$header['name']] = [];
            }

            // Add 'after' dependencies
            if (isset($header['after'])) {
                $dependencies = $this->addDependencies(
                    $header['after'],
                    $header['name'],
                    self::AFTER_FLAG,
                    $dependencies,
                    $documents
                );
            }

            // Add 'before' dependencies
            if (isset($header['before'])) {
                $dependencies = $this->addDependencies(
                    $header['before'],
                    $header['name'],
                    self::BEFORE_FLAG,
                    $dependencies,
                    $documents
                );
            }
        }

        return $dependencies;
    }

    /**
     * Incapsulates the logic for adding before/after dependencies.
     *
     * @param array|string $currentDocument
     * @param string       $name
     * @param string       $flag
     * @param array        $dependencies
     * @param array        $documents
     *
     * @return array
     */
    protected function addDependencies($currentDocument, $name, $flag, $dependencies, $documents)
    {
        // Normalise our input. YAML accpets string or array values.
        if (!is_array($currentDocument)) {
            $currentDocument = [$currentDocument];
        }

        foreach ($currentDocument as $dependency) {
            // Ensure our depdency and current document have dependencies listed
            if (!isset($dependencies[$name])) {
                $dependencies[$name] = [];
            }

            // Because wildcards and hashes exist, our 'dependency' might actually match
            // multiple blocks and therefore could be multiple dependencies.
            $matchingDocuments = $this->getMatchingDocuments($dependency, $documents);

            // If we have no matching documents, don't add it to dependecies
            if (empty($matchingDocuments)) {
                continue;
            }

            foreach ($matchingDocuments as $document) {
                $dependencyName = $document['header']['name'];
                if (!isset($dependencies[$dependencyName])) {
                    $dependencies[$dependencyName] = [];
                }

                if ($flag == self::AFTER_FLAG) {
                    // For 'after' we add the given dependency to the current document
                    $dependencies[$name][] = $dependencyName;
                } elseif ($flag == self::BEFORE_FLAG) {
                    // For 'before' we add the current document as a dependency to $before
                    $dependencies[$dependencyName][] = $name;
                } else {
                    throw Exception('Invalid flag set for adding dependency.');
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
        $pattern = str_replace(DIRECTORY_SEPARATOR, '\\'.DIRECTORY_SEPARATOR, $pattern);
        $pattern = str_replace('*', '[^\.][a-zA-Z0-9\-_\/\.]+', $pattern);

        $matchedDocuments = [];
        foreach ($documents as $document) {
            // Ensure filename is relative
            $filename = $this->makeRelative($document['filename']);
            if (preg_match('%^'.$pattern.'%', $filename)) {
                if ($documentName && $documentName != $document['header']['name']) {
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
        $documents = $this->getNamedYamlDocuments();

        if (empty($documents)) {
            return [];
        }

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
}
