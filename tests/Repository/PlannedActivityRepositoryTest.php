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
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $repository = $this->createRepository($em);
        $created = $repository->createRecurringActivities($activity, 2);

        $this->assertCount(2, $created);

        // Week 1 recurrence
        $this->assertSame($user, $created[0]->getUser());
        $this->assertSame('Sprint Planning', $created[0]->getTitle());
        $this->assertSame(4.5, $created[0]->getHoursPerDay());
        $this->assertSame('#ff0000', $created[0]->getColor());
        $this->assertSame('Weekly meeting', $created[0]->getComment());
        $this->assertSame('2026-09-14', $created[0]->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-16', $created[0]->getEnd()?->format('Y-m-d'));

        // Week 2 recurrence
        $this->assertSame($user, $created[1]->getUser());
        $this->assertSame('Sprint Planning', $created[1]->getTitle());
        $this->assertSame(4.5, $created[1]->getHoursPerDay());
        $this->assertSame('#ff0000', $created[1]->getColor());
        $this->assertSame('Weekly meeting', $created[1]->getComment());
        $this->assertSame('2026-09-21', $created[1]->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-09-23', $created[1]->getEnd()?->format('Y-m-d'));
    }
}
