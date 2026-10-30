<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use App\Entity\User;
use App\WorkingTime\Calculator\WorkingTimeCalculator;
use App\WorkingTime\Mode\WorkingTimeMode;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\WorkingDayRanges;
use PHPUnit\Framework\TestCase;

class WorkingDayRangesTest extends TestCase
{
    /**
     * @param array<int> $workingWeekdays ISO weekdays (1 = Monday) with contract hours
     * @return array<string> each range as 'Y-m-d..Y-m-d'
     */
    private function ranges(string $begin, string $end, array $workingWeekdays = [1, 2, 3, 4, 5]): array
    {
        $calculator = $this->createStub(WorkingTimeCalculator::class);
        $calculator->method('getWorkHoursForDay')->willReturnCallback(
            static fn (\DateTimeInterface $day): int => \in_array((int) $day->format('N'), $workingWeekdays, true) ? 28800 : 0
        );
        $mode = $this->createStub(WorkingTimeMode::class);
        $mode->method('getCalculator')->willReturn($calculator);
        $service = $this->createStub(WorkingTimeService::class);
        $service->method('getContractMode')->willReturn($mode);

        $activity = (new PlannedActivity())->setUser(new User())->setBegin(new \DateTime($begin))->setEnd(new \DateTime($end));

        return array_map(
            static fn (array $range): string => $range[0]->format('Y-m-d') . '..' . $range[1]->format('Y-m-d'),
            (new WorkingDayRanges($service))->forActivity($activity)
        );
    }

    public function testFridayToTuesdaySkipsTheWeekend(): void
    {
        $this->assertSame(['2026-11-06..2026-11-06', '2026-11-09..2026-11-10'], $this->ranges('2026-11-06', '2026-11-10'));
    }

    public function testLeadingAndTrailingDaysOffAreDropped(): void
    {
        $this->assertSame(['2026-11-09..2026-11-10'], $this->ranges('2026-11-07', '2026-11-10'));
        $this->assertSame(['2026-11-05..2026-11-06'], $this->ranges('2026-11-05', '2026-11-08'));
    }

    public function testAPartTimeDayOffSplitsTheWeek(): void
    {
        // no Wednesdays
        $this->assertSame(['2026-11-02..2026-11-03', '2026-11-05..2026-11-06'], $this->ranges('2026-11-02', '2026-11-06', [1, 2, 4, 5]));
    }

    public function testSeveralWeeksBecomeOneRangePerWeek(): void
    {
        $this->assertSame(['2026-11-02..2026-11-06', '2026-11-09..2026-11-13'], $this->ranges('2026-11-02', '2026-11-15'));
    }

    public function testNothingToSplit(): void
    {
        // only working days
        $this->assertSame([], $this->ranges('2026-11-02', '2026-11-06'));
        // only days off: planning a weekend on purpose is kept
        $this->assertSame([], $this->ranges('2026-11-07', '2026-11-08'));
        // no working time contract
        $this->assertSame([], $this->ranges('2026-11-06', '2026-11-10', []));
    }

    public function testIncompleteActivitiesAreNotSplit(): void
    {
        $service = $this->createStub(WorkingTimeService::class);
        $ranges = new WorkingDayRanges($service);

        $this->assertSame([], $ranges->forActivity((new PlannedActivity())->setBegin(new \DateTime('2026-11-06'))->setEnd(new \DateTime('2026-11-10'))));
        $this->assertSame([], $ranges->forActivity((new PlannedActivity())->setUser(new User())->setBegin(new \DateTime('2026-11-10'))->setEnd(new \DateTime('2026-11-06'))));
    }
}
