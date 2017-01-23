<?php

namespace UoBParser;

use \DOMDocument;
use \DOMXpath;
use \DateTime;
use \Exception;

class ParserException extends Exception { }

class Parser
{
    private $debug;
    private $startTime;

    public function __construct($debug = false)
    {
        $this->debug = $debug;

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
     * @param array|Exception $data
     * @return array
     */
    public function makeResponse($data)
    {
        $timeTaken = microtime(true) - $this->startTime;

        $isError = $data instanceof Exception;

        $outputData = [
            'response_time' => $timeTaken, 
            'error' => $isError
        ];

        if (is_array($data)){
            $outputData = array_merge($outputData, $data);
        } else if ($isError) {
            $outputData['error_str'] = $data->getMessage();
            if ($this->debug)
                $outputData['exception'] = ExceptionJsonable::fromException($data)->toArray();      
        }

        return $outputData;
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
            $url = 'https://timetable.beds.ac.uk/sws'.Utils::yearString().'/showtimetable.asp';

            //get the current term (estimated)
            //then get relevant lbxWeeks
            $termWeekRanges = [
                0 => range(1, 52),
                1 => range(7, 16),
                2 => range(20, 30),
                3 => range(34, 41)
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
                $response = $client->request('POST', $url, ['form_params' => $params]);
                $src = $response->getBody();
            } catch (Exception $e) {
                throw new ParserException('Server response error', 0, $e);
            }

            $sessions = $this->parseSessionDocument($src);

            return $this->makeResponse([
                'sessions'  =>  array_map(function($s){ return $s->toArray(); }, $sessions)
            ]);

        } catch (ParserException $e) {
            return $this->makeResponse($e);
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
            throw new ParserException('Invalid course details');

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

            //if one row, headers only, no content
            if ($rows->length == 1)
                continue;

            //start from second row
            for ($r = 1; $r < $rows->length; $r++)
            {
                //get row
                $row = $rows->item($r);

                //get collection of cells
                $cells = $row->getElementsByTagName('td');

                //no module code generally means a session does not exist
                if (strlen($cells->item(1)->nodeValue) <= 3)
                    continue;

                //build session object

                //parse input staff from 'lname, fname / lname, fname' to [ 'fname lname', ... ]
                $staffStr = $cells->item(7)->nodeValue;
                $staff = [];
                if (strpos($staffStr, ',') !== false){
                    $staff = array_map(function($s){
                        return trim(implode(' ', array_reverse(explode(',', $s))));
                    }, explode('/', $staffStr));
                }

                $session = new Entities\Session(
                    explode('/', $cells->item(0)->nodeValue)[0],
                    ucwords(strtolower($cells->item(1)->nodeValue)),
                    $cells->item(2)->nodeValue ,
                    $i,
                    $cells->item(3)->nodeValue,
                    $cells->item(4)->nodeValue,
                    explode(',', $cells->item(6)->nodeValue),
                    $staff
                );

                //assume session is new unless duplicate found
                $newSession = true;

                //loop sessions
                foreach($sessions as &$other)
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
                throw new ParserException('Server response error', 0, $e);
            }

            $data = $this->parseCourseDocument($src);

            return $this->makeResponse([
                'courses'       =>  array_map(function($c){ return $c->toArray(); }, $data['courses']),
                'departments'   =>  array_map(function($d){ return $d->toArray(); }, $data['departments'])
            ]);

        } catch (ParserException $e) {
            return $this->makeResponse($e);
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
            throw new ParserException('No data returned');

        //alpha sort arrays by name
        $sortalpha = function($a, $b){
            return strcasecmp($a->name, $b->name);
        };

        usort($depts, $sortalpha);
        usort($courses, $sortalpha);

        return [
            'courses'       =>  $courses,
            'departments'   =>  $depts
        ];
    }
}