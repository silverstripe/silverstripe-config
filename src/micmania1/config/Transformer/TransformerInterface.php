<?php

namespace micmania1\config\Transformer;

interface TransformerInterface
{
    /**
     * This is responsible for parsing a single yaml file and returning it into a format
     * that Config can understand. Config will then be responsible for turning thie
     * output into the final merged config.
     *
     * @return \micmania1\config\ConfigCollectionInterface
     */
    public function transform();

}
