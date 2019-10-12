<?php

namespace UoBParser\Responses;

use \UoBParser\Entities\Session;

class SessionsResponse extends Response {

    /**
     * @var null|string Timetable website URL.
     */
    public $timetableUrl;

    /**
     * @var null|string Course name.
     */
    public $courseName;

    /**
     * @var null|string Session date range.
     */
    public $dateRange;

    /**
     * @var null|int Estimated term based on current date.
     */
    public $estimatedTerm;

    /**
     * @var  array<Session> Session list.
     */
    public $sessions;

    /**
     * @param null|string $timetableUrl Timetable website URL.
     * @param null|string $courseName Course name.
     * @param null|string $dateRange Session date range.
     * @param array<Session> $sessions Session list.
     */
    public function __construct($timetableUrl, $courseName, $dateRange, $sessions)
    {
        $this->timetableUrl = $timetableUrl;
        $this->courseName = $courseName;
        $this->dateRange = $dateRange;
        $this->estimatedTerm = null;
        $this->sessions = $sessions;
    }

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray()
    {
        return [
            'timetable_url'     => $this->timetableUrl,
            'course_name'       => $this->courseName,
            'date_range'        => $this->dateRange,
            'estimated_term'    => $this->estimatedTerm,
            'sessions'          => \UoBParser\Utils::objectsToArrays($this->sessions),
        ];
    }

}