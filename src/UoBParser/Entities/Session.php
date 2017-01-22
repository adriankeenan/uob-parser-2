<?php

namespace UoBParser\Entities;

use \DateTime;

class Session
{
    public $moduleCode;
    public $moduleName;
    public $type;
    public $day;
    public $start;
    public $end;
    public $rooms;
    public $staff;

    function __construct($moduleCode, $moduleName, $type, $day, $start, $end, $rooms, $staff)
    {
        $this->moduleCode = $moduleCode;
        $this->moduleName = $moduleName;

        $this->type = $type;
        $this->day = $day;
        $this->start = $start;
        $this->end = $end;
        $this->rooms = $rooms;
        $this->staff = $staff;
    }

    /**
     * Get the duration of the session in hours
     * @return int|float
     */
    public function length()
    {
        $dateStart = DateTime::createFromFormat('H:i', $this->start);
        $dateEnd = DateTime::createFromFormat('H:i', $this->end);
        $seconds = $dateEnd->getTimestamp() - $dateStart->getTimestamp();
        $hours = $seconds / 60 /60;
        return $hours;
    }

    /**
     * Format length in to human readible string eg '1 hour', '2.5 hours'
     * @return string
     */
    public function lengthStr()
    {
        return $this->length().' hour'.($this->length() == 1 ? '' : 's');
    }

    /**
     * Determine if session is missing vital information
     * @return bool
     */
    public function isValid()
    {
        return strlen($this->moduleCode) > 0 && strlen($this->moduleName) > 0;
    }

    /**
     * Get a list of rooms containing only the same room ID.
     * Examples include
     *      C003 - CST Teaching Lab => C003 //Luton
     *      P0.102 - Lab C - General Teaching Lab => P0.102 //Bedford
     * @return array
     */
    public function roomsShort()
    {
        return array_map(function($r){
            $pattern = '/^(([a-zA-Z]{1,}[\d]{1,}[a-zA-Z]{0,})|([a-zA-Z])[0-9]{1,}.[0-9]{1,})\s-/';
            $matches = [];
            if (preg_match($pattern, $r, $matches))
                return $matches[1];
            return $r;
        }, $this->rooms);
    }

    /**
     * Add the attributes of another session to this session.
     * This is useful when the same session is listed multiple times but with
     * different room members and staff.
     * @param object $other
     * @return object
     */
    public function combine($other)
    {
        $this->rooms = array_values(array_unique(array_merge($this->rooms, $other->rooms)));
        $this->staff = array_values(array_unique(array_merge($this->staff, $other->staff)));
        return $this;
    }

    /**
     * Returns a hash string of the object derived from attributes which make it unique.
     * This can be used in a client application to determine if this session is
     * equal to one that exists in the cache.
     * @return string
     */
    public function hash()
    {
        $attrKeys = ['module_code', 'type', 'start', 'end', 'day'];
        $attrVals = array_intersect_key(get_object_vars($this), array_flip($attrKeys));
        $attrVals = array_values($attrVals);
        return md5(implode('', $attrVals));
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'module_code'   => $this->moduleCode,
            'module_name'   => $this->moduleName,
            'day'           => $this->day,
            'start'         => $this->start,
            'end'           => $this->end,
            'length'        => $this->length(),
            'length_str'    => $this->lengthStr(),
            'type'          => $this->type,
            'rooms'         => $this->rooms,
            'rooms_short'   => $this->roomsShort(),
            'staff'         => $this->staff,        
            'hash'          => $this->hash(),
            'is_valid'      => $this->isValid()
        ];
    }

    /**
     * Determine whether two sessions are equal.
     * @param object $other
     * @return bool
     */
    public function equals($other)
    {
        return $this->hash() == $other->hash();
    }
}