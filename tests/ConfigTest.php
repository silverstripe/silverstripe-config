<?php

use micmania1\config\Config;
use micmania1\config\Transformer\Yaml;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class ConfigTest extends TestCase
{
    protected $root;

    protected function setUp()
    {
        $this->root = vfsStream::setup();
    }

    protected function getConfigDirectory()
    {
        $dir = $this->root->url() . '/config';
        if(!is_dir($dir)) {
            mkdir($dir);
        }

        return $dir;
    }

    protected function getFilePath($file)
    {
        return $this->getConfigDirectory() . '/' . $file;
    }

    protected function getFinder()
    {
        $finder = new Finder();
        $finder->in($this->getConfigDirectory())
            ->files()
            ->name('/\.(yml|yaml)$/');

        return $finder;
    }

    /**
     * Tests a bug where a single key/value yaml entry changed from
     * test: blah
     * ..to..
     * test: []
     */
    public function testSingleKey()
    {
        $content = <<<YAML
test: 'blah'
YAML;
        file_put_contents($this->getFilePath('config.yml'), $content);

        $yaml = new Yaml($this->getConfigDirectory(), $this->getFinder(), 10);

        $config = new Config($yaml);
        $merged = $config->transform();

        $this->assertEquals(['test' => 'blah'], $merged);

    }
}

