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
use PHPUnit\Framework\TestCase;

class WeeklyPlannerDayTest extends TestCase
{
    public function testGettersAndAllocationStatus(): void
    {
        $date = new \DateTimeImmutable('2026-09-08');
        $day = new WeeklyPlannerDay($date, 8.0, 7.5);

        $this->assertSame($date, $day->getDate());
        $this->assertSame(8.0, $day->getExpectedHours());
        $this->assertSame(7.5, $day->getActualHours());
        $this->assertTrue($day->isWorkingDay());
        $this->assertTrue($day->isUnderAllocated());
        $this->assertFalse($day->isOverAllocated());
        $this->assertFalse($day->isBalanced());

        $act1 = new PlannedActivity();
        $act1->setHoursPerDay(4.0);
        $day->addActivity($act1);

        $this->assertSame(4.0, $day->getPlannedHours());
        $this->assertTrue($day->isUnderAllocated());

        $act2 = new PlannedActivity();
        $act2->setHoursPerDay(4.0);
        $day->addActivity($act2);

        $this->assertSame(8.0, $day->getPlannedHours());
        $this->assertTrue($day->isBalanced());
        $this->assertFalse($day->isUnderAllocated());
        $this->assertFalse($day->isOverAllocated());

        $act3 = new PlannedActivity();
        $act3->setHoursPerDay(1.0);
        $day->addActivity($act3);

        $this->assertSame(9.0, $day->getPlannedHours());
        $this->assertTrue($day->isOverAllocated());
        $this->assertFalse($day->isBalanced());
        $this->assertFalse($day->isUnderAllocated());
    }
}
