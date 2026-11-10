<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use App\Entity\User;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetStatisticService;
use App\WorkingTime\Calculator\WorkingTimeCalculator;
use App\WorkingTime\Mode\WorkingTimeMode;
use App\WorkingTime\WorkingTimeService;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface;
use KimaiPlugin\PlannerBundle\Holiday\PublicHoliday;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class WeeklyPlannerServiceTest extends TestCase
{
    /**
     * @param array<mixed> $queryResults
     */
    private function createStatisticService(array $queryResults = []): TimesheetStatisticService
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($queryResults);

        $expr = $this->createMock(Expr::class);
        $expr->method('between')->willReturn(new Expr\Comparison('t.date', 'BETWEEN', ':begin AND :end'));
        $expr->method('in')->willReturn(new Expr\Func('t.user IN', [':user']));
        $expr->method('isNotNull')->willReturn('t.end IS NOT NULL');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('addGroupBy')->willReturnSelf();
        $qb->method('expr')->willReturn($expr);
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(TimesheetRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        return new TimesheetStatisticService($repo, $dispatcher);
    }

    public function testGetPlannerDataWithEmptyUsers(): void
    {
        $repo = $this->createMock(PlannedActivityRepository::class);
        $wtService = $this->createMock(WorkingTimeService::class);
        $tsService = $this->createStatisticService();

        $service = new WeeklyPlannerService($repo, $wtService, $tsService);
        $data = $service->getPlannerData([], new \DateTimeImmutable('2026-09-08'));

        $this->assertCount(0, $data->getRows());
        $this->assertCount(7, $data->getDateHeaders());
    }

    public function testGetPlannerDataWithUsers(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 42);

        $activity = new PlannedActivity();
        $activity->setUser($user);
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setEnd(new \DateTime('2026-09-09'));
        $activity->setHoursPerDay(5.0);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())
            ->method('findPlannedActivitiesForUsersAndRange')
            ->willReturn([$activity]);

        $calculator = $this->createMock(WorkingTimeCalculator::class);
        $calculator->method('getWorkHoursForDay')->willReturn(28800); // 8 hours

        $mode = $this->createMock(WorkingTimeMode::class);
        $mode->method('getCalculator')->willReturn($calculator);

        $wtService = $this->createMock(WorkingTimeService::class);
        $wtService->expects($this->once())
            ->method('getContractMode')
            ->willReturn($mode);

        $tsService = $this->createStatisticService([
            [
                'user' => 42,
                'year' => '2026',
                'month' => '09',
                'day' => '07',
                'duration' => 14400,
                'rate' => 0.0,
                'internalRate' => 0.0,
                'billable' => false,
            ],
        ]);

        $service = new WeeklyPlannerService($repo, $wtService, $tsService);
        $data = $service->getPlannerData([$user], new \DateTimeImmutable('2026-09-08'));

        $this->assertCount(1, $data->getRows());
        $row = $data->getRowForUser($user);
        $this->assertNotNull($row);

        $monday = new \DateTimeImmutable('2026-09-07');
        $mondayDay = $row->getDay($monday);
        $this->assertNotNull($mondayDay);
        $this->assertSame(5.0, $mondayDay->getPlannedHours());
        $this->assertSame(8.0, $mondayDay->getExpectedHours());
        $this->assertSame(4.0, $mondayDay->getActualHours());
        $this->assertCount(1, $mondayDay->getActivities());

        $thursday = new \DateTimeImmutable('2026-09-10');
        $thursdayDay = $row->getDay($thursday);
        $this->assertNotNull($thursdayDay);
        $this->assertSame(0.0, $thursdayDay->getPlannedHours());
    }

    /**
     * @return array<string, array{string, array<int>, array<string>}>
     */
    public static function planningFilters(): array
    {
        return [
            'all' => [PlannerQuery::PLANNING_ALL, [42, 43], ['confirmed', 'tentative']],
            'confirmed only' => [PlannerQuery::PLANNING_CONFIRMED, [42, 43], ['confirmed']],
            'tentative only hides users without tentative work' => [PlannerQuery::PLANNING_TENTATIVE, [42], ['tentative']],
        ];
    }

    /**
     * @param array<int> $expectedUsers
     * @param array<string> $expectedComments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('planningFilters')]
    public function testPlanningFilterHidesActivitiesButCapacityCountsAll(string $planning, array $expectedUsers, array $expectedComments): void
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $user = new User();
        $ref->setValue($user, 42);
        $other = new User();
        $ref->setValue($other, 43);

        $confirmed = (new PlannedActivity())->setUser($user)->setHoursPerDay(4.0)->setComment('confirmed')
            ->setBegin(new \DateTime('2026-09-07'))->setEnd(new \DateTime('2026-09-07'));
        $tentative = (new PlannedActivity())->setUser($user)->setHoursPerDay(2.0)->setComment('tentative')->setTentative(true)
            ->setBegin(new \DateTime('2026-09-07'))->setEnd(new \DateTime('2026-09-07'));

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->method('findPlannedActivitiesForUsersAndRange')->willReturn([$confirmed, $tentative]);

        $calculator = $this->createMock(WorkingTimeCalculator::class);
        $calculator->method('getWorkHoursForDay')->willReturn(28800);
        $mode = $this->createMock(WorkingTimeMode::class);
        $mode->method('getCalculator')->willReturn($calculator);
        $wtService = $this->createMock(WorkingTimeService::class);
        $wtService->method('getContractMode')->willReturn($mode);

        $service = new WeeklyPlannerService($repo, $wtService, $this->createStatisticService());
        $data = $service->getPlannerData([$user, $other], new \DateTimeImmutable('2026-09-08'), 1, $planning);

        $this->assertSame($expectedUsers, array_keys($data->getRows()));

        $row = $data->getRowForUser($user);
        $this->assertNotNull($row);
        $this->assertSame($expectedComments, array_map(static fn (PlannedActivity $activity): ?string => $activity->getComment(), $row->getUniqueActivities()));

        $monday = $row->getDay(new \DateTimeImmutable('2026-09-07'));
        $this->assertNotNull($monday);
        $this->assertSame(4.0, $monday->getPlannedHours());
        $this->assertSame(2.0, $monday->getTentativeHours());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function weekCounts(): array
    {
        return [
            'one week' => [1, 7],
            'four weeks' => [4, 28],
            'eight weeks' => [8, 56],
            'zero is raised to one' => [0, 7],
            'too many is capped at eight' => [20, 56],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weekCounts')]
    public function testGetPlannerDataSpansRequestedWeeks(int $weeks, int $expectedDays): void
    {
        $service = new WeeklyPlannerService(
            $this->createMock(PlannedActivityRepository::class),
            $this->createMock(WorkingTimeService::class),
            $this->createStatisticService()
        );

        $data = $service->getPlannerData([], new \DateTimeImmutable('2026-09-09'), $weeks);

        $this->assertCount($expectedDays, $data->getDateHeaders());
        $this->assertSame('2026-09-07', $data->getStart()->format('Y-m-d'));
        $this->assertSame('Sunday', $data->getEnd()->format('l'));
        $this->assertSame('23:59:59', $data->getEnd()->format('H:i:s'));
    }

    public function testPublicHolidaysHaveNoExpectedHours(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 42);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->method('findPlannedActivitiesForUsersAndRange')->willReturn([]);

        $calculator = $this->createMock(WorkingTimeCalculator::class);
        $calculator->method('getWorkHoursForDay')->willReturnCallback(static fn (\DateTimeInterface $day): int => (int) $day->format('N') <= 5 ? 28800 : 0);

        $mode = $this->createMock(WorkingTimeMode::class);
        $mode->method('getCalculator')->willReturn($calculator);

        $wtService = $this->createMock(WorkingTimeService::class);
        $wtService->method('getContractMode')->willReturn($mode);

        $christmas = new PublicHoliday(new \DateTimeImmutable('2026-12-25'), 'Christmas Day', 'SE', 'Sweden', 'official');
        $holidays = $this->createMock(HolidayProviderInterface::class);
        $holidays->expects($this->once())
            ->method('getHolidays')
            ->with($user, new \DateTimeImmutable('2026-12-21 00:00:00'), new \DateTimeImmutable('2026-12-27 23:59:59'))
            ->willReturn(['2026-12-25' => $christmas]);

        $service = new WeeklyPlannerService($repo, $wtService, $this->createStatisticService(), $holidays);
        $row = $service->getPlannerData([$user], new \DateTimeImmutable('2026-12-23'))->getRowForUser($user);
        $this->assertNotNull($row);

        $friday = $row->getDay(new \DateTimeImmutable('2026-12-25'));
        $this->assertNotNull($friday);
        $this->assertSame($christmas, $friday->getHoliday());
        $this->assertSame(0.0, $friday->getExpectedHours());
        $this->assertFalse($friday->isWorkingDay());

        $thursday = $row->getDay(new \DateTimeImmutable('2026-12-24'));
        $this->assertNotNull($thursday);
        $this->assertFalse($thursday->isHoliday());
        $this->assertSame(8.0, $thursday->getExpectedHours());
    }

    /**
     * @param array<PlannedActivity> $activities
     * @param array<string, PublicHoliday> $holidays
     */
    private function createWeekdayService(User $user, array $activities, array $holidays = []): WeeklyPlannerService
    {
        $repo = $this->createStub(PlannedActivityRepository::class);
        $repo->method('findPlannedActivitiesForUsersAndRange')->willReturn($activities);

        // eight hours Monday to Friday
        $calculator = $this->createStub(WorkingTimeCalculator::class);
        $calculator->method('getWorkHoursForDay')->willReturnCallback(static fn (\DateTimeInterface $day): int => (int) $day->format('N') <= 5 ? 28800 : 0);

        $mode = $this->createStub(WorkingTimeMode::class);
        $mode->method('getCalculator')->willReturn($calculator);

        $wtService = $this->createStub(WorkingTimeService::class);
        $wtService->method('getContractMode')->willReturn($mode);

        $provider = $this->createStub(HolidayProviderInterface::class);
        $provider->method('getHolidays')->willReturn($holidays);

        return new WeeklyPlannerService($repo, $wtService, $this->createStatisticService(), $provider);
    }

    private function createWork(User $user, string $begin, string $end, float $hours): PlannedActivity
    {
        return (new PlannedActivity())
            ->setUser($user)
            ->setCustomer(new \App\Entity\Customer('Acme'))
            ->setBegin(new \DateTime($begin))
            ->setEnd(new \DateTime($end))
            ->setHoursPerDay($hours);
    }

    public function testWorkAcrossAWeekendCountsOnlyOnWorkingDays(): void
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);
        // Friday to Tuesday, one row as entered
        $work = $this->createWork($user, '2026-11-13', '2026-11-17', 4.0);

        $data = $this->createWeekdayService($user, [$work])->getPlannerData([$user], new \DateTimeImmutable('2026-11-09'), 2);
        $row = $data->getRowForUser($user);
        $this->assertNotNull($row);

        $this->assertSame(4.0, $row->getDay(new \DateTimeImmutable('2026-11-13'))?->getPlannedHours());
        $this->assertSame(0.0, $row->getDay(new \DateTimeImmutable('2026-11-14'))?->getPlannedHours());
        $this->assertSame(0.0, $row->getDay(new \DateTimeImmutable('2026-11-15'))?->getPlannedHours());
        $this->assertSame(4.0, $row->getDay(new \DateTimeImmutable('2026-11-16'))?->getPlannedHours());
        // three working days of four hours, the weekend not counted
        $this->assertSame(12.0, $row->getTotalPlannedHours());
        $this->assertSame(12.0, $row->getGroups()[0]->getPlannedHours($row->getDays()));
    }

    public function testWorkOnlyOnDaysOffIsPlannedOnPurpose(): void
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);
        $weekend = $this->createWork($user, '2026-11-14', '2026-11-15', 5.0);

        $row = $this->createWeekdayService($user, [$weekend])->getPlannerData([$user], new \DateTimeImmutable('2026-11-09'))->getRowForUser($user);
        $this->assertNotNull($row);

        $this->assertSame(5.0, $row->getDay(new \DateTimeImmutable('2026-11-14'))?->getPlannedHours());
        $this->assertSame(10.0, $row->getTotalPlannedHours());
    }

    public function testAWorkingDayOutsideTheShownWeekStillDecides(): void
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);
        // Saturday to the following Monday: only the weekend is on screen
        $work = $this->createWork($user, '2026-11-14', '2026-11-16', 3.0);

        $row = $this->createWeekdayService($user, [$work])->getPlannerData([$user], new \DateTimeImmutable('2026-11-09'))->getRowForUser($user);
        $this->assertNotNull($row);

        $this->assertSame(0.0, $row->getTotalPlannedHours());
    }

    public function testAPublicHolidayIsADayOffForPlannedWork(): void
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);
        $christmas = new PublicHoliday(new \DateTimeImmutable('2026-12-25'), 'Christmas Day', 'SE', 'Sweden', 'official');
        $work = $this->createWork($user, '2026-12-24', '2026-12-25', 8.0);
        // only the holiday: on purpose, counted
        $onCall = $this->createWork($user, '2026-12-25', '2026-12-25', 2.0);

        $row = $this->createWeekdayService($user, [$work, $onCall], ['2026-12-25' => $christmas])->getPlannerData([$user], new \DateTimeImmutable('2026-12-21'))->getRowForUser($user);
        $this->assertNotNull($row);

        $this->assertSame(8.0, $row->getDay(new \DateTimeImmutable('2026-12-24'))?->getPlannedHours());
        $this->assertSame(2.0, $row->getDay(new \DateTimeImmutable('2026-12-25'))?->getPlannedHours());
    }
}
