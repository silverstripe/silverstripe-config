<?php

namespace micmania1\config\Exceptions;

use Exception;

class KeyConflictException extends Exception
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var array
     */
    protected $mine;

    /**
     * @var array
     */
    protected $theirs;

    public function __construct($key, $mine, $theirs)
    {
        $this->key = $key;
        $this->mine = $mine;
        $this->theirs = $theirs;

        parent::__construct($this->__toString());
    }

    public function __toString()
    {
        $error = <<<'ERROR'
Key clash on key '%s'.

Mine:
%s

Thiers:
%s

ERROR;

        return sprintf(
            $error,
            $this->key,
            print_r($this->mine, true),
            print_r($this->theirs, true)
        );
    }
}
