<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;

final class WeeklyPlannerUserRow
{
    /**
     * @var array<string, WeeklyPlannerDay> key is 'Y-m-d'
     */
    private array $days = [];

    /**
     * @var array<PlannedActivity>
     */
    private array $uniqueActivities = [];

    public function __construct(private readonly User $user)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function addDay(WeeklyPlannerDay $day): void
    {
        $this->days[$day->getDate()->format('Y-m-d')] = $day;
    }

    public function getDay(\DateTimeInterface $date): ?WeeklyPlannerDay
    {
        return $this->days[$date->format('Y-m-d')] ?? null;
    }

    /**
     * The days of a bar that do not count for the activity: days off it crosses (see WeeklyPlannerDay::counts()), as
     * positions from \$from, so the grid can mark them inside a bar that spans several days.
     *
     * @return array<int> zero-based positions within \$span days from \$from
     */
    public function getUncountedDays(PlannedActivity $activity, \DateTimeInterface $from, int $span): array
    {
        $start = \DateTimeImmutable::createFromInterface($from);
        $uncounted = [];
        for ($i = 0; $i < $span; ++$i) {
            $day = $this->getDay($start->modify(\sprintf('+%d day', $i)));
            if ($day !== null && !$day->counts($activity)) {
                $uncounted[] = $i;
            }
        }

        return $uncounted;
    }

    /**
     * @return array<string, WeeklyPlannerDay>
     */
    public function getDays(): array
    {
        return $this->days;
    }

    public function addUniqueActivity(PlannedActivity $activity): void
    {
        if (!\in_array($activity, $this->uniqueActivities, true)) {
            $this->uniqueActivities[] = $activity;
        }
    }

    /**
     * @return array<PlannedActivity>
     */
    public function getUniqueActivities(): array
    {
        return $this->uniqueActivities;
    }

    /**
     * The displayed days split into calendar weeks (the range always starts on a Monday).
     *
     * @return array<WeeklyPlannerWeek>
     */
    public function getWeeks(): array
    {
        return array_map(
            static fn (array $days): WeeklyPlannerWeek => new WeeklyPlannerWeek($days),
            array_chunk(array_values($this->days), 7)
        );
    }

    /**
     * Activities grouped by customer/project, ordered by customer and project name, with time off first.
     *
     * @return array<WeeklyPlannerGroup>
     */
    public function getGroups(): array
    {
        $groups = [];
        foreach ($this->uniqueActivities as $activity) {
            $key = WeeklyPlannerGroup::keyFor($activity);
            $groups[$key] ??= WeeklyPlannerGroup::createFor($activity);
            $groups[$key]->addActivity($activity);
        }

        uasort($groups, static fn (WeeklyPlannerGroup $a, WeeklyPlannerGroup $b): int => [!$a->isTimeOff(), $a->getCustomerName(), $a->getProjectName() ?? ''] <=> [!$b->isTimeOff(), $b->getCustomerName(), $b->getProjectName() ?? '']);

        return array_values($groups);
    }

    public function getTotalExpectedHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getExpectedHours();
        }

        return $total;
    }

    /**
     * Contract hours minus time off.
     */
    public function getTotalCapacityHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getCapacityHours();
        }

        return $total;
    }

    public function getPlannedPercentage(): int
    {
        $capacity = $this->getTotalCapacityHours();
        if ($capacity <= 0.0) {
            return 0;
        }

        return (int) round(($this->getTotalPlannedHours() / $capacity) * 100);
    }

    public function getTotalPlannedHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getPlannedHours();
        }

        return $total;
    }

    public function getTotalTentativeHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getTentativeHours();
        }

        return $total;
    }

    public function getTotalActualHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getActualHours();
        }

        return $total;
    }

    /**
     * Capacity left (contract hours minus time off) after the planning from today on, negative when overbooked. Days
     * that have passed are not free any more, so they don't count, and neither does time off.
     */
    public function getFreeHours(?\DateTimeInterface $today = null): float
    {
        $from = ($today ?? new \DateTimeImmutable('today'))->format('Y-m-d');

        $total = 0.0;
        foreach ($this->days as $date => $day) {
            if ($date >= $from) {
                $total += $day->getCapacityHours() - $day->getPlannedHours();
            }
        }

        return round($total, 2);
    }

    public function isOverAllocated(): bool
    {
        return $this->getTotalPlannedHours() > $this->getTotalCapacityHours();
    }

    public function isUnderAllocated(): bool
    {
        return $this->getTotalPlannedHours() < $this->getTotalCapacityHours();
    }

    public function isBalanced(): bool
    {
        return abs($this->getTotalPlannedHours() - $this->getTotalCapacityHours()) < 0.001;
    }
}
