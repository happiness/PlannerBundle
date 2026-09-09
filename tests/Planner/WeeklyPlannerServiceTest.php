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
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
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
}
