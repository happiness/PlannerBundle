<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Holiday;

use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use PHPUnit\Framework\TestCase;

class HolidayCalendarsTest extends TestCase
{
    public function testCalendarsAreNamedByCountryAndRegion(): void
    {
        $calendars = (new HolidayCalendars())->getCalendars('en');

        $this->assertSame('Sweden', $calendars['SE']);
        $this->assertSame('United Kingdom › England', $calendars['GB-ENG']);
        $this->assertSame('Germany › North Rhine Westphalia', $calendars['DE-NW']);
    }

    public function testCountryNamesFollowTheLanguage(): void
    {
        $this->assertSame('Storbritannien › England', (new HolidayCalendars())->getCalendarName('GB-ENG', 'sv'));
    }

    public function testUnknownCalendar(): void
    {
        $calendars = new HolidayCalendars();

        $this->assertTrue($calendars->hasCalendar('SE'));
        $this->assertFalse($calendars->hasCalendar('XX'));
        $this->assertSame('XX', $calendars->getCalendarName('XX', 'en'));
        $this->assertNull($calendars->createProvider('XX', 2026, 'en'));
    }

    public function testObservancesNameTheCountriesThatHaveThem(): void
    {
        $observances = (new HolidayCalendars())->getObservances('en');

        $this->assertSame('Christmas Eve (DK, MX, SE)', $observances['christmasEve']);
        $this->assertArrayHasKey('stJohnsEve', $observances);
        $this->assertArrayNotHasKey('christmasDay', $observances);
    }

    public function testParseObservances(): void
    {
        $this->assertSame([], HolidayCalendars::parseObservances(null));
        $this->assertSame([], HolidayCalendars::parseObservances(''));
        $this->assertSame(['christmasEve', 'newYearsEve'], HolidayCalendars::parseObservances('christmasEve, newYearsEve,,christmasEve'));
    }

    public function testEnglishReadersOfAnEnglishCalendarGetTheLocalName(): void
    {
        $names = [];
        foreach ((new HolidayCalendars())->createProvider('GB-ENG', 2026, 'en') ?? [] as $holiday) {
            $names[$holiday->getKey()] = HolidayCalendars::getHolidayName($holiday);
        }

        $this->assertSame('Boxing Day', $names['secondChristmasDay']);
    }

    public function testMissingTranslationFallsBackToTheCountrysLanguage(): void
    {
        $names = [];
        foreach ((new HolidayCalendars())->createProvider('SE', 2026, 'en') ?? [] as $holiday) {
            $names[$holiday->getKey()] = HolidayCalendars::getHolidayName($holiday);
        }

        // Yasumi has no English name for it and would return the key "nationalDay"
        $this->assertSame('Sveriges nationaldag', $names['nationalDay']);
        $this->assertSame('Christmas Day', $names['christmasDay']);
    }

    public function testTheCalendarsCountryVariantWinsOverTheReadersRegion(): void
    {
        $names = [];
        foreach ((new HolidayCalendars())->createProvider('DE', 2026, 'de_AT') ?? [] as $holiday) {
            $names[$holiday->getKey()] = HolidayCalendars::getHolidayName($holiday);
        }

        // Austrian German calls it "Christtag"
        $this->assertSame('1. Weihnachtsfeiertag', $names['christmasDay']);
    }
}
