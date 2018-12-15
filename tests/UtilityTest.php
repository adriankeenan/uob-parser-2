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

        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 10, 1, 0, 0, 0, $tz));
        $this->assertSame(1, UoBParser\Utils::estimatedTerm());

        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 12, 31, 23, 59, 59, $tz));
        $this->assertSame(2, UoBParser\Utils::estimatedTerm());

        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 1, 1, 0, 0, 0, $tz));
        $this->assertSame(2, UoBParser\Utils::estimatedTerm());

        CarbonImmutable::setTestNow(CarbonImmutable::create(null, 5, 1, 0, 0, 0, $tz));
        $this->assertSame(3, UoBParser\Utils::estimatedTerm());
    }
}