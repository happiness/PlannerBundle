<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Calendar;

use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Writes a user's planned activities as an iCalendar (RFC 5545) feed.
 *
 * Every planned activity becomes one all-day event spanning its days. Events are transparent,
 * so planned work does not show the user as busy for meetings.
 *
 * @final not final for mocking in tests
 */
class CalendarFeedBuilder
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * @param array<PlannedActivity> $activities
     */
    public function build(User $user, array $activities, ?\DateTimeImmutable $now = null): string
    {
        $locale = $user->getLanguage();
        $stamp = ($now ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $host = parse_url($this->urlGenerator->generate('planner', [], UrlGeneratorInterface::ABSOLUTE_URL), \PHP_URL_HOST);
        $host = \is_string($host) && $host !== '' ? $host : 'kimai';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kimai//PlannerBundle//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($this->translator->trans('planner.calendar.name', ['%name%' => $user->getDisplayName()], 'messages', $locale)),
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($activities as $activity) {
            $begin = $activity->getBegin();
            $end = $activity->getEnd();
            if ($activity->getId() === null || $begin === null || $end === null) {
                continue;
            }

            // time off without hours takes the whole contract day
            $hoursPerDay = $activity->getHoursPerDay();
            $hours = $hoursPerDay === null ? $this->translator->trans('planner.whole_day', [], 'messages', $locale) : self::formatHours($hoursPerDay);
            $title = $activity->isTimeOff() ? $this->translator->trans('planner.time_off', [], 'messages', $locale) : ($activity->getName() ?? '');
            // most calendar apps ignore STATUS in subscribed calendars, so the title says it too
            if ($activity->isTentative()) {
                $title = $this->translator->trans('planner.tentative', [], 'messages', $locale) . ': ' . $title;
            }
            $description = [];
            if ($activity->getCustomer() !== null) {
                $description[] = $this->translator->trans('customer', [], 'messages', $locale) . ': ' . $activity->getCustomer()->getName();
            }
            if ($activity->getProject() !== null) {
                $description[] = $this->translator->trans('project', [], 'messages', $locale) . ': ' . $activity->getProject()->getName();
            }
            $description[] = $this->translator->trans('planner.hours_per_day', [], 'messages', $locale) . ': ' . $hours;
            if ($activity->hasComment()) {
                $description[] = '';
                $description[] = (string) $activity->getComment();
            }

            $url = $this->urlGenerator->generate('planner_week', ['_locale' => $locale, 'date' => $begin->format('Y-m-d')], UrlGeneratorInterface::ABSOLUTE_URL);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:planner-activity-' . $activity->getId() . '@' . $host;
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $begin->format('Ymd');
            // the end of an all-day event is exclusive
            $lines[] = 'DTEND;VALUE=DATE:' . \DateTimeImmutable::createFromInterface($end)->modify('+1 day')->format('Ymd');
            $lines[] = 'SUMMARY:' . self::escape(\sprintf('%s (%s)', $title, $hoursPerDay === null ? $hours : $hours . 'h'));
            $lines[] = 'DESCRIPTION:' . self::escape(implode("\n", $description));
            $lines[] = 'URL:' . $url;
            if ($activity->isTentative()) {
                $lines[] = 'STATUS:TENTATIVE';
            }
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    private static function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    /**
     * Escapes a TEXT value: backslash, semicolon, comma and line breaks.
     */
    public static function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }

    /**
     * Folds a content line into lines of at most 75 octets, never splitting a UTF-8 character.
     */
    public static function fold(string $line): string
    {
        if (\strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $current = '';
        $limit = 75;
        foreach (mb_str_split($line, 1, 'UTF-8') as $char) {
            if (\strlen($current) + \strlen($char) > $limit) {
                $parts[] = $current;
                $current = '';
                // continuation lines start with a space, which counts towards their length
                $limit = 74;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return implode("\r\n ", $parts);
    }
}
