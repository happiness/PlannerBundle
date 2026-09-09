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
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerDay;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerUserRow;
use PHPUnit\Framework\TestCase;

class WeeklyPlannerUserRowTest extends TestCase
{
    public function testUserRowTotalsAndAllocations(): void
    {
        $user = new User();
        $row = new WeeklyPlannerUserRow($user);

        $this->assertSame($user, $row->getUser());

        $d1 = new \DateTimeImmutable('2026-09-08');
        $d2 = new \DateTimeImmutable('2026-09-09');

        $day1 = new WeeklyPlannerDay($d1, 8.0, 7.0);
        $day2 = new WeeklyPlannerDay($d2, 8.0, 8.0);

        $act1 = new PlannedActivity();
        $act1->setHoursPerDay(4.0);
        $day1->addActivity($act1);
        $row->addUniqueActivity($act1);

        $act2 = new PlannedActivity();
        $act2->setHoursPerDay(8.0);
        $day2->addActivity($act2);
        $row->addUniqueActivity($act2);

        $row->addDay($day1);
        $row->addDay($day2);

        $this->assertSame($day1, $row->getDay($d1));
        $this->assertSame($day2, $row->getDay($d2));
        $this->assertCount(2, $row->getDays());
        $this->assertCount(2, $row->getUniqueActivities());

        $this->assertSame(16.0, $row->getTotalExpectedHours());
        $this->assertSame(12.0, $row->getTotalPlannedHours());
        $this->assertSame(15.0, $row->getTotalActualHours());
        $this->assertTrue($row->isUnderAllocated());
        $this->assertFalse($row->isOverAllocated());
        $this->assertFalse($row->isBalanced());
    }
}
