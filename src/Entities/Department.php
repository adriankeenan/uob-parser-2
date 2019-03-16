<?php

namespace UoBParser\Entities;

use \UoBParser\Arrayable;

class Department implements Arrayable
{
    /**
     * @var string Department ID
     */
    public $id;

    /**
     * @var string Department name
     */
    public $name;

    /**
     * @var int Number of courses in the department
     */
    public $courseCount;

    /**
     * @param string $id Department ID
     * @param string $name Department name
     */
    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
        $this->courseCount = -1;
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'course_count'  => $this->courseCount
        ];
    }
}