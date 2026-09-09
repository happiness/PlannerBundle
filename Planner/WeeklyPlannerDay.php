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

    public function getPlannedHours(): float
    {
        $total = 0.0;
        foreach ($this->activities as $activity) {
            $total += $activity->getHoursPerDay();
        }

        return $total;
    }

    public function isWorkingDay(): bool
    {
        return $this->expectedHours > 0.0;
    }

    public function isOverAllocated(): bool
    {
        if ($this->expectedHours <= 0.0) {
            return $this->getPlannedHours() > 0.0;
        }

        return $this->getPlannedHours() > $this->expectedHours;
    }

    public function isUnderAllocated(): bool
    {
        if ($this->expectedHours <= 0.0) {
            return false;
        }

        return $this->getPlannedHours() < $this->expectedHours;
    }

    public function isBalanced(): bool
    {
        if ($this->expectedHours <= 0.0) {
            return $this->getPlannedHours() === 0.0;
        }

        return abs($this->getPlannedHours() - $this->expectedHours) < 0.001;
    }
}
