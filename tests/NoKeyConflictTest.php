<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\MergeStrategy\NoKeyConflict;
use micmania1\config\Exceptions\KeyConflictException;
use micmania1\config\ConfigCollection;
use micmania1\config\ConfigItem;

class NoKeyConflictTest extends TestCase
{
    /**
     * @var NoKeyConflict
     */
    protected $strategy;

    protected function setUp()
    {
        $this->strategy = new NoKeyConflict();
    }

    public function testBasicKeyClash()
    {
        $theirs = new ConfigCollection([]);

        // First we try to add a basic key/value`
        $mine = new ConfigCollection;
        $mine->set('test', (new ConfigItem('value')));
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals('value', $theirs->get('test')->getValue());

        // No we'll try to add a different key
        $mine = new ConfigCollection;
        $mine->set('newkey', new ConfigItem('newvalue'));
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals(['test', 'newkey'], $theirs->keys());

        // Now we'll try to add the same key witha different value and it should fail.
        $this->expectException(KeyConflictException::class);
        $mine = new ConfigCollection;
        $mine->set('test', new ConfigItem('duplicate key'));
        $config = $this->strategy->merge($mine, $theirs);
    }

    public function testStringKeyClash()
    {
        $theirs = new ConfigCollection;
        $theirs->set('myarray', new ConfigItem(['test' => 'value']));

        // First we'll ensure top level keys don't affect lower level keys
        $mine = new ConfigCollection;
        $mine->set('test', (new ConfigItem('value')));
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals(['test' => 'value'], $theirs->get('myarray')->getValue());

        // Now we'll mere a new key to our existing array
        $mine = new ConfigCollection(['myarray' => ['newkey' => 'newvalue']]);
        $mine->set('myarray', new ConfigItem(['newkey' => 'newvalue']));
        $config = $this->strategy->merge($mine, $theirs);
        $expected = ['test' => 'value', 'newkey' => 'newvalue'];
        $this->assertEquals($expected, $theirs->get('myarray')->getValue());

        // // Now we'll test overwriting a deep key with a different value where it should break
        $this->expectException(KeyConflictException::class);
        $mine = new ConfigCollection;
        $mine->set('myarray', new ConfigItem(['test' => 'modified']));
        $config = $this->strategy->merge($mine, $theirs);
    }

    public function testNumericKeyClash()
    {
        $theirs = new ConfigCollection;
        $theirs->set(0, new ConfigItem('test'));
        $theirs->set(1, new ConfigItem([2 => 'test', 3 => 'test']));

        // New key, no clash
        $mine = new ConfigCollection;
        $mine->set(2, new ConfigItem('test'));
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals([0, 1, 2], $theirs->keys());

        // Same value so no clash
        $mine = new ConfigCollection;
        $mine->set(0, new ConfigItem('test'));
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals([0, 1, 2], $theirs->keys());

        // Test a clash in the top level of the array
        $thrown = false;
        try {
            $mine = new ConfigCollection;
            $mine->set(1, new ConfigItem('test'));
            $config = $this->strategy->merge($mine, $theirs);
        } catch (KeyConflictException $e) {
            $thrown = true;
        } finally {
            $this->assertTrue($thrown);
        }

        // Test a clash in the second level of the array
        $thrown = false;
        try {
            $mine = new ConfigCollection;
            $mine->set(1, new ConfigItem([2 => 'modified']));
            $config = $this->strategy->merge($mine, $theirs);
        } catch (KeyConflictException $e) {
            $thrown = true;
        } finally {
            $this->assertTrue($thrown);
        }
    }
}
