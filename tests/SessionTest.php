<?php

use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
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

        $roomsLong = array_keys($rooms);
        $roomsShort = array_values($rooms);

        // Create test sesssion with set rooms and check that long room names are converted to
        // short room names correctly
        $session = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', $roomsLong);
        $this->assertEquals($roomsLong, $session->rooms);
        $this->assertEquals($roomsShort, $session->roomsShort());

        // Perform the same test with already shortened room names to make sure they are handled
        // corrected (eg not changed).
        $session = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', $roomsShort);
        $this->assertEquals($roomsShort, $session->rooms);
        $this->assertEquals($roomsShort, $session->roomsShort());
    }

    public function testSessionsEqual(): void
    {
        // Attributes the same
        $sessionA = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', ['C010']);
        $sessionB = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', ['A404']);
        $this->assertEquals(true, $sessionA->equals($sessionB));

        // Invalid module, attributes the same, rooms intersect
        $sessionA = new UoBParser\Entities\Session('', 'Lecture', 0, '9:00', '11:00', ['C010']);
        $sessionB = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', ['C010', 'A404']);
        $this->assertEquals(true, $sessionA->equals($sessionB));

        // Invalid module, attributes the same, rooms don't intersect
        $sessionA = new UoBParser\Entities\Session('', 'Lecture', 0, '9:00', '11:00', ['C010']);
        $sessionB = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', ['A404']);
        $this->assertEquals(false, $sessionA->equals($sessionB));

        // Test attributes different
        $sessionA = new UoBParser\Entities\Session('Test module', 'Lecture', 1, '9:00', '11:00', ['C010']);
        $sessionB = new UoBParser\Entities\Session('Test module', 'Lecture', 0, '9:00', '11:00', ['C010']);
        $this->assertEquals(false, $sessionA->equals($sessionB));

        // Test invalid object
        $sessionA = new UoBParser\Entities\Session('Test module', 'Lecture', 1, '9:00', '11:00', ['C010']);
        $sessionB = new \stdClass;
        $this->expectException(Exception::class);
        $sessionA->equals($sessionB);
    }
}