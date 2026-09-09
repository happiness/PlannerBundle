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

    public function getTotalExpectedHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getExpectedHours();
        }

        return $total;
    }

    public function getTotalPlannedHours(): float
    {
        $total = 0.0;
        foreach ($this->days as $day) {
            $total += $day->getPlannedHours();
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

    public function isOverAllocated(): bool
    {
        return $this->getTotalPlannedHours() > $this->getTotalExpectedHours();
    }

    public function isUnderAllocated(): bool
    {
        return $this->getTotalPlannedHours() < $this->getTotalExpectedHours();
    }

    public function isBalanced(): bool
    {
        return abs($this->getTotalPlannedHours() - $this->getTotalExpectedHours()) < 0.001;
    }
}
