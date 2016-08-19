<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\MergeStrategy\NoKeyConflict;
use micmania1\config\Exceptions\KeyConflictException;

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
        // First we try to add a basic key/value`
        $config = $this->strategy->merge(['test' => 'test'], []);
        $this->assertEquals(['test' => 'test'], $config);

        // Now we try to add the same key/value again. This should not trigger an error
        // as we're not attempting to change the value, but it should not affect $config
        $config = $this->strategy->merge(['test' => 'test'], $config);
        $this->assertEquals(['test' => 'test'], $config);

        // No we'll try to add a different key
        $config = $this->strategy->merge(['newkey' => 'newvalue'], $config);
        $this->assertEquals(['test' => 'test', 'newkey' => 'newvalue'], $config);

        // Now we'll try to add the same key witha different value and it should fail.
        $this->expectException(KeyConflictException::class);
        $config = $this->strategy->merge(['test' => 'modified'], $config);
    }

    public function testAssocArrayKeyClash()
    {
        $base = [
            'myarray' => [
                'test' => 'value',
            ],
        ];

        // First we'll ensure top level keys don't affect lower level keys
        $config = $this->strategy->merge(['test' => 'value'], $base);
        $expected = $base;
        $expected['test'] = 'value';
        $this->assertEquals($expected, $config);

        // Now we'll mere a new key to our existing array
        $config = $this->strategy->merge(['myarray' => ['newkey' => 'newvalue']], $base);
        $expected = $base;
        $expected['myarray']['newkey'] = 'newvalue';
        $this->assertEquals($expected, $config);

        // Now we'll test overwriting a deep key with the same value
        // This should also work fine as there's no change
        $config = $this->strategy->merge(['myarray' => ['test' => 'value']], $base);
        $this->assertEquals($base, $config);

        // Now we'll test overwriting a deep key with a different value where it should break
        $this->expectException(KeyConflictException::class);
        $config = $this->strategy->merge(['myarray' => ['test' => 'modified']], $base);
    }

    public function testArrayKeyClash()
    {
        $base = [
            0 => 'test',
            1 => [
                2 => 'test',
                3 => 'test',
            ],
        ];

        // New key, no clash
        $config = $this->strategy->merge([2 => 'test'], $base);
        $expected = $base;
        $expected[2] = 'test';
        $this->assertEquals($expected, $config);

        // Same value so no clash
        $config = $this->strategy->merge([0 => 'test'], $base);
        $expected = $base;
        $expected[0] = 'test';
        $this->assertEquals($expected, $config);

        // Test a clash in the top level of the array
        $thrown = false;
        try {
            $config = $this->strategy->merge([1 => 'test'], $base);
        } catch (KeyConflictException $e) {
            $thrown = true;
        } finally {
            $this->assertTrue($thrown);
        }

        // Test a clash in the second level of the array
        $thrown = false;
        try {
            $config = $this->strategy->merge([1 => [2 => 'modified']], $base);
        } catch (KeyConflictException $e) {
            $thrown = true;
        } finally {
            $this->assertTrue($thrown);
        }
    }
}
