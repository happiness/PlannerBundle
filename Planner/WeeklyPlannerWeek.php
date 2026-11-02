<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

/**
 * Capacity figures of one user for a single calendar week of the displayed range.
 */
final class WeeklyPlannerWeek
{
    /**
     * @param array<WeeklyPlannerDay> $days
     */
    public function __construct(private readonly array $days)
    {
    }

    /**
     * @return array<WeeklyPlannerDay>
     */
    public function getDays(): array
    {
        return $this->days;
    }

    public function getStart(): ?\DateTimeImmutable
    {
        foreach ($this->days as $day) {
            return $day->getDate();
        }

        return null;
    }

    public function getTotalExpectedHours(): float
    {
        return array_sum(array_map(static fn (WeeklyPlannerDay $day): float => $day->getExpectedHours(), $this->days));
    }

    public function getTotalPlannedHours(): float
    {
        return array_sum(array_map(static fn (WeeklyPlannerDay $day): float => $day->getPlannedHours(), $this->days));
    }

    public function getTotalActualHours(): float
    {
        return array_sum(array_map(static fn (WeeklyPlannerDay $day): float => $day->getActualHours(), $this->days));
    }

    public function getPlannedPercentage(): int
    {
        $expected = $this->getTotalExpectedHours();
        if ($expected <= 0.0) {
            return 0;
        }

        return (int) round(($this->getTotalPlannedHours() / $expected) * 100);
    }

    public function isOverAllocated(): bool
    {
        return $this->getTotalPlannedHours() > $this->getTotalExpectedHours();
    }

    public function isBalanced(): bool
    {
        return abs($this->getTotalPlannedHours() - $this->getTotalExpectedHours()) < 0.001;
    }
}
