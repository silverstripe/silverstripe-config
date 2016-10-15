# Architecture

Configuration can come in many different formats. Each supported format has a related Transformer which is responsible for taking the config in its original format and merging it into an instance of `ConfigCollectionInterface`. 

## Config Collection

The merged config is an instance of `ConfigCollectionInterface` which is made up of many `ConfigItemInterface`'s.

As the config collection is passed through transformers, its content is updated with new `ConfigItem`'s containing the value of a given key as well as some meta data about where the key came from. This metadata includes the tranformer which updates the value of the key and where the key came from.

## Merge Priority

The priority in which transformers are merged is implied by the order in which `->transform()` is called on each transformer. The lower priority items are called first and may be overwritten by later transformations.

## Data Flow

The data flow is shown below:

```
           +-----------------+
           |                 |
           |      Config     |
           |    Collection   |
           |                 |
           +--------+--------+
                    |
                    |
                    v
+-------------------+--------------------+
|                                        |
|             Transformer 1              |
|                                        |
+-------------------+--------------------+
                    |
                    |
                    v
+-------------------+--------------------+
|                                        |
|             Transformer 2              |
|                                        |
+-------------------+--------------------+
                    |
                    |
                    v
+-------------------+--------------------+
|                                        |
|             Transformer 3              |
|                                        |
+-------------------+--------------------+
                    |
                    |
                    v
           +--------+--------+
           |                 |
           |      Config     |
           |    Collection   |
           |                 |
           +-----------------+
```
