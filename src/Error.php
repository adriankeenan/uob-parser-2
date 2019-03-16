<?php

namespace UoBParser;

use \Exception;

class Error extends Exception {

    /**
     * @var string|null Exception ID
     */
    protected $id;

    /**
     * @param string|null $message The Exception message to throw
     * @param string|null $id The Exception ID string
     * @param integer $code The Exception code
     * @param Exception|null $previous The previous exception used for the exception chaining
     */
    public function __construct($message = null, $id = null, $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->id = $id;
    }

    /**
     * Get exception ID
     * @return string|null
     */
    public function getID()
    {
        return $this->id;
    }

}