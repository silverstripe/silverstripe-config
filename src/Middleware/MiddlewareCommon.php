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
}
