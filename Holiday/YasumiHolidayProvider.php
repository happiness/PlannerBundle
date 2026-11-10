<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Holiday;

use App\Configuration\SystemConfiguration;
use App\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;
use Yasumi\Holiday;

/**
 * Public holidays calculated by Yasumi, which ships with Kimai core, so no network access is needed.
 *
 * A user follows their own calendar preference, or the organisation's default calendar from the
 * system settings. Official and bank holidays are days off, observance days (Christmas Eve in
 * Sweden, for example) only when ticked in the system settings.
 */
final class YasumiHolidayProvider implements HolidayProviderInterface
{
    /**
     * @var array<string, array<string, PublicHoliday>>
     */
    private array $years = [];

    public function __construct(
        private readonly SystemConfiguration $configuration,
        private readonly HolidayCalendars $calendars,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * @return string|null the calendar code the user follows, null if they have no public holidays
     */
    public function getCalendar(User $user): ?string
    {
        // the API stores any string as a preference, so "se" or " gb-eng" must still work
        $preference = $this->normalise($user->getPreferenceValue(HolidayCalendars::PREFERENCE));
        if ($preference === strtoupper(HolidayCalendars::NONE)) {
            return null;
        }
        if ($preference !== null && $this->calendars->hasCalendar($preference)) {
            return $preference;
        }

        $default = $this->normalise($this->configuration->find(HolidayCalendars::SETTING_CALENDAR));
        if ($default !== null && $this->calendars->hasCalendar($default)) {
            return $default;
        }

        return null;
    }

    private function normalise(mixed $code): ?string
    {
        if (!\is_string($code) || trim($code) === '') {
            return null;
        }

        return strtoupper(trim($code));
    }

    public function getHolidays(User $user, \DateTimeInterface $begin, \DateTimeInterface $end): array
    {
        $calendar = $this->getCalendar($user);
        if ($calendar === null) {
            return [];
        }

        $from = $begin->format('Y-m-d');
        $until = $end->format('Y-m-d');

        $holidays = [];
        for ($year = (int) $begin->format('Y'); $year <= (int) $end->format('Y'); $year++) {
            foreach ($this->getYear($calendar, $year) as $date => $holiday) {
                if ($date >= $from && $date <= $until) {
                    $holidays[$date] = $holiday;
                }
            }
        }

        return $holidays;
    }

    /**
     * @return array<string, PublicHoliday> the days off of one calendar year, keyed by Y-m-d
     */
    private function getYear(string $calendar, int $year): array
    {
        $locale = $this->translator->getLocale();
        $cacheKey = $calendar . '|' . $year . '|' . $locale;

        if (\array_key_exists($cacheKey, $this->years)) {
            return $this->years[$cacheKey];
        }

        $observances = HolidayCalendars::parseObservances($this->configuration->find(HolidayCalendars::SETTING_OBSERVANCES));
        $calendarName = $this->calendars->getCalendarName($calendar, $locale);

        $days = [];
        foreach ($this->calendars->createProvider($calendar, $year, $locale)?->getHolidays() ?? [] as $holiday) {
            if (!$this->isDayOff($holiday, $observances)) {
                continue;
            }

            $date = $holiday->format('Y-m-d');
            $name = HolidayCalendars::getHolidayName($holiday);

            // two holidays on one day share the cell, e.g. a feast day falling on Easter Monday
            if (\array_key_exists($date, $days)) {
                $name = $days[$date]->getName() . ', ' . $name;
            }

            $days[$date] = new PublicHoliday(new \DateTimeImmutable($date), $name, $calendar, $calendarName, $holiday->getType());
        }
        ksort($days);

        return $this->years[$cacheKey] = $days;
    }

    /**
     * @param list<string> $observances
     */
    private function isDayOff(Holiday $holiday, array $observances): bool
    {
        if (\in_array($holiday->getType(), HolidayCalendars::DAY_OFF_TYPES, true)) {
            return true;
        }

        return $holiday->getType() === Holiday::TYPE_OBSERVANCE && \in_array($holiday->getKey(), $observances, true);
    }
}
