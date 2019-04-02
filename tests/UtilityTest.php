<?php

use PHPUnit\Framework\TestCase;

use Carbon\CarbonImmutable;

final class UtilityTest extends TestCase
{
    public function tearDown()
    {
        CarbonImmutable::setTestNow();
    }

    public function testYearString()
    {
        $tz = UoBParser\Utils::TIMEZONE;

        CarbonImmutable::setTestNow(CarbonImmutable::create(2018, 1, 1, 0, 0, 0, $tz));
        $this->assertSame('1718', UoBParser\Utils::yearString());

        CarbonImmutable::setTestNow(CarbonImmutable::create(2018, 6, 30, 0, 0, 0, $tz));
        $this->assertSame('1718', UoBParser\Utils::yearString());

        CarbonImmutable::setTestNow(CarbonImmutable::create(2018, 7, 1, 0, 0, 0, $tz));
        $this->assertSame('1819', UoBParser\Utils::yearString());

        CarbonImmutable::setTestNow(CarbonImmutable::create(2018, 8, 1, 0, 0, 0, $tz));
        $this->assertSame('1819', UoBParser\Utils::yearString());

        CarbonImmutable::setTestNow(CarbonImmutable::create(2018, 12, 31, 0, 0, 0, $tz));
        $this->assertSame('1819', UoBParser\Utils::yearString());
    }

    public function testEstimatedTerm()
    {
        $tz = UoBParser\Utils::TIMEZONE;

        // These tests are based on the following term dates:
        // Term 1: 01/10/18 -> 25/01/19
        // Term 2: 04/02/19 -> 31/05/19
        // Term 3: 10/06/19 -> 20/10/19

        // Inside term 1
        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 10, 20, 0, 0, 0, $tz));
        $this->assertSame(1, UoBParser\Utils::estimatedTerm());

        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 12, 31, 23, 59, 59, $tz));
        $this->assertSame(1, UoBParser\Utils::estimatedTerm());

        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 1, 24, 23, 59, 59, $tz));
        $this->assertSame(1, UoBParser\Utils::estimatedTerm());

        // Between terms 1 and 2
        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 1, 25, 0, 0, 0, $tz));
        $this->assertSame(2, UoBParser\Utils::estimatedTerm());

        // Inside term 2
        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 2, 4, 0, 0, 0, $tz));
        $this->assertSame(2, UoBParser\Utils::estimatedTerm());

        // Between terms 2 and 3
        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 6, 1, 0, 0, 0, $tz));
        $this->assertSame(3, UoBParser\Utils::estimatedTerm());

        // Inside term 3
        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 6, 10, 0, 0, 0, $tz));
        $this->assertSame(3, UoBParser\Utils::estimatedTerm());

        // Between terms 3 and 1
        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 10, 20, 0, 0, 0, $tz));
        $this->assertSame(1, UoBParser\Utils::estimatedTerm());
    }

    public function testObjectsToArrays()
    {
        $arrayables = [];
        for ($i = 0; $i < 3; $i++){
            $arrayables[] = new class($i + 1) implements \UoBParser\Arrayable {
                protected $id;

                public function __construct(int $id)
                {
                    $this->id = $id;
                }

                public function toArray(): array
                {
                    return ['id' => $this->id];
                }
            };
        }

        $expected = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];

        $this->assertSame($expected, \UoBParser\Utils::objectsToArrays($arrayables));
    }
}