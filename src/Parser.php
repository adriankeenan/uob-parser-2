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

    private $version;
    private $startTime;

    public function __construct($version=1)
    {
        $this->version = $version;

        libxml_use_internal_errors(true);
    }

    /**
     * Start a timer used for measuring the response time
     * @return null
     */
    private function startTimer()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Generate an array based on either valid output data or an exception
     * @param array $data
     * @return array
     */
    public function makeResponse($data)
    {
        $timeTaken = microtime(true) - $this->startTime;

        $outputData = [
            'api_version' => $this->version,
            'response_time' => floatval(sprintf('%.2f', $timeTaken)),
            'error' => false,
        ];

        return array_merge($outputData, $data);
    }

    /**
     * Download the timetable HTML for the selected course and return a
     * response array containing the sessions
     * @param string $dept Department for course
     * @param string $course Department for course
     * @param string $level Level for course
     * @throws Error
     * @return array
     */
    public function getSessions($dept, $course, $level)
    {
        $this->startTimer();

        try {
            // Build POST URL string
            $url = 'https://timetable.beds.ac.uk/sws'.Utils::yearString().'/showtimetable.asp';

            // Get the current term (estimated)
            $termWeekRanges = [
                1 => array_merge(range(6, 16), range(20, 23)),
                2 => array_merge(range(24, 33), range(37, 41)),
                3 => array_merge(range(42, 49), range(51, 54))
            ];

            // Get relevant lbxWeeks for term. Honestly not really how this gets generated,
            // just using observed values.
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
                'idtype'                =>  'id'
            ];

            try
            {
                $client = Utils::makeGuzzle();
                $response = $client->request('POST', $url, ['form_params' => $params]);
                $src = $response->getBody();
            } catch (Exception $e) {
                throw new Error('Server response error', self::ERROR_SERVER_COMMUNICATION);
            }

            $sessions = $this->parseSessionDocument($src);

            return $this->makeResponse([
                'sessions' => array_map(function($s){

                    $arr = $s->toArray();

                    // Add fields expected by clients running V1
                    if ($this->version == 1) {
                        $arr['module_code'] = '';
                        $arr['staff'] = [];
                    }

                    return $arr;

                }, $sessions)
            ]);
        } catch (Error $e) {
            throw $e;
        } catch (Exception $e) {
            throw new Error('Unexpected error occured', self::ERROR_UNEXPECTED, 500, $e);
        }
    }

    /**
     * Get a list of session objects from the source HTML
     * @param string $src HTLM source
     * @return array<Entities\Session>
     */
    public function parseSessionDocument($src)
    {
        // Check for invalid session. Page returns 200 on error, so check contents.
        if (strpos($src, 'No Such Page') !== false)
            throw new Error('Invalid course details', self::ERROR_COURSE_INVALID);

        $doc = new DOMDocument();
        $doc->loadHTML($src);

        $xpath = new DOMXpath($doc);

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

        return $sessions;
    }

    /**
     * Download the metadata javascript file and return a response array
     * containing the courses and departments
     * @throws Error
     * @return array
     */
    public function getCourses()
    {
        $this->startTimer();

        try {
            // Build generated JavaScript URL
            $url = 'https://timetable.beds.ac.uk/sws'.Utils::yearString().'/js/data_autogen.js';

            try
            {
                $client = Utils::makeGuzzle();
                $response = $client->request('GET', $url);
                $src = $response->getBody();
            } catch (Exception $e) {
                throw new Error('Server communication error', self::ERROR_SERVER_COMMUNICATION);
            }

            $data = $this->parseCourseDocument($src);

            return $this->makeResponse([
                'courses' =>  array_map(function($course){ 
                    $course = $course->toArray();
                    
                    if (isset($_SERVER['SERVER_NAME'])) {
                        $args = [
                            'dept' => $course['department']['id'],
                            'course' => $course['id'],
                            'level' => $course['level'],
                        ];

                        $isHttps = empty($_SERVER['HTTPS']) == false && $_SERVER['HTTPS'] != 'off';

                        $url = $isHttps ? 'https://' : 'http://';
                        $url .= $_SERVER['SERVER_NAME'];
                        $url .= in_array($_SERVER['SERVER_PORT'], [80, 443]) == false ? ':'.$_SERVER['SERVER_PORT'] : '';
                        $url .= rtrim(dirname($_SERVER['PHP_SELF']), '/');
                        $url .= '/sessions?'.http_build_query($args);

                        $course['session_url'] = $url;
                    }

                    return $course;
                }, $data['courses']),
                'departments' =>  $this->objectsToArrays($data['departments']),
                'levels' => $this->objectsToArrays($data['levels']),
            ]);
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
     * @return array
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

        return [
            'courses'       =>  $courses,
            'departments'   =>  $depts,
            'levels'        =>  $levels,
        ];
    }

    private function objectsToArrays(array $objects): array
    {
        return array_map(function($object){ return $object->toArray(); }, $objects);
    }
}