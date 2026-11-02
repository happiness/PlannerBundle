<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Holiday;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use KimaiPlugin\PlannerBundle\Holiday\YasumiHolidayProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class YasumiHolidayProviderTest extends TestCase
{
    /**
     * @param array<string, string> $settings
     */
    private function createProvider(array $settings = [], string $locale = 'en'): YasumiHolidayProvider
    {
        $loader = $this->createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn($locale);

        return new YasumiHolidayProvider(new SystemConfiguration($loader, $settings), new HolidayCalendars(), $translator);
    }

    private function createUser(?string $calendar = null): User
    {
        $user = new User();
        if ($calendar !== null) {
            $user->setPreferenceValue(HolidayCalendars::PREFERENCE, $calendar);
        }

        return $user;
    }

    /**
     * @return array<string, string> holiday names by date
     */
    private function getNames(YasumiHolidayProvider $provider, User $user, string $begin, string $end): array
    {
        $names = [];
        foreach ($provider->getHolidays($user, new \DateTimeImmutable($begin), new \DateTimeImmutable($end)) as $date => $holiday) {
            $names[$date] = $holiday->getName();
        }

        return $names;
    }

    public function testNoCalendarMeansNoHolidays(): void
    {
        $provider = $this->createProvider();

        $this->assertNull($provider->getCalendar($this->createUser()));
        $this->assertSame([], $provider->getHolidays($this->createUser(), new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2026-12-31')));
    }

    public function testOrganisationDefaultWithoutObservances(): void
    {
        $provider = $this->createProvider([HolidayCalendars::SETTING_CALENDAR => 'SE']);

        $this->assertSame(
            ['2026-12-25' => 'Christmas Day', '2026-12-26' => 'Boxing Day'],
            $this->getNames($provider, $this->createUser(), '2026-12-21', '2026-12-31')
        );
    }

    public function testTickedObservancesAreDaysOff(): void
    {
        $provider = $this->createProvider([
            HolidayCalendars::SETTING_CALENDAR => 'SE',
            HolidayCalendars::SETTING_OBSERVANCES => 'christmasEve,newYearsEve',
        ]);

        $this->assertSame(
            ['2026-12-24' => 'Christmas Eve', '2026-12-25' => 'Christmas Day', '2026-12-26' => 'Boxing Day', '2026-12-31' => 'New Year’s Eve'],
            $this->getNames($provider, $this->createUser(), '2026-12-21', '2026-12-31')
        );
    }

    public function testUserPreferenceOverridesTheDefault(): void
    {
        $provider = $this->createProvider([
            HolidayCalendars::SETTING_CALENDAR => 'SE',
            HolidayCalendars::SETTING_OBSERVANCES => 'christmasEve',
        ]);
        $user = $this->createUser('GB-ENG');

        $this->assertSame('GB-ENG', $provider->getCalendar($user));
        $this->assertSame(
            ['2026-12-25' => 'Christmas Day', '2026-12-26' => 'Boxing Day', '2026-12-28' => 'Boxing Day (substitute day)'],
            $this->getNames($provider, $user, '2026-12-21', '2026-12-31')
        );

        $holiday = $provider->getHolidays($user, new \DateTimeImmutable('2026-12-28'), new \DateTimeImmutable('2026-12-28'))['2026-12-28'];
        $this->assertSame('GB-ENG', $holiday->getCalendar());
        $this->assertSame('United Kingdom › England', $holiday->getCalendarName());
        $this->assertSame('bank', $holiday->getType());
        $this->assertSame('2026-12-28', $holiday->getDate()->format('Y-m-d'));
    }

    public function testUserCanOptOut(): void
    {
        $provider = $this->createProvider([HolidayCalendars::SETTING_CALENDAR => 'SE']);

        $this->assertSame([], $provider->getHolidays($this->createUser(HolidayCalendars::NONE), new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2026-12-31')));
    }

    public function testUnknownPreferenceFallsBackToTheDefault(): void
    {
        $provider = $this->createProvider([HolidayCalendars::SETTING_CALENDAR => 'SE']);

        $this->assertSame('SE', $provider->getCalendar($this->createUser('XX')));
    }

    public function testRangeAcrossYears(): void
    {
        $provider = $this->createProvider([HolidayCalendars::SETTING_CALENDAR => 'SE']);

        $this->assertSame(
            ['2027-01-01' => 'New Year’s Day'],
            $this->getNames($provider, $this->createUser(), '2026-12-28', '2027-01-03')
        );
    }

    public function testNamesFollowTheLanguage(): void
    {
        $provider = $this->createProvider([HolidayCalendars::SETTING_CALENDAR => 'GB-ENG'], 'sv');

        $holidays = $provider->getHolidays($this->createUser(), new \DateTimeImmutable('2026-12-25'), new \DateTimeImmutable('2026-12-26'));
        $this->assertSame('juldagen', $holidays['2026-12-25']->getName());
        $this->assertSame('Storbritannien › England', $holidays['2026-12-25']->getCalendarName());
    }

    public function testCalendarCodesIgnoreCaseAndSpaces(): void
    {
        $provider = $this->createProvider([HolidayCalendars::SETTING_CALENDAR => ' se']);

        $this->assertSame('SE', $provider->getCalendar($this->createUser()));
        $this->assertSame('GB-ENG', $provider->getCalendar($this->createUser('gb-eng')));
        $this->assertNull($provider->getCalendar($this->createUser('NONE')));
    }
}
