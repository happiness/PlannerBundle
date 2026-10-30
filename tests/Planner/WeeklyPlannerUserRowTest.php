<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use App\Entity\Customer;
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
        $this->assertSame(75, $row->getPlannedPercentage());
        $this->assertTrue($row->isUnderAllocated());
        $this->assertFalse($row->isOverAllocated());
        $this->assertFalse($row->isBalanced());

        $emptyRow = new WeeklyPlannerUserRow(new User());
        $this->assertSame(0, $emptyRow->getPlannedPercentage());
    }

    public function testGetGroupsGroupsByCustomerAndProjectAndSortsByName(): void
    {
        $zeta = new Customer('Zeta');
        $acme = new Customer('Acme');

        $makeActivity = static fn (Customer $customer): PlannedActivity => (new PlannedActivity())->setCustomer($customer);

        $row = new WeeklyPlannerUserRow(new User());
        $row->addUniqueActivity($makeActivity($zeta));
        $row->addUniqueActivity($makeActivity($acme));
        $row->addUniqueActivity($makeActivity($zeta));

        $groups = $row->getGroups();

        $this->assertCount(2, $groups);
        $this->assertSame('Acme', $groups[0]->getCustomerName());
        $this->assertSame('Zeta', $groups[1]->getCustomerName());
        $this->assertCount(2, $groups[1]->getActivities());
    }

    public function testGetWeeksSplitsDaysIntoCalendarWeeks(): void
    {
        $row = new WeeklyPlannerUserRow(new User());
        $monday = new \DateTimeImmutable('2026-10-19');
        for ($i = 0; $i < 14; ++$i) {
            $row->addDay(new WeeklyPlannerDay($monday->modify('+' . $i . ' day'), $i < 5 ? 8.0 : 0.0));
        }

        $weeks = $row->getWeeks();

        $this->assertCount(2, $weeks);
        $this->assertCount(7, $weeks[0]->getDays());
        $this->assertSame('2026-10-26', $weeks[1]->getStart()?->format('Y-m-d'));
        $this->assertSame(40.0, $weeks[0]->getTotalExpectedHours());
        $this->assertSame(0.0, $weeks[1]->getTotalExpectedHours());
    }

    public function testTimeOffGroupComesFirst(): void
    {
        $row = new WeeklyPlannerUserRow(new User());
        $row->addUniqueActivity((new PlannedActivity())->setCustomer(new Customer('Acme')));
        $row->addUniqueActivity((new PlannedActivity())->setTimeOff(true));

        $groups = $row->getGroups();

        $this->assertCount(2, $groups);
        $this->assertTrue($groups[0]->isTimeOff());
        $this->assertSame('Acme', $groups[1]->getCustomerName());
    }

    public function testTimeOffIsTakenOffTheCapacity(): void
    {
        $row = new WeeklyPlannerUserRow(new User());
        $day = new WeeklyPlannerDay(new \DateTimeImmutable('2026-11-06'), 8.0);
        $day->addActivity((new PlannedActivity())->setTimeOff(true)->setHoursPerDay(2.0));
        $day->addActivity((new PlannedActivity())->setHoursPerDay(6.0));
        $row->addDay($day);

        $this->assertSame(8.0, $row->getTotalExpectedHours());
        $this->assertSame(6.0, $row->getTotalCapacityHours());
        $this->assertSame(100, $row->getPlannedPercentage());
        $this->assertTrue($row->isBalanced());
    }
}
