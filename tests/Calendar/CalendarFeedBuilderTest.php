<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Calendar;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedBuilder;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarFeedBuilderTest extends TestCase
{
    private function createBuilder(): CalendarFeedBuilder
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => match ($id) {
            'planner.calendar.name' => 'Planner: ' . $parameters['%name%'],
            'customer' => 'Customer',
            'project' => 'Project',
            'planner.hours_per_day' => 'Hours per day',
            default => $id,
        });

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => $route === 'planner'
            ? 'https://kimai.example.com/en/planner'
            : 'https://kimai.example.com/' . $parameters['_locale'] . '/planner/week/' . $parameters['date']);

        return new CalendarFeedBuilder($translator, $urls);
    }

    private function createActivity(int $id, string $begin, string $end): PlannedActivity
    {
        $activity = (new PlannedActivity())->setBegin(new \DateTime($begin))->setEnd(new \DateTime($end))->setHoursPerDay(6.0);
        $ref = new \ReflectionProperty(PlannedActivity::class, 'id');
        $ref->setValue($activity, $id);

        return $activity;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setAlias('Anna Svensson');
        $user->setLanguage('en');

        return $user;
    }

    /**
     * @return array<string> the unfolded content lines
     */
    private function lines(string $ics): array
    {
        return explode("\r\n", rtrim(str_replace("\r\n ", '', $ics), "\r\n"));
    }

    public function testAnEmptyCalendar(): void
    {
        $ics = $this->createBuilder()->build($this->createUser(), []);

        $this->assertStringEndsWith("\r\n", $ics);
        $lines = $this->lines($ics);
        $this->assertSame('BEGIN:VCALENDAR', $lines[0]);
        $this->assertContains('VERSION:2.0', $lines);
        $this->assertContains('X-WR-CALNAME:Planner: Anna Svensson', $lines);
        $this->assertSame('END:VCALENDAR', end($lines));
        $this->assertNotContains('BEGIN:VEVENT', $lines);
    }

    public function testAPlannedActivityIsAnAllDayEvent(): void
    {
        $customer = (new Customer('Acme, Inc.'));
        $project = (new Project())->setName('Website; phase 2')->setCustomer($customer);
        $activity = $this->createActivity(42, '2026-11-09', '2026-11-13')
            ->setCustomer($customer)
            ->setProject($project)
            ->setHoursPerDay(7.5)
            ->setComment("Sprint review\nwith the client");

        $lines = $this->lines($this->createBuilder()->build($this->createUser(), [$activity], new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('Europe/Stockholm'))));

        $this->assertContains('UID:planner-activity-42@kimai.example.com', $lines);
        $this->assertContains('DTSTAMP:20261102T090000Z', $lines);
        $this->assertContains('DTSTART;VALUE=DATE:20261109', $lines);
        // exclusive end: the day after the last planned day
        $this->assertContains('DTEND;VALUE=DATE:20261114', $lines);
        $this->assertContains('SUMMARY:Website\; phase 2 (7.5h)', $lines);
        $this->assertContains('DESCRIPTION:Customer: Acme\, Inc.\nProject: Website\; phase 2\nHours per day: 7.5\n\nSprint review\nwith the client', $lines);
        $this->assertContains('URL:https://kimai.example.com/en/planner/week/2026-11-09', $lines);
        $this->assertContains('TRANSP:TRANSPARENT', $lines);
    }

    public function testUnsavedActivitiesAreLeftOut(): void
    {
        $activity = (new PlannedActivity())->setBegin(new \DateTime('2026-11-09'))->setEnd(new \DateTime('2026-11-09'));

        $this->assertNotContains('BEGIN:VEVENT', $this->lines($this->createBuilder()->build($this->createUser(), [$activity])));
    }

    public function testHoursWithoutTrailingZeros(): void
    {
        $customer = new Customer('Acme');
        $activity = $this->createActivity(1, '2026-11-09', '2026-11-09')->setCustomer($customer)->setHoursPerDay(8.0);

        $this->assertContains('SUMMARY:Acme (8h)', $this->lines($this->createBuilder()->build($this->createUser(), [$activity])));
    }

    public function testEscape(): void
    {
        $this->assertSame('a\\\\b\;c\,d\ne\nf', CalendarFeedBuilder::escape("a\\b;c,d\r\ne\nf"));
    }

    public function testLongLinesAreFoldedWithoutSplittingCharacters(): void
    {
        $line = 'SUMMARY:' . str_repeat('å', 100);
        $folded = CalendarFeedBuilder::fold($line);

        foreach (explode("\r\n", $folded) as $i => $part) {
            $this->assertLessThanOrEqual(75, \strlen($part));
            $this->assertTrue(mb_check_encoding($part, 'UTF-8'));
            if ($i > 0) {
                $this->assertStringStartsWith(' ', $part);
            }
        }
        $this->assertSame($line, str_replace("\r\n ", '', $folded));
        $this->assertSame('SHORT:line', CalendarFeedBuilder::fold('SHORT:line'));
    }
}
