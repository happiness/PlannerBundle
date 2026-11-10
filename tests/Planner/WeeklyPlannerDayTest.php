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
use KimaiPlugin\PlannerBundle\Holiday\PublicHoliday;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerDay;
use PHPUnit\Framework\TestCase;

class WeeklyPlannerDayTest extends TestCase
{
    public function testTentativeHoursAreCountedApart(): void
    {
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-02'), 8.0);
        $day->addActivity((new PlannedActivity())->setHoursPerDay(6.0));
        $day->addActivity((new PlannedActivity())->setHoursPerDay(4.0)->setTentative(true));

        $this->assertSame(6.0, $day->getPlannedHours());
        $this->assertSame(4.0, $day->getTentativeHours());
        $this->assertSame(75, $day->getPlannedPercentage());
        // tentative work never makes a day overbooked, only confirmed work does
        $this->assertFalse($day->isOverAllocated());
        $this->assertTrue($day->isUnderAllocated());
        $this->assertCount(2, $day->getActivities());
    }

    public function testGettersAndAllocationStatus(): void
    {
        $date = new \DateTimeImmutable('2026-09-08');
        $day = new WeeklyPlannerDay($date, 8.0, 7.5);

        $this->assertSame($date, $day->getDate());
        $this->assertSame(8.0, $day->getExpectedHours());
        $this->assertSame(7.5, $day->getActualHours());
        $this->assertSame(0, $day->getPlannedPercentage());
        $this->assertTrue($day->isWorkingDay());
        $this->assertTrue($day->isUnderAllocated());
        $this->assertFalse($day->isOverAllocated());
        $this->assertFalse($day->isBalanced());

        $act1 = new PlannedActivity();
        $act1->setHoursPerDay(4.0);
        $day->addActivity($act1);

        $this->assertSame(4.0, $day->getPlannedHours());
        $this->assertSame(50, $day->getPlannedPercentage());
        $this->assertTrue($day->isUnderAllocated());

        $act2 = new PlannedActivity();
        $act2->setHoursPerDay(4.0);
        $day->addActivity($act2);

        $this->assertSame(8.0, $day->getPlannedHours());
        $this->assertSame(100, $day->getPlannedPercentage());
        $this->assertTrue($day->isBalanced());
        $this->assertFalse($day->isUnderAllocated());
        $this->assertFalse($day->isOverAllocated());

        $act3 = new PlannedActivity();
        $act3->setHoursPerDay(1.0);
        $day->addActivity($act3);

        $this->assertSame(9.0, $day->getPlannedHours());
        $this->assertSame(113, $day->getPlannedPercentage());
        $this->assertTrue($day->isOverAllocated());
        $this->assertFalse($day->isBalanced());
        $this->assertFalse($day->isUnderAllocated());

        $weekendDay = new WeeklyPlannerDay(new \DateTimeImmutable('2026-09-13'), 0.0, 0.0);
        $this->assertSame(0, $weekendDay->getPlannedPercentage());
        $weekendDay->addActivity($act1);
        $this->assertSame(0, $weekendDay->getPlannedPercentage());
    }

    public function testTimeOffReducesCapacityInsteadOfCountingAsPlanned(): void
    {
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-06'), 8.0);
        $day->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(4.0));

        $this->assertTrue($day->hasTimeOff());
        $this->assertSame(4.0, $day->getTimeOffHours());
        $this->assertSame(4.0, $day->getCapacityHours());
        $this->assertSame(8.0, $day->getExpectedHours());
        $this->assertSame(0.0, $day->getPlannedHours());
        $this->assertFalse($day->isDayOff());
        $this->assertTrue($day->isUnderAllocated());

        $day->addActivity((new PlannedActivity())->setHoursPerDay(4.0));

        $this->assertSame(4.0, $day->getPlannedHours());
        $this->assertSame(100, $day->getPlannedPercentage());
        $this->assertTrue($day->isBalanced());
        $this->assertFalse($day->isOverAllocated());
    }

    public function testTimeOffWithoutHoursTakesTheWholeContractDay(): void
    {
        $timeOff = (new PlannedActivity())->setTimeOff(true)->setHoursPerDay(null);

        $friday = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-06'), 6.0);
        $friday->addActivity($timeOff);

        $this->assertSame(6.0, $friday->getHoursOf($timeOff));
        $this->assertSame(6.0, $friday->getTimeOffHours());
        $this->assertSame(0.0, $friday->getCapacityHours());
        $this->assertTrue($friday->isDayOff());
        $this->assertTrue($friday->isBalanced());
        $this->assertSame(0, $friday->getPlannedPercentage());

        // planning work on a day off is overbooking
        $friday->addActivity((new PlannedActivity())->setHoursPerDay(2.0));
        $this->assertTrue($friday->isOverAllocated());
    }

    public function testTimeOffNeverExceedsTheContractDay(): void
    {
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-06'), 8.0);
        $day->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(6.0));
        $day->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(6.0));

        $this->assertSame(8.0, $day->getTimeOffHours());
        $this->assertSame(0.0, $day->getCapacityHours());

        $weekend = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-07'), 0.0);
        $weekend->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(null));

        $this->assertSame(0.0, $weekend->getTimeOffHours());
        $this->assertFalse($weekend->isDayOff());
    }

    public function testHoliday(): void
    {
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-09-07'), 8.0);
        $this->assertFalse($day->isHoliday());
        $this->assertNull($day->getHoliday());

        $holiday = new PublicHoliday(new \DateTimeImmutable('2026-12-25'), 'Christmas Day', 'SE', 'Sweden', 'official');
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-12-25'), 0.0, 0.0, $holiday);
        $this->assertTrue($day->isHoliday());
        $this->assertSame($holiday, $day->getHoliday());
        $this->assertFalse($day->isWorkingDay());
    }

    public function testTimeOffOnAHolidayDoesNotCountTwice(): void
    {
        // the holiday already takes the contract hours to zero, so time off has nothing left to take
        $holiday = new PublicHoliday(new \DateTimeImmutable('2026-12-25'), 'Christmas Day', 'SE', 'Sweden', 'official');
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-12-25'), 0.0, 0.0, $holiday);
        $day->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(null));
        $day->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(8.0));

        $this->assertSame(0.0, $day->getTimeOffHours());
        $this->assertSame(0.0, $day->getCapacityHours());
        $this->assertSame(0.0, $day->getPlannedHours());
        $this->assertFalse($day->isDayOff());
    }

    public function testWorkOnADayOffCountsOnlyWhenPlannedThereOnPurpose(): void
    {
        $saturday = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-14'), 0.0);
        $acrossTheWeekend = (new PlannedActivity())->setHoursPerDay(4.0);
        $weekendWork = (new PlannedActivity())->setHoursPerDay(5.0);
        $saturday->addActivity($acrossTheWeekend);
        $saturday->addActivity($weekendWork);
        $saturday->countOnDayOff($weekendWork);

        $this->assertFalse($saturday->counts($acrossTheWeekend));
        $this->assertSame(0.0, $saturday->getHoursOf($acrossTheWeekend));
        $this->assertTrue($saturday->counts($weekendWork));
        $this->assertSame(5.0, $saturday->getPlannedHours());

        // on a working day everything counts
        $monday = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-16'), 8.0);
        $this->assertTrue($monday->counts($acrossTheWeekend));
        $this->assertSame(4.0, $monday->getHoursOf($acrossTheWeekend));
    }
}
