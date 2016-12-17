<?php

namespace UoBParser;

use \DOMDocument;
use \DOMXpath;
use \DateTime;
use \Exception;

class ParserException extends Exception { }

class Parser
{
    public function __construct($extra = null)
    {
        libxml_use_internal_errors(true);
        $this->extra = $extra != null ? $extra : [];
    }

    private function startTimer()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Generate an array based on either valid output data or an exception
     * @param array/exception $data
     * @return array
     */
    public function makeResponse($data)
    {
        $timeTaken = microtime(true) - $this->startTime;

        $isError = $data instanceof ParserException;

        $outputData = array_merge(['response_time' => $timeTaken, 'error' => $isError], $this->extra);

        if (is_array($data)){
            return array_merge($outputData, $data);
        } else if ($isError) {
            return array_merge($outputData, [
                'error_str' =>  $data->getMessage()
            ]);
        }
    }

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
                throw new ParserException('Server response error');
            }

            $sessions = $this->parseSessionDocument($src);

            return $this->makeResponse([
                'sessions'  =>  array_map(function($s){ return $s->toArray(); }, $sessions)
            ]);

        } catch (ParserException $e) {
            return $this->makeResponse($e);
        }

    }

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
                $session = new Entities\Session(
                    $cells->item(0)->nodeValue,
                    $cells->item(1)->nodeValue,
                    $cells->item(2)->nodeValue ,
                    $i,
                    $cells->item(3)->nodeValue,
                    $cells->item(4)->nodeValue,
                    $cells->item(6)->nodeValue,
                    $cells->item(7)->nodeValue
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
                throw new ParserException('Server response error');
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
