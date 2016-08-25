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
|                   |               +                        |   by priority.   |
+-------------------+               |                        |                  |
                                    |                        +------------------+
                                    |
                                    |
                                    v
                                                       Returns an array of yaml documents
                         ->getSortedYamlDocuments()    ordered by before/after dependencies
                                    +
                                    |
                                    |
                                    |
                                    |
                                    v
                                                       Returns an array of yaml documents
                         ->getNamedYamlDocuments()     keyed by name.
                                    +
                                    |
                                    |
                                    |
                                    |
                                    v                  Filters out any YAML documents which
                                                       have not passed 'Only' tests or have
                         ->filterByOnlyAndExcept()     failed 'except' tests.
                                    +
                                    |
                                    |
                                    |
                                    |
                                    v
                                                       Uses topsort to resolve dependencies
                         ->calculateDependencies()     based on before/after statements
```
