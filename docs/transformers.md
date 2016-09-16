# Transformers

## YAML Transformer

```
   +------------+
   |            |     This defines the list of
   |   Finder   |     YAML files to search through
   |            |
   +------+-----+
          |
          |
          |
+---------v---------+                                        +------------------+
|                   |                                        |                  |
|  YAML Transformer +------->  ->transform() +---------------> PHP config keyed |
|                   |               ^                        |   by priority.   |
+-------------------+               |                        |                  |
                                    |                        +------------------+
                                    |
                                    |
                                    +
                                                       Returns an array of yaml documents
                         ->getSortedYamlDocuments()    ordered by before/after dependencies
                                    ^
                                    |
                                    |
                                    |
                                    |
                                    +
                                                       Returns an array of yaml documents
                         ->getNamedYamlDocuments()     keyed by name.
                                    ^
                                    |
                                    |
                                    |
                                    |
                                    +                  Filters out any YAML documents which
                                                       have not passed 'Only' tests or have
                         ->filterByOnlyAndExcept()     failed 'except' tests.
                                    ^
                                    |
                                    |
                                    |
                                    |
                                    +
                                                       Uses topsort to resolve dependencies
                         ->calculateDependencies()     based on before/after statements
```


## Private Static Transformer
```
+--------------------------------+
|                                |
|       Array of Classes         |
| (eg. SilverStripe ClassLoader) |
|                                |
+---------------+----------------+
                |
                |
                |
                v                                                    +----------------------+
 +--------------+---------------+                                    |                      |
 |                              |                                    |   PHP config keyed   |
 |  Private static transformer  +----------> transform() +---------->+     by priority.     |
 |                              |                ^                   |                      |
 +------------------------------+                |                   +----------------------+
                                                 |
                                                 |
                                                 |
                                                 |
                                                 |
                                                 +
                                                                    Uses reflection to lookup
                                          ->getClassConfig()        the private statics of a class
                                                                    and returns them as an array
```
