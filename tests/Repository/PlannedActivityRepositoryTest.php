<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use PHPUnit\Framework\TestCase;

class PlannedActivityRepositoryTest extends TestCase
{
    private function createRepository(EntityManagerInterface $em): PlannedActivityRepository
    {
        $metadata = new ClassMetadata(PlannedActivity::class);
        $em->method('getClassMetadata')->with(PlannedActivity::class)->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return new PlannedActivityRepository($registry);
    }

    public function testSavePlannedActivity(): void
    {
        $activity = new PlannedActivity();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($activity);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->savePlannedActivity($activity);
    }

    public function testDeletePlannedActivity(): void
    {
        $activity = new PlannedActivity();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($activity);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->deletePlannedActivity($activity);
    }

    public function testRemoveDayFromSingleDayActivity(): void
    {
        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-14'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($activity);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->removeDayFromActivity($activity, new \DateTime('2026-09-14'));
    }

    public function testRemoveDayFromStartBoundary(): void
    {
        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-16'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($activity);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->removeDayFromActivity($activity, new \DateTime('2026-09-14'));

        $this->assertSame('2026-09-15', $activity->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-16', $activity->getEnd()?->format('Y-m-d'));
    }

    public function testRemoveDayFromEndBoundary(): void
    {
        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-16'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($activity);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->removeDayFromActivity($activity, new \DateTime('2026-09-16'));

        $this->assertSame('2026-09-14', $activity->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-15', $activity->getEnd()?->format('Y-m-d'));
    }

    public function testRemoveDayFromMiddleSplitsActivity(): void
    {
        $user = new User();
        $activity = new PlannedActivity();
        $activity->setUser($user);
        $activity->setTitle('Sprint Task');
        $activity->setHoursPerDay(6.0);
        $activity->setColor('#00ff00');
        $activity->setComment('Middle split comment');
        $activity->setRecurrenceGroup('rec-group-1');
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-16'));

        $persistedEntities = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist')->willReturnCallback(function ($entity) use (&$persistedEntities) {
            $persistedEntities[] = $entity;
        });
        $em->expects($this->exactly(2))->method('flush');

        $repository = $this->createRepository($em);
        $repository->removeDayFromActivity($activity, new \DateTime('2026-09-15'));

        $this->assertSame('2026-09-14', $activity->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-14', $activity->getEnd()?->format('Y-m-d'));

        $this->assertCount(2, $persistedEntities);
        $this->assertSame($activity, $persistedEntities[0]);
        $split = $persistedEntities[1];
        $this->assertInstanceOf(PlannedActivity::class, $split);
        $this->assertSame($user, $split->getUser());
        $this->assertSame('Sprint Task', $split->getTitle());
        $this->assertSame(6.0, $split->getHoursPerDay());
        $this->assertSame('#00ff00', $split->getColor());
        $this->assertSame('Middle split comment', $split->getComment());
        $this->assertSame('rec-group-1', $split->getRecurrenceGroup());
        $this->assertSame('2026-09-16', $split->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-16', $split->getEnd()?->format('Y-m-d'));
    }

    public function testRemoveDayWhenNullOrOutOfRange(): void
    {
        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-16'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($activity);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->removeDayFromActivity($activity, new \DateTime('2026-09-20'));
    }

    public function testDeleteRecurringActivities(): void
    {
        $activity1 = new PlannedActivity();
        $activity2 = new PlannedActivity();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('remove')->with($this->logicalOr($this->identicalTo($activity1), $this->identicalTo($activity2)));
        $em->expects($this->once())->method('flush');

        $metadata = new ClassMetadata(PlannedActivity::class);
        $em->method('getClassMetadata')->with(PlannedActivity::class)->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repository = $this->getMockBuilder(PlannedActivityRepository::class)
            ->setConstructorArgs([$registry])
            ->onlyMethods(['findBy'])
            ->getMock();

        $repository->expects($this->once())
            ->method('findBy')
            ->with(['recurrenceGroup' => 'group-123'])
            ->willReturn([$activity1, $activity2]);

        $repository->deleteRecurringActivities('group-123');
    }

    public function testDeleteFutureRecurringActivitiesEarlyReturnWithoutGroupOrBegin(): void
    {
        $activityNoGroup = new PlannedActivity();
        $activityNoGroup->setBegin(new \DateTime('2026-09-14'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($activityNoGroup);
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->deleteFutureRecurringActivities($activityNoGroup);

        $activityNoBegin = new PlannedActivity();
        $activityNoBegin->setRecurrenceGroup('group-1');
        $activityNoBegin->setBegin(null);

        $em2 = $this->createMock(EntityManagerInterface::class);
        $em2->expects($this->once())->method('remove')->with($activityNoBegin);
        $em2->expects($this->once())->method('flush');

        $repository2 = $this->createRepository($em2);
        $repository2->deleteFutureRecurringActivities($activityNoBegin);
    }

    public function testDeleteFutureRecurringActivitiesRemovesFutureEntities(): void
    {
        $currentActivity = new PlannedActivity();
        $currentActivity->setRecurrenceGroup('rec-group-1');
        $currentActivity->setBegin(new \DateTime('2026-09-14'));

        $futureActivity1 = new PlannedActivity();
        $futureActivity1->setBegin(new \DateTime('2026-09-21'));

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$currentActivity, $futureActivity1]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->with('p.recurrenceGroup = :group')->willReturnSelf();
        $qb->method('andWhere')->with('p.begin >= :begin')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $removed = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects($this->exactly(2))->method('remove')->willReturnCallback(function ($entity) use (&$removed) {
            $removed[] = $entity;
        });
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->deleteFutureRecurringActivities($currentActivity);

        $this->assertCount(2, $removed);
        $this->assertSame($currentActivity, $removed[0]);
        $this->assertSame($futureActivity1, $removed[1]);
    }

    public function testUpdateFutureRecurringActivitiesEarlyReturn(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $repository = $this->createRepository($em);

        $activityNoGroup = new PlannedActivity();
        $activityNoGroup->setBegin(new \DateTime('2026-09-14'));
        $repository->updateFutureRecurringActivities($activityNoGroup);

        $activityNoBegin = new PlannedActivity();
        $activityNoBegin->setRecurrenceGroup('group-1');
        $activityNoBegin->setBegin(null);
        $repository->updateFutureRecurringActivities($activityNoBegin);
    }

    public function testUpdateFutureRecurringActivitiesUpdatesFutureEntities(): void
    {
        $user = new User();
        $currentActivity = new PlannedActivity();
        $ref = new \ReflectionProperty(PlannedActivity::class, 'id');
        $ref->setValue($currentActivity, 1);
        $currentActivity->setUser($user);
        $currentActivity->setTitle('Updated Title');
        $currentActivity->setHoursPerDay(7.5);
        $currentActivity->setColor('#123456');
        $currentActivity->setComment('Updated comment');
        $currentActivity->setRecurrenceGroup('rec-group-1');
        $currentActivity->setBegin(new \DateTime('2026-09-14'));

        $futureActivity1 = new PlannedActivity();
        $ref->setValue($futureActivity1, 2);
        $futureActivity1->setTitle('Old Title');
        $futureActivity1->setBegin(new \DateTime('2026-09-21'));

        $futureActivity2 = new PlannedActivity();
        $ref->setValue($futureActivity2, 3);
        $futureActivity2->setTitle('Old Title 2');
        $futureActivity2->setBegin(new \DateTime('2026-09-28'));

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$futureActivity1, $futureActivity2]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->with('p.recurrenceGroup = :group')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->expects($this->exactly(2))->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $em->expects($this->once())->method('flush');

        $repository = $this->createRepository($em);
        $repository->updateFutureRecurringActivities($currentActivity);

        $this->assertCount(2, $persisted);
        $this->assertSame($user, $futureActivity1->getUser());
        $this->assertSame('Updated Title', $futureActivity1->getTitle());
        $this->assertSame(7.5, $futureActivity1->getHoursPerDay());
        $this->assertSame('#123456', $futureActivity1->getColor());
        $this->assertSame('Updated comment', $futureActivity1->getComment());

        $this->assertSame($user, $futureActivity2->getUser());
        $this->assertSame('Updated Title', $futureActivity2->getTitle());
        $this->assertSame(7.5, $futureActivity2->getHoursPerDay());
        $this->assertSame('#123456', $futureActivity2->getColor());
        $this->assertSame('Updated comment', $futureActivity2->getComment());
    }

    public function testFindPlannedActivitiesForEmptyUsers(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createRepository($em);
        $results = $repository->findPlannedActivitiesForUsersAndRange([], new \DateTime('2026-09-01'), new \DateTime('2026-09-07'));

        $this->assertSame([], $results);
    }

    public function testFindPlannedActivitiesForUsersAndRange(): void
    {
        $user = new User();
        $activity = new PlannedActivity();
        $activity->setUser($user);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$activity]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $repository = $this->createRepository($em);
        $results = $repository->findPlannedActivitiesForUserAndRange($user, new \DateTime('2026-09-01'), new \DateTime('2026-09-07'));

        $this->assertCount(1, $results);
        $this->assertSame($activity, $results[0]);
    }

    public function testCreateRecurringActivitiesWithZeroOrNegativeWeeks(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $repository = $this->createRepository($em);

        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setEnd(new \DateTime('2026-09-09'));

        $this->assertSame([], $repository->createRecurringActivities($activity, 0));
        $this->assertSame([], $repository->createRecurringActivities($activity, -2));
    }

    public function testCreateRecurringActivitiesWithPositiveWeeks(): void
    {
        $user = new User();
        $activity = new PlannedActivity();
        $activity->setUser($user);
        $activity->setTitle('Sprint Planning');
        $activity->setHoursPerDay(4.5);
        $activity->setColor('#ff0000');
        $activity->setComment('Weekly meeting');
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setEnd(new \DateTime('2026-09-09'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(3))->method('persist');
        $em->expects($this->exactly(3))->method('flush');

        $repository = $this->createRepository($em);
        $created = $repository->createRecurringActivities($activity, 2);

        $this->assertCount(2, $created);
        $this->assertNotNull($activity->getRecurrenceGroup());
        $this->assertNotEmpty($activity->getRecurrenceGroup());

        // Week 1 recurrence
        $this->assertSame($user, $created[0]->getUser());
        $this->assertSame('Sprint Planning', $created[0]->getTitle());
        $this->assertSame(4.5, $created[0]->getHoursPerDay());
        $this->assertSame('#ff0000', $created[0]->getColor());
        $this->assertSame('Weekly meeting', $created[0]->getComment());
        $this->assertSame($activity->getRecurrenceGroup(), $created[0]->getRecurrenceGroup());
        $this->assertSame('2026-09-14', $created[0]->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-16', $created[0]->getEnd()?->format('Y-m-d'));

        // Week 2 recurrence
        $this->assertSame($user, $created[1]->getUser());
        $this->assertSame('Sprint Planning', $created[1]->getTitle());
        $this->assertSame(4.5, $created[1]->getHoursPerDay());
        $this->assertSame('#ff0000', $created[1]->getColor());
        $this->assertSame('Weekly meeting', $created[1]->getComment());
        $this->assertSame($activity->getRecurrenceGroup(), $created[1]->getRecurrenceGroup());
        $this->assertSame('2026-09-21', $created[1]->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-23', $created[1]->getEnd()?->format('Y-m-d'));
    }
}
