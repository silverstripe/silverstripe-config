<?php

namespace SilverStripe\Config\Transformer;

use SilverStripe\Config\Collections\MutableConfigCollectionInterface;

interface TransformerInterface
{
    /**
     * This is responsible for parsing a single yaml file and returning it into a format
     * that Config can understand. Config will then be responsible for turning thie
     * output into the final merged config.
     *
     * @param  MutableConfigCollectionInterface $collection
     * @return MutableConfigCollectionInterface
     */
    public function transform(MutableConfigCollectionInterface $collection);
}
