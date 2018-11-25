<?php

namespace UoBParser;

use \DOMDocument;
use \DOMXpath;
use \DateTime;
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
            'response_time' => $timeTaken,
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
     * @return array
     */
    public function getSessions($dept, $course, $level)
    {
        $this->startTimer();

        try {
            //post data to get html string
            $timetable_url = 'https://timetable.beds.ac.uk/sws'.Utils::yearString();
            $timetable_post_url = $timetable_url.'/showtimetable.asp';

            //get the current term (estimated)
            //then get relevant lbxWeeks
            $termWeekRanges = [
                1 => array_merge(range(6, 16), range(20, 23)),
                2 => array_merge(range(24, 30), range(34, 41)),
                3 => array_merge(range(42, 49), range(51, 54))
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
                'idtype'                =>  'id'
            ];

            try
            {
                $client = Utils::makeGuzzle();
                $response = $client->request('POST', $timetable_post_url, ['form_params' => $params]);
                $src = $response->getBody();
            } catch (Exception $e) {
                throw new Error('Server response error', self::ERROR_SERVER_COMMUNICATION);
            }

            $sessions = $this->parseSessionDocument($src);

            return $this->makeResponse([
                'timetable_url' => $timetable_url,
                'sessions' => array_map(function($s){

                    $arr = $s->toArray();

                    // Add fields expected by clients running V1
                    if ($this->version == 1) {
                        $arr['module_code'] = '';
                        $arr['staff'] = [];
                    }

                    return $arr;

                }, $sessions),
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
     * @return array[Entities\Session]
     */
    public function parseSessionDocument($src)
    {
        //check for invalid session
        //page returns 200 on error, so check contents
        if (strpos($src, 'No Such Page') !== false)
            throw new Error('Invalid course details', self::ERROR_COURSE_INVALID);

        $doc = new DOMDocument();
        $doc->loadHTML($src);

        $xpath = new DOMXpath($doc);

        //collection of DOM elements of type 'table' with 'spreadsheet' class
        $tables = $xpath->query("//table[@class='spreadsheet']");

        $sessions = [];

        //iterate through tables
        for ($i = 0; $i < $tables->length; $i++)
        {
            //get table
            $table = $tables->item($i);

            //get rows
            $rows = $table->getElementsByTagName('tr');

            //less than 2 rows for no content or header only
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

            //start from second row
            for ($r = 1; $r < $rows->length; $r++)
            {
                //get row
                $row = $rows->item($r);

                //get collection of cells
                $cells = $row->getElementsByTagName('td');

                //get values

                $moduleName = '';
                if (isset($columnMap['Title'])){
                    $moduleName = ucwords(strtolower($cells->item($columnMap['Title'])->nodeValue));
                    if ($moduleName == chr(0xC2).chr(0xA0))
                        $moduleName = '';
                }

                $type = '';
                if (isset($columnMap['Type']))
                    $type = $cells->item($columnMap['Type'])->nodeValue;

                $day = $i;

                $start = '';
                if (isset($columnMap['Start']))
                    $start = $cells->item($columnMap['Start'])->nodeValue;

                $end = '';
                if (isset($columnMap['End']))
                    $end = $cells->item($columnMap['End'])->nodeValue;

                $rooms = [];
                if (isset($columnMap['Room']))
                    $rooms = explode(',', $cells->item($columnMap['Room'])->nodeValue);

                //build session object
                $session = new Entities\Session(
                    $moduleName,
                    $type,
                    $day,
                    $start,
                    $end,
                    $rooms
                );

                //assume session is new unless duplicate found
                $newSession = true;

                //loop sessions
                foreach ($sessions as $other)
                {
                    if ($other->equals($session)){
                        $other->combine($session);
                        $newSession = false;
                    }
                }

                //add if session is new
                if ($newSession)
                    $sessions[] = $session;
            }
        }

        return $sessions;
    }

    /**
     * Download the metadata javascript file and return a response array
     * containing the courses and departments
     * @return array
     */
    public function getCourses()
    {
        $this->startTimer();

        try {
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
        $depts      = [];
        $courses    = [];
        $levels     = [];

        $src = iconv('UTF-8', 'ISO-8859-1//IGNORE', $src);
        $lines = explode(PHP_EOL, $src);

        foreach ($lines as $line)
        {
            //get everything between brackets
            $s = strpos($line, '(') + 1;
            $e = strripos($line, ')');
            $str = substr($line, $s,  $e - $s);

            //split as CSV
            $l = str_getcsv($str, ',', '"');

            //departments
            if (strstr($line, 'deptarray[i++] = new dept'))
            {
                $depts[] = new Entities\Department($l[2], $l[0]);
            }
            //courses
            else if (strstr($line, 'posarray[i++] = new pos'))
            {
                if (trim($l[1]) == '')
                    continue;

                $courses[] = new Entities\Course($l[2], $l[1], $l[4], $l[3]);
            }
            //levels
            else if (strstr($line, 'posgrouparray[i++] = new posgroup'))
            {
                $levels[] = new Entities\Level($l[0]);
            }
        }

        //set the course count for each department
        foreach ($depts as $department)
        {
            $department->courseCount = 0;

            foreach ($courses as $course)
            {
                if ($course->deptId == $department->id)
                    $department->courseCount++;
            }
        }

        //set the course department using department array
        foreach ($courses as $course)
        {
            foreach ($depts as $department)
            {
                if ($course->deptId == $department->id)
                {
                    $course->department = $department;
                    break;
                }
            }
        }

        if (count($depts) == 0 || count($courses) == 0)
            throw new Error('No data returned', self::ERROR_SERVER_RESPONSE);

        //alpha sort arrays by name
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