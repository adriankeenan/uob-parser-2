<?php

namespace UoBParser;

use \DateTime;
use \Exception;

class Utils
{
    /**
     * Get the current accademic year in string form, used in URLS.
     * Eg '1415' for the 2014/2015 accademic year.
     * @return string
     */
    public static function yearString()
    {
        $d = new DateTime;
        $m = intval($d->format('m'));
        $y = intval($d->format('y'));
        return $m >= 6 ? $y.($y+1) : ($y-1).$y;
    }

    /**
     * Estimates the current term based on previous term dates (which
     * haven't changed much)
     * If between terms, returns the next term
     * @return int
     */
    public static function estimatedTerm()
    {
        //date ranges
        $termRanges = [
            ['term' => 1, 'start' => ['month' => 10, 'date' => 1], 'end' => ['month' => 12, 'date' => 15]],
            ['term' => 2, 'start' => ['month' => 12, 'date' => 15], 'end' => ['month' => 12, 'date' => 31]],
            ['term' => 2, 'start' => ['month' => 1, 'date' => 1], 'end' => ['month' => 3, 'date' => 20]],
            ['term' => 3, 'start' => ['month' => 3, 'date' => 20], 'end' => ['month' => 10, 'date' => 1]]
        ];

        //get current range
        $termNumber = 0;
        $year = (new DateTime())->format('Y');
        foreach ($termRanges as $termRange){
            $start = (new DateTime())->setTime(0, 0, 0)->setDate($year, $termRange['start']['month'], $termRange['start']['date']);
            $end =   (new DateTime())->setTime(0, 0, 0)->setDate($year, $termRange['end']['month'],   $termRange['end']['date']);

            if ($start < new DateTime() && $end > new DateTime()){
                $termNumber = $termRange['term'];
                break;
            }
        }

        if ($termNumber == 0)
            throw new Exception('Unable to determine current term', 1);

        return $termNumber;
    }

    /**
     * Make an HTTP request
     * @param string $url
     * @param string $method
     * @param array $params
     * @return string
     */
    public static function request($url, $method = null, $params = null)
    {
        $result = false;

        if (strtolower($method) == 'get' || $method === null){

            if ($params != null)
                $url = $url.'?'.http_build_query($params);

            $result = file_get_contents($url);

        } else if (strtolower($method) == 'post'){

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'content' => http_build_query($params)
                ]
            ];
            $context    = stream_context_create($options);
            $result     = file_get_contents($url, false, $context);
        }

        //connection error or not 200 response
        if ($result === false || !strpos($http_response_header[0], '200'))
            return false;

        return $result;
    }
}