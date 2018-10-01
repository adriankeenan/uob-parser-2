<?php

namespace UoBParser;

use \Exception;

class Error extends Exception {

    protected $id;

    public function __construct($message = null, $id = null, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->id = $id;
    }

    /**
     * Get exception ID.
     * @return string|null
     */
    public function getID()
    {
        return $this->id;
    }

}