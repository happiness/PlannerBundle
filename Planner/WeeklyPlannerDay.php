<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;

final class WeeklyPlannerDay
{
    /**
     * @var array<PlannedActivity>
     */
    private array $activities = [];

    public function __construct(
        private readonly \DateTimeImmutable $date,
        private readonly float $expectedHours = 0.0,
        private readonly float $actualHours = 0.0
    ) {
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getExpectedHours(): float
    {
        return $this->expectedHours;
    }

    public function getActualHours(): float
    {
        return $this->actualHours;
    }

    public function addActivity(PlannedActivity $activity): void
    {
        $this->activities[] = $activity;
    }

    /**
     * @return array<PlannedActivity>
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * The hours an activity takes on this day: time off without hours is the whole working day.
     */
    public function getHoursOf(PlannedActivity $activity): float
    {
        return $activity->getHoursPerDay() ?? ($activity->isTimeOff() ? $this->expectedHours : 0.0);
    }

    /**
     * Planned work only, time off is not planned but reduces the capacity.
     */
    public function getPlannedHours(): float
    {
        $total = 0.0;
        foreach ($this->activities as $activity) {
            if (!$activity->isTimeOff()) {
                $total += $this->getHoursOf($activity);
            }
        }

        return $total;
    }

    /**
     * Time off on this day, never more than the contract expects.
     */
    public function getTimeOffHours(): float
    {
        $total = 0.0;
        foreach ($this->activities as $activity) {
            if ($activity->isTimeOff()) {
                $total += $this->getHoursOf($activity);
            }
        }

        return min($total, $this->expectedHours);
    }

    public function hasTimeOff(): bool
    {
        foreach ($this->activities as $activity) {
            if ($activity->isTimeOff()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The contract hours minus time off: what is left to plan.
     */
    public function getCapacityHours(): float
    {
        return round($this->expectedHours - $this->getTimeOffHours(), 2);
    }

    public function getPlannedPercentage(): int
    {
        $capacity = $this->getCapacityHours();
        if ($capacity <= 0.0) {
            return 0;
        }

        return (int) round(($this->getPlannedHours() / $capacity) * 100);
    }

    public function isWorkingDay(): bool
    {
        return $this->expectedHours > 0.0;
    }

    /**
     * A working day the user has taken off completely.
     */
    public function isDayOff(): bool
    {
        return $this->isWorkingDay() && $this->getCapacityHours() <= 0.0;
    }

    public function isOverAllocated(): bool
    {
        $capacity = $this->getCapacityHours();
        if ($capacity <= 0.0) {
            return $this->getPlannedHours() > 0.0;
        }

        return $this->getPlannedHours() > $capacity;
    }

    public function isUnderAllocated(): bool
    {
        $capacity = $this->getCapacityHours();
        if ($capacity <= 0.0) {
            return false;
        }

        return $this->getPlannedHours() < $capacity;
    }

    public function isBalanced(): bool
    {
        $capacity = $this->getCapacityHours();
        if ($capacity <= 0.0) {
            return $this->getPlannedHours() === 0.0;
        }

        return abs($this->getPlannedHours() - $capacity) < 0.001;
    }
}
