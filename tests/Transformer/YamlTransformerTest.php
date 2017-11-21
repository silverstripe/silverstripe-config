<?php

namespace SilverStripe\Config\Tests\Transformer;

use org\bovigo\vfs\vfsStreamDirectory;
use SilverStripe\Config\Transformer\YamlTransformer;
use SilverStripe\Config\Collections\MemoryConfigCollection;
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
     * @var vfsStreamDirectory
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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);
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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

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
before: 'test/*#test'
---
test: 'should not overwrite'
YAML;
        mkdir($this->getConfigDirectory().'/test2');
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

        $this->assertEquals('test', $collection->get('test'));

        // this one is kind of moot because if the matching fails, it'll go after anyway...
        $content = <<<'YAML'
---
name: test3
after: 'test/*#test'
---
test: 'overwrite'
YAML;
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

        $this->assertEquals('overwrite', $collection->get('test'));
    }

    public function testBeforeAfterStatementWithNestedPath()
    {
        $content = <<<'YAML'
---
name: test
---
test: 'test'
YAML;
        mkdir($this->getConfigDirectory().'/test');
        mkdir($this->getConfigDirectory().'/test/test1-1');
        file_put_contents($this->getFilePath('test/test1-1/config.yml'), $content);

        $content = <<<'YAML'
---
name: test2
before: 'test1-1/*#test'
---
test: 'should not overwrite'
YAML;
        mkdir($this->getConfigDirectory().'/test2');
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

        $this->assertEquals('test', $collection->get('test'));

        $content = <<<'YAML'
---
name: test3
after: 'test1-1/*#test'
---
test: 'overwrite'
YAML;
        file_put_contents($this->getFilePath('test2/config.yml'), $content);

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $collection->transform([$transformer]);

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

        $collection = new MemoryConfigCollection;
        $transformer = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );

        $this->expectException(CircularDependencyException::class);
        $collection->transform([$transformer]);
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

        $collection = new MemoryConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $yaml->addRule(
            'testcase',
            function () {
                return true;
            }
        );
        $collection->transform([$yaml]);

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

        $collection = new MemoryConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $yaml->addRule(
            'testcase1',
            function () {
                return true;
            }
        );
        $yaml->addRule(
            'testcase2',
            function () {
                return true;
            }
        );
        $collection->transform([$yaml]);

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
test: 'test-original'

---
name: 'test-override'
after: 'test'
Except:
  mustbefalse: true
  mustbetrue: false
---
test: 'test-success'

---
name: 'test-dontapply'
after:
  - 'test'
  - 'test-override'
Only:
  mustbefalse: false
  mustbetrue: false
---
test: 'test-error'

---
name: included
Only:
  mustbetrue: true
---
test2: 'test2-original'

---
name: includedsuccess
after: included
Only:
  mustbetrue:
    - true
    - true
  mustbefalse:
    - false
    - false
Except:
  mustbetrue:
    - false
    - false
  mustbefalse:
    - true
    - true
---
test2: 'test2-success'

---
name: includednotapplied
after: includedsuccess
# Except prevents application here because one except is true
Except:
  mustbefalse:
    - true
    - false
Only:
  mustbetrue: true
---
test2: 'test2-except-error'

---
name: alsoincludednotapplied
after: includedsuccess
# Only prevents application here because one only is false
Except:
  mustbefalse: true
Only:
  mustbetrue:
    - true
    - false
---
test2: 'test2-only-error'

YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $collection = new MemoryConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $yaml->addRule(
            'mustbefalse',
            function ($val) {
                return $val === false;
            }
        );
        $yaml->addRule(
            'mustbetrue',
            function ($val) {
                return $val === true;
            }
        );
        $collection->transform([$yaml]);

        $this->assertEquals('test-success', $collection->get('test'));
        $this->assertEquals('test2-success', $collection->get('test2'));
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

        $collection = new MemoryConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );
        $yaml->addRule(
            'testcase',
            function () {
                return false;
            }
        );
        $yaml->ignoreRule('testcase');
        $collection->transform([$yaml]);

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

        $collection = new MemoryConfigCollection;
        $yaml = new YamlTransformer(
            $this->getConfigDirectory(),
            $this->getFinder()
        );

        $testData = [
            'key' => 'value',
            'otherkey' => 'othervalue',
            'arraykey' => ['test' => 'test'],
        ];
        $yaml->addRule(
            'testcase',
            function ($key, $value) use ($testData) {
                return ($testData[$key] === $value);
            }
        );
        $collection->transform([$yaml]);

        $this->assertEquals('value', $collection->get('key'));
        $this->assertEquals('passed', $collection->get('arrays'));
    }
}
