<?php

use PHPUnit\Framework\TestCase;
use micmania1\config\MergeStrategy\Priority;

class PriorityTest extends TestCase
{
    /**
     * @var Priorty
     */
    protected $strategy;

    protected function setUp()
    {
        $this->strategy = new Priority();
    }

    public function testMerge()
    {
        // Test standard override
        $mine = [0 => 'override'];
        $thiers = [0 => 'test'];
        $config = $this->strategy->merge($mine, $thiers);
        $this->assertEquals($mine, $config);

        // Test deep override
        $mine = [0 => ['test' => 'override']];
        $thiers = [0 => ['test' => 'blah']];
        $config = $this->strategy->merge($mine, $thiers);
        $this->assertEquals($mine, $config);

        // Test merge
        $mine = ['test' => 'override', 'test2' => 'newvalue', 0 => []];
        $thiers = ['test' => 'test'];
        $config = $this->strategy->merge($mine, $thiers);
        $this->assertEquals($mine, $config);
    }
}
