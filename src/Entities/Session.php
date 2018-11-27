<?php

namespace UoBParser\Entities;

class Session
{
    public $moduleName;
    public $type;
    public $day;
    public $start;
    public $end;
    public $rooms;

    /**
     * @param string $moduleName Module name
     * @param string $type Session tyle (eg Lecture, Practical etc)
     * @param integer $day Day of week as an integer (0 to 4, Monday to Friday)
     * @param string $start Start time in HH:MM format
     * @param string $end  End time in HH:MM format
     * @param array $rooms List of rooms
     */
    function __construct($moduleName, $type, $day, $start, $end, $rooms)
    {
        $this->moduleName = $moduleName;
        $this->type = $type;
        $this->day = $day;
        $this->start = $start;
        $this->end = $end;
        $this->rooms = $rooms;
    }

    /**
     * Get the day of week as a string
     * @return string
     */
    public function dayName()
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        return $days[$this->day];
    }

    /**
     * Get the duration of the session in hours
     * @return int|float
     */
    public function length()
    {
        $startParts = explode(':', $this->start);
        $startMinutes = $startParts[0] * 60 + $startParts[1];

        $endParts = explode(':', $this->end);
        $endMinutes = $endParts[0] * 60 + $endParts[1];

        return ($endMinutes - $startMinutes) / 60;
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
        return empty($this->moduleName) == false;
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
            $pattern = '/^([A-Z0-9.]+)\s-/';
            $matches = [];
            if (preg_match($pattern, $r, $matches))
                return $matches[1];
            return $r;
        }, $this->rooms);
    }

    /**
     * Add the attributes of another session to this session.
     * This is useful when the same session is listed multiple times but with
     * different rooms.
     * @param Session $other
     * @return Session This instance
     */
    public function combine(Session $other)
    {
        if (strlen($this->moduleName) == 0)
            $this->moduleName = $other->moduleName;

        $this->rooms = array_values(array_unique(array_merge($this->rooms, $other->rooms)));
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
        $values = [
            $this->moduleName,
            $this->type,
            $this->day,
            $this->start,
            $this->end,
        ];

        // Potential for collissions - consider serialising as JSON before hashing
        return md5(implode('', $values));
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'module_name'   => $this->moduleName,
            'day'           => $this->day,
            'day_name'      => $this->dayName(),
            'start'         => $this->start,
            'end'           => $this->end,
            'length'        => $this->length(),
            'length_str'    => $this->lengthStr(),
            'type'          => $this->type,
            'rooms'         => $this->rooms,
            'rooms_short'   => $this->roomsShort(),
            'hash'          => $this->hash(),
            'is_valid'      => $this->isValid()
        ];
    }

    /**
     * Determine whether two sessions are considered equal.
     * @param object $other
     * @return bool
     */
    public function equals($other)
    {
        // Check for equality using standard attributes (day, times, type) and either
        // the module (which may be blank) or room intersection, as two different
        // sessions wont be happening in the same room.

        // Check for same day, type, start, end
        if ($this->day != $other->day ||
            $this->start != $other->start ||
            $this->end != $other->end ||
            $this->type != $other->type){
            return false;
        }

        // Check for same module
        if ($this->moduleName == $other->moduleName)
            return true;

        // Check for room
        if (empty(array_intersect($this->rooms, $other->rooms)) == false)
            return true;

        return false;
    }
}