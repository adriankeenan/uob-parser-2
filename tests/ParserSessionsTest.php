<?php

use PHPUnit\Framework\TestCase;

final class ParserSessionsTest extends TestCase
{
    public function setUp()
    {
        // Set parser instance
        $this->parser = new UoBParser\Parser;

        // Load test data
        $this->testParseData = file_get_contents(__DIR__.'/data/showtimetable.html');
    }

    public function testInvalidInput(): void
    {
        $this->expectException(UoBParser\Error::class);
        $this->expectExceptionMessage('Invalid course details');

        $this->parser->parseSessionDocument('No Such Page');
    }

    public function testCourseName(): void
    {
        $parseResult = $this->parser->parseSessionDocument($this->testParseData);
        $expected = 'Electronic Engineering (With Professional Practice Year) - BENG (Hons) - Ltn - Y4 Oct FT';

        $this->assertEquals($expected, $parseResult->courseName);
    }

    public function testDateRange(): void
    {
        $parseResult = $this->parser->parseSessionDocument($this->testParseData);
        $expected = 'Weeks: 35-34 (27 Aug 2018-25 Aug 2019)';

        $this->assertEquals($expected, $parseResult->dateRange);
    }

    public function testSessions(): void
    {
        $parseResult = $this->parser->parseSessionDocument($this->testParseData);
        $sessions = UoBParser\Utils::objectsToArrays($parseResult->sessions);

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
            // This session has duplicate short rooms
            [
                'module_name' => 'Concepts And Technologies Of Artificial Intelligence',
                'day' => 1,
                'day_name' => 'Tuesday',
                'start' => '9:00',
                'end' => '11:00',
                'length' => 2,
                'length_str' => '2 hours',
                'type' => 'Practical',
                'rooms' => [
                    'A100 - A',
                    'A100 - B',
                    'A310 - CST Teaching Lab'
                ],
                'rooms_short' => [
                    'A100 - A',
                    'A100 - B',
                    'A310 - CST Teaching Lab'
                ],
                'hash' => '0fc8f8027186bc3ddef2a061d0eefd0b',
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
}