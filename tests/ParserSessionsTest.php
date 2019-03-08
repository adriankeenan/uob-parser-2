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

    public function testSessionShortRoomNames(): void
    {
        // Test room list
        $rooms = [
            // Luton campus
            'H204 - Pollux - BSS Project Room'                      => 'H204',
            'H307b - Language Teaching Room'                        => 'H307b',
            'C320 - Microbiology Lab (Research)'                    => 'C320',
            'A012a - White Room'                                    => 'A012a',
            'A316d - Radio Edit Suite'                              => 'A316d',
            'H204 - Pollux - BSS Project Room'                      => 'H204',
            'C110 - CST Network Lab (Elec/Hardware/Robotics Lab)'   => 'C110',
            'H306'                                                  => 'H306',
            // Bedford campus
            'P0.102 - Lab C - General Teaching Lab'                 => 'P0.102',
            'G2.20 - IT Suite'                                      => 'G2.20',
            'P0.110 - Side A'                                       => 'P0.110',
            'Training Suite 2 - Bedford Library'                    => 'Training Suite 2 - Bedford Library',
            'ASC Far Hall'                                          => 'ASC Far Hall',
            'P2.19'                                                 => 'P2.19',
            // Milton Keynes campus
            'MK010 - Electronics and Telecoms Lab'                  => 'MK010',
            'MK007'                                                 => 'MK007',
        ];

        $rooms_long = array_keys($rooms);
        $rooms_short = array_values($rooms);

        // Create test sesssion with set rooms and check that long room names are converted to
        // short room names correctly
        $session = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', $rooms_long);
        $this->assertEquals($rooms_long, $session->rooms);
        $this->assertEquals($rooms_short, $session->roomsShort());

        // Perform the same test with already shortened room names to make sure they are handled
        // corrected (eg not changed).
        $session = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', $rooms_short);
        $this->assertEquals($rooms_short, $session->rooms);
        $this->assertEquals($rooms_short, $session->roomsShort());
    }

    private function objectsToArrays(array $objects): array
    {
        return array_map(function($object){ return $object->toArray(); }, $objects);
    }
}