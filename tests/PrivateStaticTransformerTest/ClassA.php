<?php

namespace SilverStripe\Config\Tests\PrivateStaticTransformerTest;

class ClassA
{
    private static $myString = 'value';

    private static $myArray = [
        'myThing' => 'myValue',
        'myOtherThing',
    ];

    public static $ignoredPublicStatic = 'ignored';

    protected static $ignoredProtectedStatic = 'ignored';

    private $ignoredPrivate = 'ignored';

    protected $ignoredProtected = 'ignored';

    public $ignoredPublic = 'ignored';
}
