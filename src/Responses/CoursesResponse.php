<?php

namespace UoBParser\Responses;

use \UoBParser\Entities\Course;
use \UoBParser\Entities\Department;
use \UoBParser\Entities\Level;

class CoursesResponse extends Response {

    /**
     * @var array<Course> Course list
     */
    public $courses;

    /**
     * @var array<Department> Department list
     */
    public $departments;

    /**
     * @var array<Level> Level list
     */
    public $levels;

    /**
     * @param array<Course> $courses Course list
     * @param array<Department> $departments Department list
     * @param array<Level> $levels Level list
     */
    public function __construct($courses, $departments, $levels)
    {
        $this->courses = $courses;
        $this->departments = $departments;
        $this->levels = $levels;
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'courses'       => \UoBParser\Utils::objectsToArrays($this->courses),
            'departments'   => \UoBParser\Utils::objectsToArrays($this->departments),
            'levels'        => \UoBParser\Utils::objectsToArrays($this->levels),
        ];
    }

}