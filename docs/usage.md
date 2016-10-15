# Usage

Below is a basic example of a SilverStripe configuration:

```
<?php

require 'vendor/autoload.php';

use micmania1\config\ConfigCollection;
use micmania1\config\Transformer\YamlTransformer;
use micmania1\config\Transformer\PrivateStaticTransformer;
use Symfony\Component\Finder\Finder;

// First thing we do is create our empty config collection
$collection = new ConfigCollection;

// Setup Private static transformer and pass in our collection
$classes = ['MyClass', 'MyOtherClass'];
$privateStatics = new PrivateStaticTransformer($classes, $collection);

// Setup the finder to go fetch our yaml files
$finder = new Finder();
$finder->in('/path/to/site/*/_config')
    ->files()
    ->name('/\.(yml|yaml)$/');

// Pass the finder in to your yaml transformer along with our collection
$yaml = new YamlTransformer('/path/to/site', $finder, $collection);

// Add some rules for only/except statements
$yaml->addRule('classexists', function($class) {
    return class_exists($class);
});
$yaml->addRule('envvarset', function($var) {
    return getenv($var) !== FALSE;
});
$yaml->addRule('constantdefined', function($const) {
    return defined($const);
});

// Now we do the transformation in the order of lower priority first
$privateStatics->transform();
$yaml->transform();

// Now our config collection has is a list of merged config items
var_dump($collection->all());
var_dump($collection->keys());
var_dump($collection->exists('configKey'));
var_dump($collection->get('configKey'));
var_dump($collection->get('configKey')->getValue());
var_dump($collection->get('configKey')->getMetaData());
var_dump($collection->get('configKey')->getHistory());
```
