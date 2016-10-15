<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\MergeStrategy\NoKeyConflict;
use micmania1\config\Exceptions\KeyConflictException;
use micmania1\config\ConfigCollection;

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
        $mine = new ConfigCollection(['test' => 'test']);
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals('test', $theirs->get('test')->getValue());

        // No we'll try to add a different key
        $mine = new ConfigCollection(['newkey' => 'newvalue']);
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals(['test', 'newkey'], $theirs->keys());

        // Now we'll try to add the same key witha different value and it should fail.
        $this->expectException(KeyConflictException::class);
        $mine = new ConfigCollection(['test' => 'this key should cause a conflict']);
        $config = $this->strategy->merge($mine, $theirs);
    }

    public function testStringKeyClash()
    {
        $theirs = new ConfigCollection(['myarray' => ['test' => 'value']]);

        // First we'll ensure top level keys don't affect lower level keys
        $mine = new ConfigCollection(['test' => 'value']);
        $config = $this->strategy->merge($mine, $theirs);
        $this->assertEquals(['test' => 'value'], $theirs->get('myarray')->getValue());

        // Now we'll mere a new key to our existing array
        $mine = new ConfigCollection(['myarray' => ['newkey' => 'newvalue']]);
        $config = $this->strategy->merge($mine, $theirs);
        $expected = ['test' => 'value', 'newkey' => 'newvalue'];
        $this->assertEquals($expected, $theirs->get('myarray')->getValue());

        // // Now we'll test overwriting a deep key with a different value where it should break
        $this->expectException(KeyConflictException::class);
        $mine = new ConfigCollection(['myarray' => ['test' => 'modified']]);
        $config = $this->strategy->merge($mine, $theirs);
    }

    public function testNumericKeyClash()
    {
        $base = [
            0 => 'test',
            1 => [
                2 => 'test',
                3 => 'test',
            ],
        ];
        $theirs = new ConfigCollection($base);

        // New key, no clash
        $mine = new ConfigCollection([2 => 'test']);
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals([0, 1, 2], $theirs->keys());

        // Same value so no clash
        $mine = new ConfigCollection([0 => 'test']);
        $this->strategy->merge($mine, $theirs);
        $this->assertEquals([0, 1, 2], $theirs->keys());

        // Test a clash in the top level of the array
        $thrown = false;
        try {
            $mine = new ConfigCollection([1 => 'test']);
            $config = $this->strategy->merge($mine, $theirs);
        } catch (KeyConflictException $e) {
            $thrown = true;
        } finally {
            $this->assertTrue($thrown);
        }

        // Test a clash in the second level of the array
        $thrown = false;
        try {
            $mine = new ConfigCollection([1 => [2 => 'modified']]);
            $config = $this->strategy->merge($mine, $theirs);
        } catch (KeyConflictException $e) {
            $thrown = true;
        } finally {
            $this->assertTrue($thrown);
        }
    }
}
