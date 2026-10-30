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
use App\Entity\Project;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerDay;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerGroup;
use PHPUnit\Framework\TestCase;

class WeeklyPlannerGroupTest extends TestCase
{
    /**
     * @return array<WeeklyPlannerDay>
     */
    private function createWeek(): array
    {
        $days = [];
        $monday = new \DateTimeImmutable('2026-10-19');
        for ($i = 0; $i < 7; ++$i) {
            $days[] = new WeeklyPlannerDay($monday->modify('+' . $i . ' day'));
        }

        return $days;
    }

    private function createActivity(string $begin, string $end, float $hours = 4.0): PlannedActivity
    {
        return (new PlannedActivity())
            ->setBegin(new \DateTime($begin))
            ->setEnd(new \DateTime($end))
            ->setHoursPerDay($hours);
    }

    public function testLabelUsesCustomerAndProject(): void
    {
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setName('Website');
        $project->setCustomer($customer);

        $group = WeeklyPlannerGroup::createFor($this->createActivity('2026-10-19', '2026-10-19')->setCustomer($customer)->setProject($project));

        $this->assertSame('ACME', $group->getCustomerName());
        $this->assertSame('Website', $group->getProjectName());
        $this->assertSame($customer, $group->getCustomer());
        $this->assertSame($project, $group->getProject());
    }

    public function testLegacyActivityIsLabelledWithItsTitle(): void
    {
        $activity = $this->createActivity('2026-10-19', '2026-10-19')->setTitle('Old plan');
        $group = WeeklyPlannerGroup::createFor($activity);

        $this->assertSame('Old plan', $group->getCustomerName());
        $this->assertNull($group->getProjectName());
        $this->assertSame('legacy:Old plan', $group->getKey());
    }

    public function testMultiDayActivityIsMergedIntoOneSegment(): void
    {
        $activity = $this->createActivity('2026-10-20', '2026-10-22');
        $group = WeeklyPlannerGroup::createFor($activity);
        $group->addActivity($activity);
        $group->addActivity($activity);

        $segments = $group->getSegments($this->createWeek());

        $this->assertSame([1, 3, 1, 1, 1], array_column($segments, 'span'));
        $this->assertSame([], $segments[0]['activities']);
        $this->assertSame([$activity], $segments[1]['activities']);
        $this->assertSame('2026-10-20', $segments[1]['date']->format('Y-m-d'));
        $this->assertSame([$activity], $group->getActivities());
    }

    public function testOverlappingActivitiesAreNotMerged(): void
    {
        $first = $this->createActivity('2026-10-19', '2026-10-20');
        $second = $this->createActivity('2026-10-20', '2026-10-21');
        $group = WeeklyPlannerGroup::createFor($first);
        $group->addActivity($first);
        $group->addActivity($second);

        $segments = $group->getSegments($this->createWeek());

        $this->assertSame([1, 1, 1, 1, 1, 1, 1], array_column($segments, 'span'));
        $this->assertSame([$first, $second], $segments[1]['activities']);
        $this->assertSame([$second], $segments[2]['activities']);
    }

    public function testBackToBackDifferentActivitiesAreNotMerged(): void
    {
        $first = $this->createActivity('2026-10-19', '2026-10-19');
        $second = $this->createActivity('2026-10-20', '2026-10-20');
        $group = WeeklyPlannerGroup::createFor($first);
        $group->addActivity($first);
        $group->addActivity($second);

        $this->assertSame([1, 1, 1, 1, 1, 1, 1], array_column($group->getSegments($this->createWeek()), 'span'));
    }

    public function testPlannedHoursOnlyCountDaysInRange(): void
    {
        // Sunday of the previous week to Tuesday: only Monday and Tuesday are visible
        $activity = $this->createActivity('2026-10-18', '2026-10-20', 3.0);
        $group = WeeklyPlannerGroup::createFor($activity);
        $group->addActivity($activity);

        $this->assertSame(6.0, $group->getPlannedHours($this->createWeek()));
    }

    public function testJoinableAfterFindsTheNextPartOfTheSamePlanning(): void
    {
        $customer = new Customer('ACME');
        $first = $this->createActivity('2026-10-19', '2026-10-20')->setCustomer($customer);
        $second = $this->createActivity('2026-10-21', '2026-10-22')->setCustomer($customer);
        $other = $this->createActivity('2026-10-21', '2026-10-21', 2.0)->setCustomer($customer);

        $group = WeeklyPlannerGroup::createFor($first);
        foreach ([$first, $other, $second] as $activity) {
            $group->addActivity($activity);
        }

        $this->assertSame($second, $group->getJoinableAfter($first));
        $this->assertNull($group->getJoinableAfter($second));
    }
}
