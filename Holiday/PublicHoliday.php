<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Holiday;

/**
 * A public holiday that one user has off, as returned by a HolidayProviderInterface.
 */
final class PublicHoliday
{
    /**
     * @param string $name the holiday's name in the current language
     * @param string $calendar the calendar code, e.g. "SE" or "GB-ENG"
     * @param string $calendarName the calendar's name in the current language, e.g. "United Kingdom › England"
     * @param string $type the kind of holiday, e.g. "official", "bank" or "observance"
     */
    public function __construct(
        private readonly \DateTimeImmutable $date,
        private readonly string $name,
        private readonly string $calendar,
        private readonly string $calendarName,
        private readonly string $type
    ) {
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCalendar(): string
    {
        return $this->calendar;
    }

    public function getCalendarName(): string
    {
        return $this->calendarName;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
