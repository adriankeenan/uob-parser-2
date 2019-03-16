<?php

use PHPUnit\Framework\TestCase;

final class ParserCoursesTest extends TestCase
{
    public function setUp()
    {
        // Set parser instance
        $this->parser = new UoBParser\Parser;

        // Load test data
        $this->testParseData = file_get_contents(__DIR__.'/data/data_autogen.js');
    }

    public function testInvalidInput(): void
    {
        $this->expectException(UoBParser\Error::class);
        $this->expectExceptionMessage('No data returned');

        $this->parser->parseCourseDocument('INVALID DATA');
    }

    public function testCourses(): void
    {
        $parseResult = $this->parser->parseCourseDocument($this->testParseData)->toArray();

        $course0 = [
            'id' => 'BSCCS-S/10AA/1/FT',
            'name' => 'Computer Science - BSc (Hons) - Ltn - Year 1 Oct FT',
            'level' => 'Undergraduate Year 1',
            'department' => [
                'id' => 'CM010',
                'name' => 'School of Computer Science and Technology',
                'course_count' => 3,
            ],
            'name_start' => 'Computer Science',
            'name_end' => 'BSc (Hons) - Ltn - Year 1 Oct FT',
        ];
        $this->assertEquals($course0, $parseResult['courses'][0]);

        $course3 = [
            'id' => 'BSSESABF/10AB/3/FT',
            'name' => 'Sport and Physical Education (BSc - With Professional Practice Year) - BSc (Hons) - Bed - Year 3 Oct FT',
            'level' => 'Undergraduate Year 3',
            'department' => [
                'id' => 'BD032',
                'name' => 'School of Sport Science and Physical Activity',
                'course_count' => 1,
            ],
            'name_start' => 'Sport and Physical Education (BSc - With Professional Practice Year)',
            'name_end' => 'BSc (Hons) - Bed - Year 3 Oct FT',
        ];
        $this->assertEquals($course3, $parseResult['courses'][3]);
    }

    public function testDepartments(): void
    {
        $departments = $this->parser->parseCourseDocument($this->testParseData)->toArray()['departments'];

        $expected = [
            [
                'id' => 'CM010',
                'name' => 'School of Computer Science and Technology',
                'course_count' => 3,
            ],
            [
                'id' => 'BD032',
                'name' => 'School of Sport Science and Physical Activity',
                'course_count' => 1,
            ],
        ];

        $this->assertEquals($expected, $departments);
    }

    public function testLevels(): void
    {
        $levels = $this->parser->parseCourseDocument($this->testParseData)->toArray()['levels'];

        $expected = [
            ['name' => 'Apprenticeship'],
            ['name' => 'Foundation Year'],
            ['name' => 'Postgraduate'],
            ['name' => 'Undergraduate Other'],
            ['name' => 'Undergraduate Year 1'],
            ['name' => 'Undergraduate Year 2'],
            ['name' => 'Undergraduate Year 3'],
            ['name' => 'Undergraduate Year 4'],
        ];

        $this->assertEquals($expected, $levels);
    }
}
