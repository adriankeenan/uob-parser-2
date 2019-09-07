<?php

namespace UoBParser;

use \DOMDocument;
use \DOMXpath;
use \Exception;

class Parser
{
    const ERROR_UNEXPECTED = 'unexpected';
    const ERROR_SERVER_COMMUNICATION = 'server_communication';
    const ERROR_SERVER_RESPONSE = 'server_response_invalid';
    const ERROR_COURSE_INVALID = 'course_invalid';

    public function __construct()
    {
        libxml_use_internal_errors(true);
    }

    /**
     * Download the timetable HTML for the selected course and return a
     * response array containing the sessions
     * @param string $dept Department for course
     * @param string $course Department for course
     * @param string $level Level for course
     * @throws Error
     * @return Responses\SessionsResponse
     */
    public function getSessions($dept, $course, $level)
    {
        try {
            // Build POST URL string
            $timetableUrl = 'https://timetable.beds.ac.uk/sws'.Utils::yearString();
            $timetablePostUrl = $timetableUrl.'/showtimetable.asp';

            // Get relevant lbxWeeks for term. Honestly not really how this gets generated,
            // just using observed values.
            $termWeekRanges = [
                1 => array_merge(range(6, 16), range(20, 23)),
                2 => array_merge(range(24, 32), range(36, 41)),
                3 => array_merge(range(42, 56)),
            ];
            $currentTermWeeks = $termWeekRanges[Utils::estimatedTerm()];

            $params = [
                'ddlDepartments'        =>  $dept,
                'ddlPosGroup'           =>  $level,
                'lbxPos'                =>  $course,
                'lbxWeeks'              =>  implode(';', $currentTermWeeks),
                'ddlWeekdays'           =>  '1-7',
                'ddlPeriods'            =>  '1-34',
                'lstStyle'              =>  'textspreadsheet',
                'btnShowTimetable'      =>  'View Timetable',
                'ObjectClass'           =>  'programme of study',
                'ObjectClassIdentifier' =>  'lbxPos',
                'idtype'                =>  'id',
            ];

            try {
                $client = Utils::makeGuzzle();
                $response = $client->request('POST', $timetablePostUrl, ['form_params' => $params]);
                $src = $response->getBody();
            } catch (Exception $e) {
                throw new Error('Server response error', self::ERROR_SERVER_COMMUNICATION, 0, $e);
            }

            $response = $this->parseSessionDocument($src);
            $response->timetableUrl = $timetableUrl;
            return $response;
        } catch (Error $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Error('Unexpected error occured', self::ERROR_UNEXPECTED, 500, $e);
        }
    }

    /**
     * Get a list of session objects from the source HTML
     * @param string $src HTLM source
     * @return Responses\SessionsResponse
     */
    public function parseSessionDocument($src)
    {
        // Check for invalid session. Page returns 200 on error, so check contents.
        if (strpos($src, 'No Such Page') !== false)
            throw new Error('Invalid course details', self::ERROR_COURSE_INVALID);

        $doc = new DOMDocument();
        $doc->loadHTML($src);

        $xpath = new DOMXpath($doc);

        // Parse timetable details. These queries are really brittle, although in theory
        // not moreso than the session data itself. Just leave these values null if
        // parsing fails.
        $courseName = null;
        $dateRange = null;

        try {
            $query = "//table[@class='header-border-args']//table[@class='header-5-args']//table//td";

            $courseName = $xpath->query($query."//span[@class='header-5-0-5']")->item(0)->nodeValue;
            $dateRange = $xpath->query($query)->item(1)->nodeValue;
        } catch (\Exception $e) {

        }

        // Each table with class spreadsheet contains the timetable for a given day.
        // Should be 5 entries (Monday to Friday).
        $tables = $xpath->query("//table[@class='spreadsheet']");

        $sessions = [];

        // Iterate through tables
        for ($i = 0; $i < $tables->length; $i++)
        {
            // Get table for day
            $table = $tables->item($i);

            // Get rows in table
            // DOMNode::getElementsByTagName() is undocumented,
            // see http://php.net/manual/en/class.domnode.php
            // @phan-suppress-next-line PhanUndeclaredMethod
            $rows = $table->getElementsByTagName('tr');

            // Less than 2 rows for no content or header only
            if ($rows->length < 2)
                continue;

            // The order of the coumns can change. To handle this, build a map of
            // [$columnName => $index], this allows up to lookup later on by
            // getting the index we need from this array.
            $columnMap = [];
            $headerRowCells = $rows->item(0)->getElementsByTagName('td');
            foreach ($headerRowCells as $index => $cell){
                $cellText = $cell->nodeValue;
                $columnMap[$cellText] = $index;
            }

            // Iterate through rows, staring at index 1 (skipping header)
            for ($r = 1; $r < $rows->length; $r++)
            {
                // Get row
                $row = $rows->item($r);

                // Get collection of cells
                $cells = $row->getElementsByTagName('td');

                // Get values from cells

                // Determine the current day based on the table index
                $day = $i;

                $moduleName = '';
                if (isset($columnMap['Title'])){
                    $moduleName = ucwords(strtolower($cells->item($columnMap['Title'])->nodeValue));
                    if ($moduleName == chr(0xC2).chr(0xA0))
                        $moduleName = '';
                }

                $type = '';
                if (isset($columnMap['Type']))
                    $type = $cells->item($columnMap['Type'])->nodeValue;

                $start = '';
                if (isset($columnMap['Start']))
                    $start = $cells->item($columnMap['Start'])->nodeValue;

                $end = '';
                if (isset($columnMap['End']))
                    $end = $cells->item($columnMap['End'])->nodeValue;

                $rooms = [];
                if (isset($columnMap['Room']))
                    $rooms = explode(',', $cells->item($columnMap['Room'])->nodeValue);

                // Create session object
                $session = new Entities\Session(
                    $moduleName,
                    $type,
                    $day,
                    $start,
                    $end,
                    $rooms
                );

                // We need to check whether this session is an extension of an existing
                // session before adding it to the list
                $newSession = true;

                // Search existing sessions for a match, and update the existing session
                // if found
                foreach ($sessions as $other)
                {
                    if ($other->equals($session)){
                        $other->combine($session);
                        $newSession = false;
                    }
                }

                // Add to list if this is a new session
                if ($newSession)
                    $sessions[] = $session;
            }
        }

        return new Responses\SessionsResponse(null, $courseName, $dateRange, $sessions);
    }

    /**
     * Download the metadata javascript file and return a response array
     * containing the courses and departments
     * @throws Error
     * @return Responses\CoursesResponse
     */
    public function getCourses()
    {
        try {
            // Build generated JavaScript URL
            $url = 'https://timetable.beds.ac.uk/sws'.Utils::yearString().'/js/data_autogen.js';

            try
            {
                $client = Utils::makeGuzzle();
                $response = $client->request('GET', $url);
                $src = $response->getBody();
            } catch (Exception $e) {
                throw new Error('Server communication error', self::ERROR_SERVER_COMMUNICATION, 0, $e);
            }

            return $this->parseCourseDocument($src);
        } catch (Error $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Error('Unexpected error occured', self::ERROR_UNEXPECTED, 500, $e);
        }
    }

    /**
     * Get a list of course and session objects from the source javascript
     * metadata
     * @param string $src Source javascript file
     * @return Responses\CoursesResponse
     */
    public function parseCourseDocument($src)
    {
        $depts = [];
        $courses = [];
        $levels = [];

        $src = iconv('UTF-8', 'ISO-8859-1//IGNORE', $src);
        $lines = explode(PHP_EOL, $src);

        // Read the JS file one line at a time. Each line that we are interested in
        // constructs a new JS object and adds it to an array. We'll read each line,
        // determine which type of object is being created, then parse the call to
        // the constructor to get the data.
        foreach ($lines as $line)
        {
            // Get the text between the first and last brackets in the line string
            $firstBracketPosition = strpos($line, '(') + 1;
            $lastBracketPosition = strrpos($line, ')');
            $str = substr($line, $firstBracketPosition,  $lastBracketPosition - $firstBracketPosition);

            // Parse the text as a CSV. This works because the constructor data is
            // always only strings or integers
            $parts = str_getcsv($str, ',', '"');

            // Determine the type of each line and parse as appropriate

            // Line is a department
            if (strstr($line, 'deptarray[i++] = new dept'))
            {
                $depts[] = new Entities\Department($parts[2], $parts[0]);
            }
            // Line is a course
            else if (strstr($line, 'posarray[i++] = new pos'))
            {
                // Some course entries are invalid and have no name, ignore these
                if (trim($parts[1]) == '')
                    continue;

                $courses[] = new Entities\Course($parts[2], $parts[1], $parts[4], $parts[3]);
            }
            // Line is a level
            else if (strstr($line, 'posgrouparray[i++] = new posgroup'))
            {
                $levels[] = new Entities\Level($parts[0]);
            }
        }

        // Set the course count for each department
        foreach ($depts as $department)
        {
            $department->courseCount = 0;

            foreach ($courses as $course)
            {
                if ($course->departmentId == $department->id)
                    $department->courseCount++;
            }
        }

        // Set the department for each course
        foreach ($courses as $course)
        {
            foreach ($depts as $department)
            {
                if ($course->departmentId == $department->id)
                {
                    $course->department = $department;
                    break;
                }
            }
        }

        // Check that the data appears to be valid
        if (count($depts) == 0 || count($courses) == 0)
            throw new Error('No data returned', self::ERROR_SERVER_RESPONSE);

        // Alpha sort arrays by name
        $sortalpha = function($a, $b){
            return strcasecmp($a->name, $b->name);
        };

        usort($depts, $sortalpha);
        usort($courses, $sortalpha);
        usort($levels, $sortalpha);

        return new Responses\CoursesResponse($courses, $depts, $levels);
    }
}