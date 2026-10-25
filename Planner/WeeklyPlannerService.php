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
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;

/**
 * @final not final for mocking in tests
 */
class WeeklyPlannerService
{
    public function __construct(
        private readonly PlannedActivityRepository $activityRepository,
        private readonly WorkingTimeService $workingTimeService,
        private readonly TimesheetStatisticService $statisticService
    ) {
    }

    public const MIN_WEEKS = 1;
    public const MAX_WEEKS = 8;

    /**
     * @param array<User> $users
     * @param int $weeks number of calendar weeks to show, starting with the week of $selectedDate
     */
    public function getPlannerData(array $users, \DateTimeInterface $selectedDate, int $weeks = 1): WeeklyPlannerData
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

            $cur = $start;
            while ($cur <= $end) {
                $expectedSeconds = $calculator->getWorkHoursForDay($cur);
                $expectedHours = round($expectedSeconds / 3600.0, 2);

                $userId = $user->getId();
                $actualHours = 0.0;
                if ($userId !== null && isset($userStats[$userId])) {
                    $dayStat = $userStats[$userId]->getDay($cur->format('Y'), $cur->format('m'), $cur->format('d'));
                    if ($dayStat !== null) {
                        $actualHours = round($dayStat->getTotalDuration() / 3600.0, 2);
                    }
                }

                $day = new WeeklyPlannerDay($cur, $expectedHours, $actualHours);

                if ($userId !== null && isset($userActivities[$userId])) {
                    foreach ($userActivities[$userId] as $activity) {
                        if ($activity->coversDate($cur)) {
                            $day->addActivity($activity);
                            $userRow->addUniqueActivity($activity);
                        }
                    }
                }

                $userRow->addDay($day);
                $cur = $cur->modify('+1 day');
            }

            $plannerData->addRow($userRow);
        }

        return $plannerData;
    }
}
