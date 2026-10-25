<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerDay;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerWeek;
use PHPUnit\Framework\TestCase;

class WeeklyPlannerWeekTest extends TestCase
{
    private function createDay(string $date, float $expected, float $actual, float $planned): WeeklyPlannerDay
    {
        $day = new WeeklyPlannerDay(new \DateTimeImmutable($date), $expected, $actual);
        if ($planned > 0.0) {
            $day->addActivity((new PlannedActivity())->setHoursPerDay($planned));
        }

        return $day;
    }

    public function testTotalsAndPercentage(): void
    {
        $week = new WeeklyPlannerWeek([
            $this->createDay('2026-10-19', 8.0, 7.0, 8.0),
            $this->createDay('2026-10-20', 8.0, 0.0, 4.0),
        ]);

        $this->assertSame(16.0, $week->getTotalExpectedHours());
        $this->assertSame(12.0, $week->getTotalPlannedHours());
        $this->assertSame(7.0, $week->getTotalActualHours());
        $this->assertSame(75, $week->getPlannedPercentage());
        $this->assertFalse($week->isOverAllocated());
        $this->assertSame('2026-10-19', $week->getStart()?->format('Y-m-d'));
    }

    public function testOverAllocationAndNoCapacity(): void
    {
        $over = new WeeklyPlannerWeek([$this->createDay('2026-10-19', 8.0, 0.0, 10.0)]);
        $this->assertTrue($over->isOverAllocated());

        $none = new WeeklyPlannerWeek([$this->createDay('2026-10-24', 0.0, 0.0, 0.0)]);
        $this->assertSame(0, $none->getPlannedPercentage());

        $empty = new WeeklyPlannerWeek([]);
        $this->assertNull($empty->getStart());
        $this->assertSame(0.0, $empty->getTotalPlannedHours());
    }
}
