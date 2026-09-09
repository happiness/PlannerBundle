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
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerData;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerDay;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerUserRow;
use PHPUnit\Framework\TestCase;

class WeeklyPlannerDataTest extends TestCase
{
    public function testWeeklyPlannerDataStructure(): void
    {
        $start = new \DateTimeImmutable('2026-09-07');
        $end = new \DateTimeImmutable('2026-09-13');

        $data = new WeeklyPlannerData($start, $end);

        $this->assertSame($start, $data->getStart());
        $this->assertSame($end, $data->getEnd());
        $this->assertCount(7, $data->getDateHeaders());

        $user1 = new User();
        $ref1 = new \ReflectionProperty(User::class, 'id');
        $ref1->setValue($user1, 1);

        $row1 = new WeeklyPlannerUserRow($user1);
        $d1 = new WeeklyPlannerDay($start, 8.0, 6.0);
        $act1 = new PlannedActivity();
        $act1->setHoursPerDay(4.0);
        $d1->addActivity($act1);
        $row1->addDay($d1);

        $data->addRow($row1);

        $this->assertSame($row1, $data->getRowForUser($user1));
        $this->assertCount(1, $data->getRows());
        $this->assertSame(8.0, $data->getTotalExpectedHoursForDay($start));
        $this->assertSame(4.0, $data->getTotalPlannedHoursForDay($start));
        $this->assertSame(6.0, $data->getTotalActualHoursForDay($start));

        $this->assertSame(8.0, $data->getTotalExpectedHours());
        $this->assertSame(4.0, $data->getTotalPlannedHours());
        $this->assertSame(6.0, $data->getTotalActualHours());
    }
}
