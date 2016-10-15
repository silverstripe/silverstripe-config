<?php

use micmania1\config\Transformer\YamlTransformer;
use micmania1\config\ConfigCollection;
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

    /**
     * @return Finder
     */
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
     * Test that we can have an empty file without throwing any errors.
     */
    public function testEmptyFileIgnored()
    {
        file_put_contents($this->getFilePath('empty.yml'), '');

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();
    }

    /**
     * This tests that if a document has no body, the it will not throw an error
     * and will return an empty array with the correct sort order.
     */
    public function testEmptyDocumentIgnored()
    {
        // Now we'll test the same thing again, but the empty document will be mid-file
        $content = <<<'YAML'
first: firstValue

---
name: second
---

---
name: third
---
third: thirdValue
YAML;

        file_put_contents($this->getFilePath('config2.yml'), $content);

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $expected = [
            'first' => 'firstValue',
            'third' => 'thirdValue',
        ];
        $this->assertEquals('firstValue', $collection->get('first'));
        $this->assertEquals('thirdValue', $collection->get('third'));
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

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $expected = [
            'test' => 'test',
            'assoc' => [
                'test' => 'assoctest',
            ],
            'array' => [
                0 => 'arraytest1',
                1 => 'arraytest2',
            ],
        ];

        $this->assertEquals($expected, $collection->get('MyConfig'));
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

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $this->assertEquals('blah', $collection->get('Test'));
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

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $this->assertEquals('overwritten', $collection->get('test'));
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

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $this->assertEquals('actualvalue', $collection->get('test'));
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

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $this->assertEquals('test', $collection->get('test'));

        $content = <<<'YAML'
---
name: test3
after: 'test/*#test'
---
test: 'overwrite'
YAML;
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $transformer->transform();

        $this->assertEquals('overwrite', $collection->get('test'));
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

        $collection = new ConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );

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

        $collection = new ConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $yaml->addRule('testcase', function() {
            return true;
        });
        $yaml->transform();

        $this->assertEquals('overwritten', $collection->get('test'));
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

        $collection = new ConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $yaml->addRule('testcase1', function() {
            return true;
        });
        $yaml->addRule('testcase2', function() {
            return true;
        });
        $yaml->transform();

        $this->assertEquals('overwritten', $collection->get('test'));
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

        $collection = new ConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $yaml->addRule('testcase1', function() {
            return false;
        });
        $yaml->addRule('testcase2', function() {
            return true;
        });
        $yaml->transform();

        $this->assertEquals('overwritten', $collection->get('test'));
        $this->assertEquals('test2', $collection->get('test2'));
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

        $collection = new ConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );
        $yaml->addRule('testcase', function() {
            return false;
        });
        $yaml->ignoreRule('testcase');
        $yaml->transform();

        $this->assertEquals('test1', $collection->get('test1'));
        $this->assertEquals('test2', $collection->get('test2'));
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

        $collection = new ConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder(),
            $collection
        );

        $testData = [
            'key' => 'value',
            'otherkey' => 'othervalue',
            'arraykey' => ['test' => 'test'],
        ];
        $yaml->addRule('testcase', function($key, $value) use($testData) {
            return ($testData[$key] === $value);
        });
        $merged = $yaml->transform();

        $this->assertEquals('value', $collection->get('key'));
        $this->assertEquals('passed', $collection->get('arrays'));
    }
}
