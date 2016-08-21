<?php

use micmania1\config\Transformer\Yaml as YamlTransformer;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\Finder\Finder;
use MJS\TopSort\CircularDependencyException;

class YamlTransformerTest extends TestCase
{
    /**
     * Config directory name.
     *
     * @var string
     */
    protected $directory = 'config';

    /**
     * Root directory for virtual filesystem
     *
     * @var string
     */
    protected $root;

    protected function setUp()
    {
        $this->root = vfsStream::setup();
    }

    protected function getFinder()
    {
        return (new Finder())
            ->files()
            ->name('/\.(yml|yaml)$/')
            ->in($this->getConfigDirectory());
    }

    /**
     * Quickly create a config file inside the config directory.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getFilePath($name)
    {
        return $this->getConfigDirectory().'/'.$name;
    }

    /**
     * Return the config directoyry path. This will create the directory if it doesn't
     * exist.
     *
     * @return string
     */
    protected function getConfigDirectory()
    {
        $dir = $this->root->url().'/'.$this->directory;

        // Create the directory if it doesn't exist.
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        return $dir;
    }

    /**
     * This tests that if we have an empty file, it will not throw an error
     * and will be returned as an empty array with the correct sort order.
     */
    public function testEmptyFileIgnored()
    {
        file_put_contents($this->getFilePath('empty.yml'), '');

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $this->assertEquals([10 => []], $config);
    }

    /**
     * This tests that if a document has no body, the it will not throw an error
     * and will return an empty array with the correct sort order.
     */
    public function testEmptyDocumentIgnored()
    {
        $header = <<<'YAML'
---
name: 'test'
---
YAML;
        file_put_contents($this->getFilePath('emptydoc.yml'), $header);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $this->assertEquals([10 => []], $config);

        // Now we'll test the same thing again, but the empty document will be mid-file
        $content = <<<'YAML'
first: test

---
name: second
---

---
name: third
---
third: test
YAML;

        file_put_contents($this->getFilePath('config2.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            'first' => 'test',
            'third' => 'test',
        ];
        $this->assertEquals([10 => $expected], $config);
    }

    /**
     * This tests that a single yaml file with no header is parsed correctly and the
     * correct value is returned.
     */
    public function testNoHeader()
    {
        $content = <<<'YAML'
MyConfig:
  test: 'test'
  assoc:
    test: 'assoctest'
  array:
    - 'arraytest1'
    - 'arraytest2'
YAML;

        $file = $this->getFilePath('config.yml');
        file_put_contents($file, $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            10 => [
                'MyConfig' => [
                    'test' => 'test',
                    'assoc' => [
                        'test' => 'assoctest',
                    ],
                    'array' => [
                        0 => 'arraytest1',
                        1 => 'arraytest2',
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    /**
     * This will ensure that a file with a header, but no name throws an apropriate
     * exception.
     */
    public function testNoNameStatement()
    {
        $content = <<<'YAML'
---
noname: 'error'
---
Test: blah
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            10 => [
                'Test' => 'blah',
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    /**
     * Test the sort order of before and after statements are correct and that content
     * merges correctly.
     */
    public function testBeforeAndAfterStatements()
    {
        $content = <<<'YAML'
---
name: 'first'
---
test: 'blah'
YAML;
        file_put_contents($this->getFilePath('first.yml'), $content);

        $content = <<<'YAML'
---
name: 'second'
after: 'first'
---
test: 'overwritten'
YAML;
        file_put_contents($this->getFilePath('second.yml'), $content);

        $content = <<<'YAML'
---
name: 'zzz'
before: 'first'
---
test: 'set first'
YAML;
        file_put_contents($this->getFilePath('zzz.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            10 => [
                'test' => 'overwritten',
            ],
        ];
        $this->assertEquals($expected, $config);
    }

    /**
     * Tests that before after statements with preceeding '#' are mapped to documents
     * correctly.
     */
    public function testBeforeAfterStatementsByHash()
    {
        $content = <<<'YAML'
---
name: first
---
test: 'actualvalue'

---
name: second
before: '#first'
---
test: 'overwritten'
YAML;
        file_put_contents($this->getFilePath('first.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            10 => [
                'test' => 'actualvalue',
            ],
        ];
        $this->assertEquals($expected, $config);
    }

    /**
     * Tests that you can use before nad after ruls based on filepath filter.
     */
    public function testBeforeAfterStatementWithPath()
    {
        $content = <<<'YAML'
---
name: test
---
test: 'test'
YAML;
        mkdir($this->getConfigDirectory().'/test');
        file_put_contents($this->getFilePath('test/config.yml'), $content);

        $content = <<<'YAML'
---
name: test2
before: 'test/*'
---
test: 'should not overwrite'
YAML;
        mkdir($this->getConfigDirectory().'/test2');
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            10 => [
                'test' => 'test',
            ],
        ];
        $this->assertEquals($expected, $config);

        $content = <<<'YAML'
---
name: test3
after: 'test/*#test'
---
test: 'overwrite'
YAML;
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $config = $transformer->transform();

        $expected = [
            10 => [
                'test' => 'overwrite',
            ],
        ];
        $this->assertEquals($expected, $config);
    }

    /**
     * Tests that an exception is correctly thrown when a circular dependency is present.
     * This means when two YAML documents are stated as both becoming before (or after)
     * the other.
     */
    public function testCircularDependency()
    {
        $content = <<<'YAML'
---
name: first
before: second
---
test: test

---
name: second
before: first
---
test: test
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $transformer = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);

        $this->expectException(CircularDependencyException::class);
        $transformer->transform();
    }

    /**
     * Tests that single only/except statements are applied and that documents are excluded
     * or included correctly.
     */
    public function testSingleOnlyExceptStatements()
    {
        $content = <<<YAML
---
name: 'test'
---
test: 'test'

---
name: 'override'
after: 'test'
Only:
  testcase: test
---
test: 'overwritten'

---
name: 'dontapply'
Except:
  testcase: test
---
test: 'not applied'
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $yaml = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $yaml->addRule('testcase', function() {
            return true;
        });
        $merged = $yaml->transform();

        $expected = [10 => ['test' => 'overwritten']];
        $this->assertEquals($expected, $merged);
    }

    /**
     * Tests that multiple only/except statements are applied and that documents are excluded
     * or included correctly.
     */
    public function testMultipleOnlyExceptStatements()
    {
        $content = <<<YAML
---
name: 'test'
---
test: 'test'

---
name: 'override'
after: 'test'
Only:
  testcase1: test
  testcase2: test
---
test: 'overwritten'

---
name: 'dontapply'
Except:
  testcase1: test
  testcase2: test
---
test: 'not applied'
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $yaml = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $yaml->addRule('testcase1', function() {
            return true;
        });
        $yaml->addRule('testcase2', function() {
            return true;
        });
        $merged = $yaml->transform();

        $expected = [10 => ['test' => 'overwritten']];
        $this->assertEquals($expected, $merged);
    }

    /**
     * Test that documents are includes or excluded correctly when an only/except statement
     * fails.
     */
    public function testFailedOnlyExceptStatements()
    {
        $content = <<<YAML
---
name: 'test'
---
test: 'test'

---
name: 'dontapply'
after: 'test'
Only:
  testcase1: false
  testcas2: true
---
test: 'not applied'

---
name: 'override'
Except:
  testcase1: false
---
test: 'overwritten'

---
name: included
Only:
  testcase2: true
---
test2: test2
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $yaml = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $yaml->addRule('testcase1', function() {
            return false;
        });
        $yaml->addRule('testcase2', function() {
            return true;
        });
        $merged = $yaml->transform();

        $expected = [10 => ['test' => 'overwritten', 'test2' => 'test2']];
        $this->assertEquals($expected, $merged);
    }

    public function testIgnoredOnlyExceptRule()
    {
        $content = <<<YAML
---
Only:
  testcase: ignored
---
test1: 'test1'

---
Except:
  testcase: ignored
---
test2: 'test2'
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $yaml = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);
        $yaml->addRule('testcase', function() {
            return false;
        });
        $yaml->ignoreRule('testcase');
        $merged = $yaml->transform();

        $expected = [10 => ['test1' => 'test1', 'test2' => 'test2']];
        $this->assertEquals($expected, $merged);
    }

    public function testKeyValueOnlyExceptStatements()
    {
        $content = <<<YAML
---
Only:
  testcase:
    key: value
    otherkey: othervalue
---
key: 'value'

---
Only:
  testcase:
    key: 'notcorrect'
---
key: 'notcorrect'

---
Except:
  testcase:
    otherkey: othervalue
---
notincluded: notincluded

---
Only:
  testcase:
    arraykey:
      test: test
---
arrays: passed
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $yaml = new YamlTransformer($this->getConfigDirectory(), $this->getFinder(), 10);

        $testData = [
            'key' => 'value',
            'otherkey' => 'othervalue',
            'arraykey' => ['test' => 'test'],
        ];
        $yaml->addRule('testcase', function($key, $value) use($testData) {
            return ($testData[$key] === $value);
        });
        $merged = $yaml->transform();

        $expected = [10 => ['key' => 'value', 'arrays' => 'passed']];
        $this->assertEquals($expected, $merged);
    }
}
