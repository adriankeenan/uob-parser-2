<?php

namespace UoBParser\Entities;

class Department
{
    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
        	'id' 	=> $this->id,
        	'name' 	=> $this->name
        ];
    }
}