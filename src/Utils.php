<?php

namespace UoBParser;

use \Exception;
use \DateTimeZone;
use \Carbon\CarbonImmutable;
use \UoBParser\Arrayable;

class Utils
{
    /**
     * @var string Timezone that should be used for all dates.
     */
    const TIMEZONE = 'Europe/London';

    /**
     * Get the current time as a Carbon\CarbonImmutable instance.
     * @return CarbonImmutable
     */
    public static function now()
    {
        return CarbonImmutable::now(new DateTimeZone(static::TIMEZONE));
    }

    /**
     * Get the current accademic year in string form, used in URLs.
     * Advance to next year if current month is later or equal to July.
     * Eg '1415' for the 2014/2015 accademic year.
     * @return string
     */
    public static function yearString()
    {
        $now = static::now();

        // Get start year. Assume the academic year started last year
        // unless we're past July 1st.
        $year = intval($now->format('y')) - 1;
        if ($now->month >= 7)
            $year++;

        return sprintf('%s%s', $year, $year + 1);
    }

    /**
     * Estimates the current term based on previous term dates (which
     * haven't changed much)
     * If between terms, returns the next term
     * @return int
     */
    public static function estimatedTerm()
    {
        $now = static::now();
        $year = $now->year;

        // Date ranges from the term dates page: https://www.beds.ac.uk/about-us/our-university/dates
        // As noted in the doc block - if in between terms, return the next term.
        // For this reason, only the end date of each term is needed, because the
        // start date is set to the end date of the previous term.
        $termRanges = [
            [
                'term' => 1,
                'start' => $now->setDate($year, 10, 20)->startOfDay(),
                'end' => $now->setDate($year, 12, 31)->endOfDay(),
            ],
            [
                'term' => 1,
                'start' => $now->setDate($year, 1, 1)->startOfDay(),
                'end' => $now->setDate($year, 1, 24)->endOfDay(),
            ],
            [
                'term' => 2,
                'start' => $now->setDate($year, 1, 25)->startOfDay(),
                'end' => $now->setDate($year, 5, 31)->endOfDay(),
            ],
            [
                'term' => 3,
                'start' => $now->setDate($year, 6, 1)->startOfDay(),
                'end' => $now->setDate($year, 10, 19)->endOfDay(),
            ],
        ];

        foreach ($termRanges as $termRange){
            if ($now->between($termRange['start'], $termRange['end'], true))
                return $termRange['term'];
        }

        throw new Exception('Unable to determine current term');
    }

    /**
     * Returns a Guzzle client instance with default settings applied
     * @return \GuzzleHttp\Client
     */
    public static function makeGuzzle()
    {
        return new \GuzzleHttp\Client([
            'defaults' => [
                'headers' => [
                    'User-Agent' => 'uob-parser-2 (+https://github.com/adriankeenan/uob-parser-2)'
                ]
            ]
        ]);
    }

    /**
     * Convert a list of objects to arrays by calling their `toArray()` method.
     * @param array<Arrayable> $objects
     * @return array<array>
     */
    public static function objectsToArrays($objects)
    {
        return array_map(function($object){
            return $object->toArray();
        }, $objects);
    }
}