<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;

/**
 * Splits the date range of a planned activity into the runs of days its user works, according to their working time
 * contract, so planning from a Friday to a Tuesday becomes Friday and Monday to Tuesday.
 *
 * @final not final for mocking in tests
 */
class WorkingDayRanges
{
    public function __construct(private readonly WorkingTimeService $workingTimeService)
    {
    }

    /**
     * @return array<array{0: \DateTime, 1: \DateTime}> begin and end of each run of working days, empty when the range
     *                                                  has no day off in it (nothing to split) or no working day at all
     *                                                  (planning a day off on purpose is kept as it is)
     */
    public function forActivity(PlannedActivity $activity): array
    {
        $user = $activity->getUser();
        $begin = $activity->getBegin();
        $end = $activity->getEnd();
        if ($user === null || $begin === null || $end === null || $end < $begin) {
            return [];
        }

        $calculator = $this->workingTimeService->getContractMode($user)->getCalculator($user);

        $ranges = [];
        $runBegin = null;
        $runEnd = null;
        $hasDayOff = false;
        $last = $end->format('Y-m-d');
        for ($day = new \DateTimeImmutable($begin->format('Y-m-d')); $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
            if ($calculator->getWorkHoursForDay($day) > 0) {
                $runBegin ??= $day;
                $runEnd = $day;

                continue;
            }

            $hasDayOff = true;
            if ($runBegin !== null && $runEnd !== null) {
                $ranges[] = [new \DateTime($runBegin->format('Y-m-d')), new \DateTime($runEnd->format('Y-m-d'))];
                $runBegin = null;
            }
        }

        if ($runBegin !== null && $runEnd !== null) {
            $ranges[] = [new \DateTime($runBegin->format('Y-m-d')), new \DateTime($runEnd->format('Y-m-d'))];
        }

        if (!$hasDayOff || $ranges === []) {
            return [];
        }

        return $ranges;
    }
}
