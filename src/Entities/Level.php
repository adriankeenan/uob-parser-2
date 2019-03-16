<?php

namespace UoBParser\Entities;

use \UoBParser\Arrayable;

class Level implements Arrayable
{
    /**
     * @var string Level name
     */
    public $name;

    /**
     * @param string $name Level name
     */
    function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'name' => $this->name,
        ];
    }

}