# Usage

Below is a basic example of a SilverStripe configuration:

```
<?php

require 'vendor/autoload.php';

use micmania1\config\Config;
use micmania1\config\Transformer\Yaml;
use Symfony\Component\Finder\Finder;

// Setup Private static transformer
$classes = ['MyClass', 'MyOtherClass'];
$privateStatics = new PrivateStaticTransformer($classes, 0)

// Setup YAML Transformer
$finder = new Finder();
$finder->in('/path/to/site/*/_config')
    ->files()
    ->name('/\.(yml|yaml)$/');
$yaml = new Yaml('/path/to/site', $finder, 10);

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

// Pass the transformers into our config class
$config = new Config($privateStatics, $yaml);
$merged = $config->transform();

print_r($merged);
```


