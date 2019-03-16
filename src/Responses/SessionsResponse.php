<?php

namespace UoBParser\Responses;

use \UoBParser\Entities\Session;

class SessionsResponse extends Response {

    /**
     * @var string Timetable website URL.
     */
    public $timetableUrl;

    /**
     * @var  array<Session> Session list.
     */
    public $sessions;

    /**
     * @param string $timetableUrl Timetable website URL.
     * @param array<Session> $sessions Session list.
     */
    public function __construct($timetableUrl, $sessions)
    {
        $this->timetableUrl = $timetableUrl;
        $this->sessions = $sessions;
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'timetable_url' => $this->timetableUrl,
            'sessions'      => \UoBParser\Utils::objectsToArrays($this->sessions),
        ];
    }

}