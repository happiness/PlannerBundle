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

final class WeeklyPlannerData
{
    /**
     * @var array<\DateTimeImmutable>
     */
    private array $dateHeaders = [];

    /**
     * @var array<int, WeeklyPlannerUserRow>
     */
    private array $rows = [];

    public function __construct(
        private readonly \DateTimeImmutable $start,
        private readonly \DateTimeImmutable $end
    ) {
        $cur = $this->start;
        while ($cur <= $this->end) {
            $this->dateHeaders[] = $cur;
            $cur = $cur->modify('+1 day');
        }
    }

    public function getStart(): \DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): \DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * @return array<\DateTimeImmutable>
     */
    public function getDateHeaders(): array
    {
        return $this->dateHeaders;
    }

    public function addRow(WeeklyPlannerUserRow $row): void
    {
        $userId = $row->getUser()->getId();
        if ($userId !== null) {
            $this->rows[$userId] = $row;
        }
    }

    /**
     * @return array<int, WeeklyPlannerUserRow>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getRowForUser(User $user): ?WeeklyPlannerUserRow
    {
        $userId = $user->getId();
        if ($userId === null) {
            return null;
        }

        return $this->rows[$userId] ?? null;
    }

    public function getTotalExpectedHoursForDay(\DateTimeInterface $date): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $day = $row->getDay($date);
            if ($day !== null) {
                $total += $day->getExpectedHours();
            }
        }

        return $total;
    }

    public function getTotalPlannedHoursForDay(\DateTimeInterface $date): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $day = $row->getDay($date);
            if ($day !== null) {
                $total += $day->getPlannedHours();
            }
        }

        return $total;
    }

    public function getTotalActualHoursForDay(\DateTimeInterface $date): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $day = $row->getDay($date);
            if ($day !== null) {
                $total += $day->getActualHours();
            }
        }

        return $total;
    }

    public function getTotalExpectedHours(): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $total += $row->getTotalExpectedHours();
        }

        return $total;
    }

    public function getTotalPlannedHours(): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $total += $row->getTotalPlannedHours();
        }

        return $total;
    }

    public function getTotalActualHours(): float
    {
        $total = 0.0;
        foreach ($this->rows as $row) {
            $total += $row->getTotalActualHours();
        }

        return $total;
    }
}
