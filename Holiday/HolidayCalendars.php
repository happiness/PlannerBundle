<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Holiday;

use Symfony\Component\Intl\Countries;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * The holiday calendars Yasumi knows (shipped with Kimai core), and the settings that pick one.
 *
 * A calendar is identified by its ISO 3166 code: "SE" for Sweden, "GB-ENG" for England.
 */
class HolidayCalendars
{
    /** organisation-wide default calendar, empty means no public holidays */
    public const SETTING_CALENDAR = 'planner.holiday_calendar';
    /** comma separated Yasumi keys of "observance" days that also count as days off, e.g. "christmasEve" */
    public const SETTING_OBSERVANCES = 'planner.holiday_observances';
    /** per-user override of the default calendar */
    public const PREFERENCE = 'planner_holiday_calendar';
    /** preference value for "this user has no public holidays", whatever the default is */
    public const NONE = 'none';

    /** holiday types that are always days off, observance days only when ticked in the settings */
    public const DAY_OFF_TYPES = [Holiday::TYPE_OFFICIAL, Holiday::TYPE_BANK];

    /**
     * @var array<string, array<string, string>>
     */
    private array $observances = [];

    /**
     * @return array<string, string> calendar names by code, sorted by name
     */
    public function getCalendars(string $locale): array
    {
        $calendars = [];
        foreach (array_keys(Yasumi::getProviders()) as $code) {
            $calendars[$code] = $this->getCalendarName($code, $locale);
        }
        asort($calendars, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $calendars;
    }

    public function hasCalendar(string $code): bool
    {
        return \array_key_exists($code, Yasumi::getProviders());
    }

    /**
     * The country in the current language, followed by Yasumi's region names, e.g. "Storbritannien › England".
     */
    public function getCalendarName(string $code, string $locale): string
    {
        $providers = Yasumi::getProviders();
        if (!\array_key_exists($code, $providers)) {
            return $code;
        }

        $parts = explode('/', $providers[$code]);
        $country = strtoupper(explode('-', $code)[0]);
        if (Countries::exists($country)) {
            $parts[0] = Countries::getName($country, $locale);
        }

        for ($i = 1; $i < \count($parts); $i++) {
            $parts[$i] = (string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $parts[$i]);
        }

        return implode(' › ', $parts);
    }

    /**
     * Every "observance" day of any calendar this year and next, the days an organisation may tick
     * as days off. Labelled with the countries that have them, as Yasumi keys are shared between countries.
     *
     * @return array<string, string> labels by Yasumi key, sorted by label
     */
    public function getObservances(string $locale): array
    {
        if (\array_key_exists($locale, $this->observances)) {
            return $this->observances[$locale];
        }

        $year = (int) date('Y');
        $names = [];
        $countries = [];
        foreach (array_keys(Yasumi::getProviders()) as $code) {
            for ($y = $year; $y <= $year + 1; $y++) {
                foreach ($this->createProvider($code, $y, $locale) ?? [] as $holiday) {
                    if ($holiday->getType() !== Holiday::TYPE_OBSERVANCE) {
                        continue;
                    }
                    $names[$holiday->getKey()] ??= self::getHolidayName($holiday);
                    $countries[$holiday->getKey()][strtoupper(explode('-', $code)[0])] = true;
                }
            }
        }

        $observances = [];
        foreach ($names as $key => $name) {
            $codes = array_keys($countries[$key]);
            sort($codes);
            $observances[$key] = \sprintf('%s (%s)', $name, implode(', ', $codes));
        }
        asort($observances, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $this->observances[$locale] = $observances;
    }

    /**
     * @return list<string>
     */
    public static function parseObservances(mixed $raw): array
    {
        if (!\is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)), static fn (string $key): bool => $key !== '')));
    }

    /**
     * The name in the provider's language. Where Yasumi has no translation it would return the
     * key ("nationalDay"), so a name in any language it does have, usually the country's own, wins.
     */
    public static function getHolidayName(Holiday $holiday): string
    {
        $name = $holiday->getName();
        if ($name === $holiday->getKey() && $holiday->translations !== []) {
            $name = reset($holiday->translations);
        }

        return $name;
    }

    /**
     * Creates the Yasumi provider for a calendar, with the holiday names in the closest language Yasumi has.
     *
     * @return iterable<Holiday>|null null for an unknown calendar or year
     */
    public function createProvider(string $code, int $year, string $locale): ?iterable
    {
        try {
            return Yasumi::createByISO3166_2($code, $year, $this->getYasumiLocale($code, $locale));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Prefers the calendar's country variant of the language, so English readers of a
     * UK calendar get "Boxing Day" rather than "Second Christmas Day", and an Austrian
     * reader of a German calendar gets the German name.
     */
    private function getYasumiLocale(string $code, string $locale): string
    {
        $available = Yasumi::getAvailableLocales();
        $language = strtolower(explode('_', str_replace('-', '_', $locale))[0]);
        $country = strtoupper(explode('-', $code)[0]);

        $candidates = [$language . '_' . $country];
        if (str_contains($locale, '_')) {
            $candidates[] = $locale;
        }
        $candidates[] = $language;

        foreach ($candidates as $candidate) {
            if (\in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return Holiday::DEFAULT_LOCALE;
    }
}
