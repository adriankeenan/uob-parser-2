<?php

use PHPUnit\Framework\TestCase;

final class ParserSessionsTest extends TestCase
{
    public function setUp()
    {
        // Set parser instance
        $this->parser = new UoBParser\Parser;

        // Load test data
        $this->test_parse_data = file_get_contents(__DIR__.'/data/showtimetable.html');
    }

    public function testInvalidInput(): void
    {
        $this->expectException(UoBParser\Error::class);
        $this->expectExceptionMessage('Invalid course details');

        $this->parser->parseSessionDocument('No Such Page');
    }

    public function testSessions(): void
    {
        $parse_result = $this->parser->parseSessionDocument($this->test_parse_data);
        $sessions = $this->objectsToArrays($parse_result);

        $expected = [
            // This session is merged from two sessions, due to overlapping attributes
            [
                'module_name' => 'Fundamentals Of Computer Studies',
                'day' => 0,
                'day_name' => 'Monday',
                'start' => '9:00',
                'end' => '11:00',
                'length' => 2,
                'length_str' => '2 hours',
                'type' => 'Lecture',
                'rooms' => [
                    'C016 - CST Teaching Lab',
                    'C015 - CST Teaching Lab',
                ],
                'rooms_short' => [
                    'C016',
                    'C015'
                ],
                'hash' => '754be31034bb54be6d936af158e6bfa2',
                'is_valid' => true,
            ],
            // This session is invalid
            [
                'module_name' => '',
                'day' => 4,
                'day_name' => 'Friday',
                'start' => '12:00',
                'end' => '14:30',
                'length' => 2.5,
                'length_str' => '2.5 hours',
                'type' => 'Practical',
                'rooms' => [
                    'C016 - CST Teaching Lab',
                    'P0.102 - Lab C - General Teaching Lab',
                    'MK010 - Electronics and Telecoms Lab',
                    'MK027',
                ],
                'rooms_short' => [
                    'C016',
                    'P0.102',
                    'MK010',
                    'MK027',
                ],
                'hash' => 'ae6f58fb88d75ab1ce9e95f42f8cdd6d',
                'is_valid' => false,
            ],
        ];

        $this->assertEquals($expected, $sessions);
    }

    private function objectsToArrays(array $objects): array
    {
        return array_map(function($object){ return $object->toArray(); }, $objects);
    }
}