<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Repository;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;

/**
 * @extends ServiceEntityRepository<PlannedActivity>
 */
class PlannedActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlannedActivity::class);
    }

    public function savePlannedActivity(PlannedActivity $activity): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($activity);
        $entityManager->flush();
    }

    public function findCustomer(int $id): ?Customer
    {
        return $this->getEntityManager()->find(Customer::class, $id);
    }

    public function findProject(int $id): ?Project
    {
        return $this->getEntityManager()->find(Project::class, $id);
    }

    public function deletePlannedActivity(PlannedActivity $activity): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($activity);
        $entityManager->flush();
    }

    public function removeDayFromActivity(PlannedActivity $activity, \DateTimeInterface $targetDay): void
    {
        $begin = $activity->getBegin();
        $end = $activity->getEnd();
        if ($begin === null || $end === null) {
            $this->deletePlannedActivity($activity);

            return;
        }

        $targetStr = $targetDay->format('Y-m-d');
        $beginStr = $begin->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        if ($targetStr < $beginStr || $targetStr > $endStr || ($beginStr === $endStr && $targetStr === $beginStr)) {
            $this->deletePlannedActivity($activity);

            return;
        }

        if ($targetStr === $beginStr) {
            $activity->setBegin((clone $begin)->modify('+1 day'));
            $this->savePlannedActivity($activity);

            return;
        }

        if ($targetStr === $endStr) {
            $activity->setEnd((clone $end)->modify('-1 day'));
            $this->savePlannedActivity($activity);

            return;
        }

        // Target day is in the middle: split into two activities
        $targetDate = \DateTime::createFromInterface($targetDay);
        $firstEnd = (clone $targetDate)->modify('-1 day');
        $secondBegin = (clone $targetDate)->modify('+1 day');
        $originalEnd = clone $end;

        $activity->setEnd(new \DateTime($firstEnd->format('Y-m-d')));
        $this->savePlannedActivity($activity);

        $splitActivity = $this->createCopy($activity);
        $splitActivity->setBegin(new \DateTime($secondBegin->format('Y-m-d')));
        $splitActivity->setEnd(new \DateTime($originalEnd->format('Y-m-d')));

        $this->savePlannedActivity($splitActivity);
    }

    /**
     * Saves the activity over the given date ranges: the activity itself takes the first range, a copy each of
     * the others (see WorkingDayRanges). All are written in one flush. Without ranges the activity is saved as it is.
     *
     * Several parts share one recurrence group, so they can be edited or deleted together ("this and all following")
     * like the planning they came from, also when a mistyped end date produced many of them.
     *
     * @param array<array{0: \DateTime, 1: \DateTime}> $ranges
     * @return non-empty-array<PlannedActivity> the saved activities, the given one first
     */
    public function savePlannedActivityInRanges(PlannedActivity $activity, array $ranges): array
    {
        if (\count($ranges) > 1 && !$activity->isRecurring()) {
            $activity->setRecurrenceGroup(bin2hex(random_bytes(16)));
        }

        $activities = [$activity];
        foreach (array_values($ranges) as $i => [$begin, $end]) {
            $part = $i === 0 ? $activity : $this->createCopy($activity);
            $part->setBegin($begin);
            $part->setEnd($end);
            if ($i > 0) {
                $activities[] = $part;
            }
        }

        $entityManager = $this->getEntityManager();
        foreach ($activities as $part) {
            $entityManager->persist($part);
        }
        $entityManager->flush();

        return $activities;
    }

    /**
     * Repeats several activities for the following weeks as one recurring series.
     *
     * @param array<PlannedActivity> $activities
     */
    public function createRecurringActivitiesForAll(array $activities, int $weeks): void
    {
        $group = null;
        foreach ($activities as $activity) {
            if ($group !== null) {
                $activity->setRecurrenceGroup($group);
                $this->savePlannedActivity($activity);
            }
            $this->createRecurringActivities($activity, $weeks);
            $group ??= $activity->getRecurrenceGroup();
        }
    }

    /**
     * Everything but the dates, which the caller sets.
     */
    private function createCopy(PlannedActivity $activity): PlannedActivity
    {
        $copy = new PlannedActivity();
        $copy->setUser($activity->getUser());
        $copy->setCustomer($activity->getCustomer());
        $copy->setProject($activity->getProject());
        $copy->setTitle($activity->getTitle());
        $copy->setHoursPerDay($activity->getHoursPerDay());
        $copy->setColor($activity->getColor());
        $copy->setComment($activity->getComment());
        $copy->setRecurrenceGroup($activity->getRecurrenceGroup());

        return $copy;
    }

    public function deleteRecurringActivities(string $recurrenceGroup): void
    {
        $activities = $this->findBy(['recurrenceGroup' => $recurrenceGroup]);
        $entityManager = $this->getEntityManager();
        foreach ($activities as $activity) {
            $entityManager->remove($activity);
        }
        $entityManager->flush();
    }

    public function deleteFutureRecurringActivities(PlannedActivity $activity): void
    {
        $recurrenceGroup = $activity->getRecurrenceGroup();
        $begin = $activity->getBegin();
        if ($recurrenceGroup === null || $recurrenceGroup === '' || $begin === null) {
            $this->deletePlannedActivity($activity);

            return;
        }

        $qb = $this->createQueryBuilder('p');
        $qb->where('p.recurrenceGroup = :group')
            ->andWhere('p.begin >= :begin')
            ->setParameter('group', $recurrenceGroup)
            ->setParameter('begin', $begin->format('Y-m-d'));

        /** @var array<PlannedActivity> $futureActivities */
        $futureActivities = $qb->getQuery()->getResult();

        $entityManager = $this->getEntityManager();
        foreach ($futureActivities as $future) {
            $entityManager->remove($future);
        }
        $entityManager->flush();
    }

    public function updateFutureRecurringActivities(PlannedActivity $activity): void
    {
        $recurrenceGroup = $activity->getRecurrenceGroup();
        $begin = $activity->getBegin();
        if ($recurrenceGroup === null || $recurrenceGroup === '' || $begin === null) {
            return;
        }

        $qb = $this->createQueryBuilder('p');
        $qb->where('p.recurrenceGroup = :group')
            ->andWhere('p.id != :currentId')
            ->andWhere('p.begin >= :begin')
            ->setParameter('group', $recurrenceGroup)
            ->setParameter('currentId', $activity->getId())
            ->setParameter('begin', $begin->format('Y-m-d'));

        /** @var array<PlannedActivity> $futureActivities */
        $futureActivities = $qb->getQuery()->getResult();

        $entityManager = $this->getEntityManager();
        foreach ($futureActivities as $future) {
            $future->setUser($activity->getUser());
            $future->setCustomer($activity->getCustomer());
            $future->setProject($activity->getProject());
            $future->setTitle($activity->getTitle());
            $future->setHoursPerDay($activity->getHoursPerDay());
            $future->setColor($activity->getColor());
            $future->setComment($activity->getComment());
            $entityManager->persist($future);
        }
        $entityManager->flush();
    }

    /**
     * @return array<PlannedActivity>
     */
    public function createRecurringActivities(PlannedActivity $activity, int $weeks): array
    {
        $begin = $activity->getBegin();
        $end = $activity->getEnd();
        if ($weeks <= 0 || $begin === null || $end === null) {
            return [];
        }

        if ($activity->getRecurrenceGroup() === null || $activity->getRecurrenceGroup() === '') {
            $activity->setRecurrenceGroup(bin2hex(random_bytes(16)));
            $this->savePlannedActivity($activity);
        }

        $created = [];
        for ($i = 1; $i <= $weeks; $i++) {
            $recurring = new PlannedActivity();
            $recurring->setUser($activity->getUser());
            $recurring->setCustomer($activity->getCustomer());
            $recurring->setProject($activity->getProject());
            $recurring->setTitle($activity->getTitle());
            $recurring->setHoursPerDay($activity->getHoursPerDay());
            $recurring->setColor($activity->getColor());
            $recurring->setComment($activity->getComment());
            $recurring->setRecurrenceGroup($activity->getRecurrenceGroup());

            $recBegin = (clone $begin)->modify(\sprintf('+%d week', $i));
            $recEnd = (clone $end)->modify(\sprintf('+%d week', $i));

            $recurring->setBegin($recBegin);
            $recurring->setEnd($recEnd);

            $this->savePlannedActivity($recurring);
            $created[] = $recurring;
        }

        return $created;
    }

    /**
     * @param array<User> $users
     * @return array<PlannedActivity>
     */
    public function findPlannedActivitiesForUsersAndRange(array $users, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        if ($users === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.user', 'u')
            ->leftJoin('p.customer', 'c')
            ->leftJoin('p.project', 'pr')
            ->addSelect('u', 'c', 'pr')
            ->where('p.user IN (:users)')
            ->andWhere('p.begin <= :end')
            ->andWhere('p.end >= :start')
            ->orderBy('p.begin', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('pr.name', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->setParameter('users', $users)
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'));

        /** @var array<PlannedActivity> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return array<PlannedActivity>
     */
    public function findPlannedActivitiesForUserAndRange(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->findPlannedActivitiesForUsersAndRange([$user], $start, $end);
    }

    /**
     * @param array<User> $users
     * @param array<Customer> $customers
     * @param array<Project> $projects
     * @return array<PlannedActivity>
     */
    public function findByFilter(
        array $users,
        ?\DateTimeInterface $begin = null,
        ?\DateTimeInterface $end = null,
        array $customers = [],
        array $projects = []
    ): array {
        if ($users === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.user', 'u')
            ->leftJoin('p.customer', 'c')
            ->leftJoin('p.project', 'pr')
            ->addSelect('u', 'c', 'pr')
            ->where('p.user IN (:users)')
            ->orderBy('p.begin', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setParameter('users', $users);

        if ($begin !== null) {
            $qb->andWhere('p.end >= :begin')->setParameter('begin', $begin->format('Y-m-d'));
        }

        if ($end !== null) {
            $qb->andWhere('p.begin <= :end')->setParameter('end', $end->format('Y-m-d'));
        }

        if ($customers !== []) {
            $qb->andWhere('p.customer IN (:customers)')->setParameter('customers', $customers);
        }

        if ($projects !== []) {
            $qb->andWhere('p.project IN (:projects)')->setParameter('projects', $projects);
        }

        /** @var array<PlannedActivity> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
