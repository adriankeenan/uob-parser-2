<?php

namespace UoBParser\Entities;

use \UoBParser\Arrayable;
use \UoBParser\Equatable;

class Session implements Arrayable, Equatable
{
    /**
     * @var string Module name
     */
    public $moduleName;

    /**
     * @var string Session tyle (eg Lecture, Practical etc)
     */
    public $type;

    /**
     * @var integer Day of week as an integer (0 to 4, Monday to Friday)
     */
    public $day;

    /**
     * @var string Start time in HH:MM format
     */
    public $start;

    /**
     * @var string End time in HH:MM format
     */
    public $end;

    /**
     * @var array<string> List of rooms
     */
    public $rooms;

    /**
     * @param string $moduleName Module name
     * @param string $type Session tyle (eg Lecture, Practical etc)
     * @param integer $day Day of week as an integer (0 to 4, Monday to Friday)
     * @param string $start Start time in HH:MM format
     * @param string $end End time in HH:MM format
     * @param array<string> $rooms List of rooms
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
     *      'C003 - CST Teaching Lab' => 'C003' // Luton
     *      'P0.102 - Lab C - General Teaching Lab' => 'P0.102' // Bedford
     *      'MK010 - Electronics and Telecoms Lab' => 'MK010' // Milton Keynes
     * See Session unit tests for more examples
     * @return array<string>
     */
    public function roomsShort()
    {
        // Shorten each room if it looks like the first segment
        // is a room number. These are generally composed of uppercase
        // letters, numbers and fullstops, possibly followed by a
        // single lowercase letter.
        $rooms_short = array_map(function($r){
            $pattern = '/^([A-Z0-9.]+[a-z]{0,1})\s-/';
            $matches = [];
            if (preg_match($pattern, $r, $matches))
                return $matches[1];
            return $r;
        }, $this->rooms);

        // If we end up with duplicated short room strings, this is
        // likely because some of the room strings have a different
        // second part (eg "A100 - A", "A100 - B"). In this case, it's
        // best to return the original values for all rooms so that
        // we're not missing anything. This situation does seem very
        // rare.
        $unique_rooms_long = count(array_unique($this->rooms));
        $unique_rooms_short = count(array_unique($rooms_short));
        if ($unique_rooms_short < $unique_rooms_long)
            return $this->rooms;

        return $rooms_short;
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
            'module' => $this->moduleName,
            'type' => $this->type,
            'day' => $this->day,
            'start' => $this->start,
            'end' => $this->end,
        ];

        // We need to be careful that the types and whitespace do not change, otherwise
        // this could cause a different hash for the same input data.
        return md5(json_encode($values));
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
     * @param Session $other
     * @return bool
     */
    public function equals($other)
    {
        // Check type
        if ($other instanceof static == false)
            throw new \Exception('Cannot compare object of different type');

        // Check session hash. See hash method for equality checking logic.
        if ($this->hash() == $other->hash())
            return true;

        // This is an additional case where sessions can be considered equal
        // due to the fact that module names can sometimes be missing. If all
        // other unique attributes (day, start, end and type) are the same and
        // at least one of the rooms in this session is present in the room list
        // in $other, treat this as a match. This assumes that two different 
        // sessions will never occur in the same room.
        if ($this->day == $other->day &&
            $this->start == $other->start &&
            $this->end == $other->end &&
            $this->type == $other->type &&
            empty(array_intersect($this->rooms, $other->rooms)) == false){
            return true;
        }

        return false;
    }
}