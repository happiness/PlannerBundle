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
use App\Model\DailyStatistic;
use App\Repository\Query\TimesheetStatisticQuery;
use App\Timesheet\TimesheetStatisticService;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;

/**
 * @final not final for mocking in tests
 */
class WeeklyPlannerService
{
    public function __construct(
        private readonly PlannedActivityRepository $activityRepository,
        private readonly WorkingTimeService $workingTimeService,
        private readonly TimesheetStatisticService $statisticService,
        private readonly ?HolidayProviderInterface $holidayProvider = null
    ) {
    }

    public const MIN_WEEKS = 1;
    public const MAX_WEEKS = 8;

    /**
     * @param array<User> $users
     * @param int $weeks number of calendar weeks to show, starting with the week of $selectedDate
     * @param string $planning one of the PlannerQuery::PLANNING_* filters: decides which activities are shown, the
     *                         capacity figures always count all of them; "tentative" also leaves out users without any
     */
    public function getPlannerData(array $users, \DateTimeInterface $selectedDate, int $weeks = 1, string $planning = PlannerQuery::PLANNING_ALL): WeeklyPlannerData
    {
        $weeks = max(self::MIN_WEEKS, min(self::MAX_WEEKS, $weeks));

        $start = \DateTimeImmutable::createFromInterface($selectedDate)->modify('this week monday 00:00:00');
        $end = $start->modify(\sprintf('+%d days', $weeks * 7 - 1))->setTime(23, 59, 59);

        $plannerData = new WeeklyPlannerData($start, $end);

        if ($users === []) {
            return $plannerData;
        }

        $activities = $this->activityRepository->findPlannedActivitiesForUsersAndRange($users, $start, $end);

        /** @var array<int, array<PlannedActivity>> $userActivities */
        $userActivities = [];
        foreach ($activities as $act) {
            $user = $act->getUser();
            if ($user !== null && $user->getId() !== null) {
                $userActivities[$user->getId()][] = $act;
            }
        }

        $query = new TimesheetStatisticQuery($start, $end, $users);
        $dailyStats = $this->statisticService->getDailyStatistics($query);
        /** @var array<int, DailyStatistic> $userStats */
        $userStats = [];
        foreach ($dailyStats as $stat) {
            $u = $stat->getUser();
            if ($u !== null && $u->getId() !== null) {
                $userStats[$u->getId()] = $stat;
            }
        }

        foreach ($users as $user) {
            $userRow = new WeeklyPlannerUserRow($user);
            $calculator = $this->workingTimeService->getContractMode($user)->getCalculator($user);
            $holidays = $this->holidayProvider?->getHolidays($user, $start, $end) ?? [];

            $cur = $start;
            while ($cur <= $end) {
                // a public holiday is a day without contract hours
                $holiday = $holidays[$cur->format('Y-m-d')] ?? null;
                $expectedSeconds = $holiday === null ? $calculator->getWorkHoursForDay($cur) : 0;
                $expectedHours = round($expectedSeconds / 3600.0, 2);

                $userId = $user->getId();
                $actualHours = 0.0;
                if ($userId !== null && isset($userStats[$userId])) {
                    $dayStat = $userStats[$userId]->getDay($cur->format('Y'), $cur->format('m'), $cur->format('d'));
                    if ($dayStat !== null) {
                        $actualHours = round($dayStat->getTotalDuration() / 3600.0, 2);
                    }
                }

                $day = new WeeklyPlannerDay($cur, $expectedHours, $actualHours, $holiday);

                if ($userId !== null && isset($userActivities[$userId])) {
                    foreach ($userActivities[$userId] as $activity) {
                        if ($activity->coversDate($cur)) {
                            $day->addActivity($activity);
                            if ($this->isShown($activity, $planning)) {
                                $userRow->addUniqueActivity($activity);
                            }
                        }
                    }
                }

                $userRow->addDay($day);
                $cur = $cur->modify('+1 day');
            }

            if ($planning === PlannerQuery::PLANNING_TENTATIVE && $userRow->getUniqueActivities() === []) {
                continue;
            }

            $plannerData->addRow($userRow);
        }

        return $plannerData;
    }

    private function isShown(PlannedActivity $activity, string $planning): bool
    {
        return match ($planning) {
            PlannerQuery::PLANNING_CONFIRMED => !$activity->isTentative(),
            PlannerQuery::PLANNING_TENTATIVE => $activity->isTentative(),
            default => true,
        };
    }
}
