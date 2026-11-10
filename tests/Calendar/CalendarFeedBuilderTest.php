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
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservation;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservations;
use KimaiPlugin\PlannerBundle\Tests\Reservation\PlanningReservationsTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarFeedBuilderTest extends TestCase
{
    private function createBuilder(?PlanningReservations $reservations = null): CalendarFeedBuilder
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = []): string => match ($id) {
            'planner.calendar.name' => 'Planner: ' . $parameters['%name%'],
            'customer' => 'Customer',
            'project' => 'Project',
            'planner.hours_per_day' => 'Hours per day',
            'planner.time_off' => 'Time off',
            'planner.tentative' => 'Tentative',
            'planner.whole_day' => 'whole day',
            default => $id,
        });

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => $route === 'planner'
            ? 'https://kimai.example.com/en/planner'
            : 'https://kimai.example.com/' . $parameters['_locale'] . '/planner/week/' . $parameters['date']);

        return new CalendarFeedBuilder($translator, $urls, $reservations);
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

        $lines = $this->lines($this->createBuilder()->build($this->createUser(), [$activity], true, new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone('Europe/Stockholm'))));

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

    public function testCommentsStayOutUnlessIncluded(): void
    {
        $activity = $this->createActivity(42, '2026-11-09', '2026-11-09')->setCustomer(new Customer('Acme'))->setComment('Internal: client is late paying');

        $lines = $this->lines($this->createBuilder()->build($this->createUser(), [$activity]));

        $this->assertContains('DESCRIPTION:Customer: Acme\nHours per day: 6', $lines);
        $this->assertSame([], array_filter($lines, static fn (string $line): bool => str_contains($line, 'late paying')));
    }

    public function testTimeOffWithoutHoursIsAWholeDay(): void
    {
        $activity = $this->createActivity(43, '2026-11-09', '2026-11-10')->setTimeOff(true)->setHoursPerDay(null);

        $lines = $this->lines($this->createBuilder()->build($this->createUser(), [$activity]));

        $this->assertContains('SUMMARY:Time off (whole day)', $lines);
        $this->assertContains('DESCRIPTION:Hours per day: whole day', $lines);
    }

    public function testTentativeWorkIsMarkedAsTentative(): void
    {
        $customer = new Customer('Acme');
        $tentative = $this->createActivity(7, '2026-11-09', '2026-11-09')->setCustomer($customer)->setTentative(true);
        $confirmed = $this->createActivity(8, '2026-11-10', '2026-11-10')->setCustomer($customer);

        $lines = $this->lines($this->createBuilder()->build($this->createUser(), [$tentative, $confirmed]));

        $this->assertContains('SUMMARY:Tentative: Acme (6h)', $lines);
        $this->assertContains('SUMMARY:Acme (6h)', $lines);
        $this->assertSame(1, \count(array_keys($lines, 'STATUS:TENTATIVE', true)));
        $this->assertLessThan(array_search('UID:planner-activity-8@kimai.example.com', $lines, true), array_search('STATUS:TENTATIVE', $lines, true));
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

    public function testTimeOff(): void
    {
        $wholeDay = $this->createActivity(1, '2026-11-09', '2026-11-10')->setTimeOff(true)->setHoursPerDay(null);
        $morning = $this->createActivity(2, '2026-11-11', '2026-11-11')->setTimeOff(true)->setHoursPerDay(4.0);

        $lines = $this->lines($this->createBuilder()->build($this->createUser(), [$wholeDay, $morning]));

        $this->assertContains('SUMMARY:Time off (whole day)', $lines);
        $this->assertContains('DESCRIPTION:Hours per day: whole day', $lines);
        $this->assertContains('SUMMARY:Time off (4h)', $lines);
    }

    public function testAReservationIsTitledWithItsName(): void
    {
        $buffer = $this->createActivity(1, '2026-11-09', '2026-11-13')->setReservation('buffer')->setHoursPerDay(1.0);
        $unknown = $this->createActivity(2, '2026-11-09', '2026-11-09')->setReservation('gone');
        $reservations = PlanningReservationsTest::create([new PlanningReservation('buffer', 'Agency buffer')]);

        $lines = $this->lines($this->createBuilder($reservations)->build($this->createUser(), [$buffer, $unknown]));

        $this->assertContains('SUMMARY:Agency buffer (1h)', $lines);
        $this->assertContains('DESCRIPTION:Hours per day: 1', $lines);
        $this->assertContains('SUMMARY:gone (6h)', $lines);
    }
}
