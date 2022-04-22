<?php

namespace SilverStripe\Config\Middleware;

/**
 * Abstract flag-aware middleware
 */
trait MiddlewareCommon
{
    /**
     * Disable flag
     *
     * @var int
     */
    protected $disableFlag = 0;

    /**
     * Set flag to use to disable this middleware
     *
     * @param int $disableFlag
     * @return $this
     */
    public function setDisableFlag($disableFlag)
    {
        $this->disableFlag = $disableFlag;
        return $this;
    }

    /**
     * Get flag to use to disable this middleware
     *
     * @return int
     */
    public function getDisableFlag()
    {
        return $this->disableFlag;
    }

    /**
     * Check if this middlware is enabled
     *
     * @param int|true $excludeMiddleware
     * @return bool
     */
    protected function enabled($excludeMiddleware)
    {
        if ($excludeMiddleware === true) {
            return false;
        }
        if (!$this->disableFlag) {
            return true;
        }
        return ($excludeMiddleware & $this->disableFlag) !== $this->disableFlag;
    }

    public function __serialize(): array
    {
        return [
            'disableFlag' => $this->disableFlag
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->disableFlag = $data['disableFlag'];
    }

    /**
     * The __serialize() magic method will be automatically used instead of this
     *
     * @return string
     * @deprecated will be removed in 2.0
     */
    public function serialize()
    {
        return json_encode(array_values($this->__serialize() ?? []));
    }

    /**
     * The __unserialize() magic method will be automatically used instead of this almost all the time
     * This method will be automatically used if existing serialized data was not saved as an associative array
     * and the PHP version used in less than PHP 9.0
     *
     * @param string $serialized
     * @deprecated will be removed in 2.0
     */
    public function unserialize($serialized)
    {
        $values = json_decode($serialized ?? '', true);
        foreach (array_keys($this->__serialize() ?? []) as $i => $key) {
            if (!property_exists($this, $key ?? '')) {
                continue;
            }
            $this->{$key} = $values[$i] ?? 0;
        }
    }
}
