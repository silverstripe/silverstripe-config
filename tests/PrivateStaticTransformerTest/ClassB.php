<?php

namespace SilverStripe\Config\Tests\PrivateStaticTransformerTest;

class ClassB
{
    private static $myString = 'my other string';

    private static $myArray = [
        'test1',
        'test2'
    ];
}
