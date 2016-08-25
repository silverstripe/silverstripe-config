# Architecture

Configuration can come in many different formats. Each supported format has a related Transformer which is responsible for taking the config in its original format and outputting it in a format understood by the Config class.

When provided with transformers the Config instance will call the ->transform() method on each. This returns an array with a single key representing the transformers priority and value containing the config for that transformer. All transformers are then merged in order of priority into the final merged PHP array.

## Transformer Output

The format accepted by the Config class and output bu the transformer is as follows:

```
// Transformer 1
array(
    10 => array(
        'MyConfig1' => 'value',
        'MyConfig2' => 'othervalue'
    )
)

// Transformer 2
array(
    20 => array(
        'MyConfig2' => 'overwritten'
    )
)
```

The numbers 10 and 20 represent the priority of the transformer's config and is used to sort in relation to other transformers. In the above example, MyConfig2's value would become 'overwritten' as that config has higher priority (20) then the first (10). Having separations between each key allows for transformers to be insert into any location later on.

## Final Merged Configuration

The final configuration is a result of all transformers merged together:

```
array(
    'MyConfig1' => 'value',
    'MyConfig2' => 'overwritten'
)
```

## Data Flow

The entire flow of data is shown below:

```
+---------------+   +---------------+   +---------------+
|               |   |               |   |               |
| Transformer 1 |   | Transformer 2 |   | Transformer 3 |
|               |   |               |   |               |
+-------+-------+   +-------+-------+   +-------+-------+
        |                   |                   |
        |                   |                   |
        |                   |                   |
        |           +-------v-------+           |
        |           |               |           |
        +----------->    Config     <-----------+
                    |               |
                    +-------+-------+
                            |
                            |
                            |
                    +-------v-------+
                    |               |
                    |   Transform   |
                    |               |
                    +-------+-------+
                            |
                            |
                            |
                    +-------v-------+
                    |  Merged PHP   |
                    |     array     |
                    |               |
                    +---------------+
```
