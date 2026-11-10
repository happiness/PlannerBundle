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
use App\WorkingTime\Calculator\WorkingTimeCalculator;
use App\WorkingTime\WorkingTimeService;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservations;

/**
 * @final not final for mocking in tests
 */
class WeeklyPlannerService
{
    public function __construct(
        private readonly PlannedActivityRepository $activityRepository,
        private readonly WorkingTimeService $workingTimeService,
        private readonly TimesheetStatisticService $statisticService,
        private readonly ?HolidayProviderInterface $holidayProvider = null,
        private readonly ?PlanningReservations $reservations = null
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
            $userRow = new WeeklyPlannerUserRow($user, $this->reservations);
            $calculator = $this->workingTimeService->getContractMode($user)->getCalculator($user);
            $holidays = $this->holidayProvider?->getHolidays($user, $start, $end) ?? [];

            /** @var array<WeeklyPlannerDay> $days */
            $days = [];
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
                $days[] = $day;
                $cur = $cur->modify('+1 day');
            }

            $this->countWorkPlannedOnDaysOff($user, $calculator, $userActivities[(int) $user->getId()] ?? [], $days, $start, $end);

            if ($planning === PlannerQuery::PLANNING_TENTATIVE && $userRow->getUniqueActivities() === []) {
                continue;
            }

            $plannerData->addRow($userRow);
        }

        return $plannerData;
    }

    /**
     * Work across a day off takes no hours there (WeeklyPlannerDay::counts()), unless every day of the activity is a
     * day off: then it was planned there on purpose, like weekend work, and counts in full. Most activities have a
     * working day on screen, so the contract is only asked about days outside the shown range when there is none.
     *
     * @param array<PlannedActivity> $activities
     * @param array<WeeklyPlannerDay> $days
     */
    private function countWorkPlannedOnDaysOff(User $user, WorkingTimeCalculator $calculator, array $activities, array $days, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        foreach ($activities as $activity) {
            if ($activity->isTimeOff()) {
                continue;
            }

            $covered = array_filter($days, static fn (WeeklyPlannerDay $day): bool => $activity->coversDate($day->getDate()));
            if ($covered === []) {
                continue;
            }

            foreach ($covered as $day) {
                if ($day->getExpectedHours() > 0.0) {
                    continue 2;
                }
            }

            if ($this->hasWorkingDayOutside($user, $calculator, $activity, $start, $end)) {
                continue;
            }

            foreach ($covered as $day) {
                $day->countOnDayOff($activity);
            }
        }
    }

    /**
     * Whether a day of the activity outside the shown range has contract hours and is no public holiday.
     */
    private function hasWorkingDayOutside(User $user, WorkingTimeCalculator $calculator, PlannedActivity $activity, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $begin = $activity->getBegin();
        $last = $activity->getEnd();
        if ($begin === null || $last === null) {
            return false;
        }

        $from = \DateTimeImmutable::createFromInterface($begin)->setTime(0, 0);
        $to = \DateTimeImmutable::createFromInterface($last)->setTime(0, 0);
        $holidays = null;

        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            if ($day >= $start && $day <= $end) {
                // already known to be a day off
                continue;
            }

            $holidays ??= $this->holidayProvider?->getHolidays($user, $from, $to) ?? [];
            if (!\array_key_exists($day->format('Y-m-d'), $holidays) && $calculator->getWorkHoursForDay($day) > 0) {
                return true;
            }
        }

        return false;
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
