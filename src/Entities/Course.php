<?php

namespace UoBParser\Entities;

class Course
{
    public $id;
    public $name;
    public $level;
    public $departmentId;

    /**
     * @var Department|null
     */
    public $department;

    /**
     * @param string $id Course ID
     * @param string $name Course name
     * @param string $level Course level
     * @param string $departmentId Course department ID
     */
    function __construct($id, $name, $level, $departmentId)
    {
        $this->id = $id;
        $this->name = $name;
        $this->level = $level;
        $this->departmentId = $departmentId;
        $this->department = null;
    }

    /**
     * Split name in to chunks
     * Parse the string so that it isn't split by '-' between brackets
     * @return array
     */
    public function nameChunks()
    {
        $name = $this->name;

        $chunks = [];
        $bracketLevel = 0;
        $lastSectionStart = 0;
        foreach (str_split($name) as $index => $char) {
            $atEnd = $index == strlen($name) -1;
            
            if ($char == '(') {
                $bracketLevel += 1;
            } else if ($char == ')') {
                $bracketLevel -= 1;
            } else if (($char == '-' || $atEnd) && $bracketLevel == 0){
                if ($atEnd)
                    $chunk = substr($name, $lastSectionStart);
                else
                    $chunk = substr($name, $lastSectionStart, $index - $lastSectionStart);
                $chunk = trim($chunk, '- ');    
                    
                $chunks[] = $chunk;
                $lastSectionStart = $index;
            }
        }

        if (count($chunks) == 0)
            return [$name];

        return $chunks;
    }

    /**
     * Return an array containing:
     *  - name_start - name of the course
     *  - name_end - all other data (year etc)
     * @return array
     */
    public function names()
    {
        $nameChunks = $this->nameChunks();
       
        return [
            'name_start' => $nameChunks[0],
            'name_end' => implode(' - ', array_slice($nameChunks, 1)),
        ];
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        $names = $this->names();

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'name_start'    => $names['name_start'],
            'name_end'      => $names['name_end'],
            'level'         => $this->level,
            'department'    => $this->department instanceof Department ? $this->department->toArray() : null,
        ];
    }

}