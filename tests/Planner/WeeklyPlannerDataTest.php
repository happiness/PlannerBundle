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
        $d1->addActivity((new PlannedActivity())->setHoursPerDay(3.0)->setTentative(true));
        $row1->addDay($d1);

        $data->addRow($row1);

        $this->assertSame($row1, $data->getRowForUser($user1));
        $this->assertCount(1, $data->getRows());
        $this->assertSame(8.0, $data->getTotalExpectedHoursForDay($start));
        $this->assertSame(4.0, $data->getTotalPlannedHoursForDay($start));
        $this->assertSame(6.0, $data->getTotalActualHoursForDay($start));
        $this->assertSame(3.0, $data->getTotalTentativeHoursForDay($start));
        $this->assertSame(0.0, $data->getTotalTentativeHoursForDay($end));

        $this->assertSame(8.0, $data->getTotalExpectedHours());
        $this->assertSame(4.0, $data->getTotalPlannedHours());
        $this->assertSame(6.0, $data->getTotalActualHours());
        $this->assertSame(3.0, $data->getTotalTentativeHours());
    }

    public function testSortByFreeHoursKeepsTheOrderOfEqualRows(): void
    {
        $date = new \DateTimeImmutable('2026-09-07');
        $data = new WeeklyPlannerData($date, $date);
        $ref = new \ReflectionProperty(User::class, 'id');

        // user => planned hours of an 8 hour day: 4 free, 4 free, overbooked by 1, 8 free
        foreach ([1 => 4.0, 2 => 4.0, 3 => 9.0, 4 => 0.0] as $id => $planned) {
            $user = new User();
            $ref->setValue($user, $id);
            $row = new WeeklyPlannerUserRow($user);
            $day = new WeeklyPlannerDay($date, 8.0);
            if ($planned > 0.0) {
                $day->addActivity((new PlannedActivity())->setHoursPerDay($planned));
            }
            $row->addDay($day);
            $data->addRow($row);
        }

        $data->sortByFreeHours($date);
        $this->assertSame([4, 1, 2, 3], array_keys($data->getRows()));

        // once the day has passed nobody has free hours, so the order stays as it is
        $data->sortByFreeHours($date->modify('+1 day'));
        $this->assertSame([4, 1, 2, 3], array_keys($data->getRows()));
    }
}
