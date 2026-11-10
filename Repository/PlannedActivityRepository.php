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

    /**
     * Confirms tentative activities in one flush.
     *
     * @param array<PlannedActivity> $activities
     */
    public function confirmPlannedActivities(array $activities): void
    {
        if ($activities === []) {
            return;
        }

        $entityManager = $this->getEntityManager();
        foreach ($activities as $activity) {
            $activity->setTentative(false);
            $entityManager->persist($activity);
        }
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

        $splitActivity = new PlannedActivity();
        $splitActivity->setUser($activity->getUser());
        $splitActivity->setCustomer($activity->getCustomer());
        $splitActivity->setProject($activity->getProject());
        $splitActivity->setTitle($activity->getTitle());
        $splitActivity->setHoursPerDay($activity->getHoursPerDay());
        $splitActivity->setTimeOff($activity->isTimeOff());
        $splitActivity->setColor($activity->getColor());
        $splitActivity->setComment($activity->getComment());
        $splitActivity->setTentative($activity->isTentative());
        $splitActivity->setRecurrenceGroup($activity->getRecurrenceGroup());
        $splitActivity->setBegin(new \DateTime($secondBegin->format('Y-m-d')));
        $splitActivity->setEnd(new \DateTime($originalEnd->format('Y-m-d')));

        $this->savePlannedActivity($splitActivity);
    }

    /**
     * Other activities of the same user and customer/project sharing at least one day with this one, confirmed or
     * tentative like it (confirmed and tentative work on the same project may well sit side by side). Legacy
     * activities without a customer, and time off, are not checked: they have nothing to compare.
     */
    public function countOverlapping(PlannedActivity $activity): int
    {
        $begin = $activity->getBegin();
        $end = $activity->getEnd();
        if ($begin === null || $end === null) {
            return 0;
        }

        return $this->countOverlappingFor($activity, $begin, $end, $activity->getId() === null ? [] : [$activity->getId()]);
    }

    /**
     * @param array<int> $excludeIds
     */
    private function countOverlappingFor(PlannedActivity $activity, \DateTimeInterface $begin, \DateTimeInterface $end, array $excludeIds): int
    {
        if ($activity->getUser() === null || $activity->getCustomer() === null) {
            return 0;
        }

        $qb = $this->createQueryBuilder('p');
        $qb->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->andWhere('p.customer = :customer')
            ->andWhere('p.begin <= :end')
            ->andWhere('p.end >= :begin')
            ->andWhere('p.tentative = :tentative')
            ->setParameter('user', $activity->getUser())
            ->setParameter('tentative', $activity->isTentative())
            ->setParameter('customer', $activity->getCustomer())
            ->setParameter('begin', $begin->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'));

        if ($activity->getProject() === null) {
            $qb->andWhere('p.project IS NULL');
        } else {
            $qb->andWhere('p.project = :project')->setParameter('project', $activity->getProject());
        }

        if ($excludeIds !== []) {
            $qb->andWhere('p.id NOT IN (:ids)')->setParameter('ids', $excludeIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Splits a multi-day activity in two: the original ends the day before $secondBegin, a copy with
     * everything else (see createCopy()) covers $secondBegin to the old end.
     * Both are written in one flush, so a failure leaves the activity as it was.
     *
     * @return PlannedActivity|null the new second part, null when $secondBegin is not a day after the begin within the range
     */
    public function splitActivity(PlannedActivity $activity, \DateTimeInterface $secondBegin): ?PlannedActivity
    {
        $begin = $activity->getBegin();
        $end = $activity->getEnd();
        $day = $secondBegin->format('Y-m-d');
        if ($begin === null || $end === null || $day <= $begin->format('Y-m-d') || $day > $end->format('Y-m-d')) {
            return null;
        }

        $second = $this->createCopy($activity);
        $second->setBegin(new \DateTime($day));
        $second->setEnd(new \DateTime($end->format('Y-m-d')));

        $activity->setEnd((new \DateTime($day))->modify('-1 day'));

        $entityManager = $this->getEntityManager();
        $entityManager->persist($activity);
        $entityManager->persist($second);
        $entityManager->flush();

        return $second;
    }

    /**
     * Joins two adjacent parts of the same planning (see PlannedActivity::isJoinableWith()) back into the first one.
     */
    public function joinActivities(PlannedActivity $first, PlannedActivity $second): bool
    {
        if (!$first->isJoinableWith($second) || $second->getEnd() === null) {
            return false;
        }

        $first->setEnd(new \DateTime($second->getEnd()->format('Y-m-d')));

        $entityManager = $this->getEntityManager();
        $entityManager->persist($first);
        $entityManager->remove($second);
        $entityManager->flush();

        return true;
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
        $copy->setTimeOff($activity->isTimeOff());
        $copy->setColor($activity->getColor());
        $copy->setComment($activity->getComment());
        $copy->setTentative($activity->isTentative());
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

    /**
     * Copies the edited activity to the following activities of its recurring series.
     *
     * Pass the dates the activity had before the edit to move the following activities
     * as well: their begin and end are shifted by the same number of days, so an activity
     * stretched from Thursday to Monday-Friday covers Monday-Friday in every following week.
     */
    public function updateFutureRecurringActivities(PlannedActivity $activity, ?\DateTimeInterface $previousBegin = null, ?\DateTimeInterface $previousEnd = null): void
    {
        $recurrenceGroup = $activity->getRecurrenceGroup();
        $begin = $activity->getBegin();
        if ($recurrenceGroup === null || $recurrenceGroup === '' || $begin === null) {
            return;
        }

        $beginShift = $previousBegin !== null ? $this->daysBetween($previousBegin, $begin) : 0;
        $end = $activity->getEnd();
        $endShift = $previousEnd !== null && $end !== null ? $this->daysBetween($previousEnd, $end) : 0;

        // "Future" is measured from where the activity was, so moving it later does not skip the activities it passed
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.recurrenceGroup = :group')
            ->andWhere('p.id != :currentId')
            ->andWhere('p.begin >= :begin')
            ->setParameter('group', $recurrenceGroup)
            ->setParameter('currentId', $activity->getId())
            ->setParameter('begin', ($previousBegin ?? $begin)->format('Y-m-d'));

        /** @var array<PlannedActivity> $futureActivities */
        $futureActivities = $qb->getQuery()->getResult();

        $entityManager = $this->getEntityManager();
        foreach ($futureActivities as $future) {
            $this->shiftDates($future, $beginShift, $endShift);
            $future->setUser($activity->getUser());
            $future->setCustomer($activity->getCustomer());
            $future->setProject($activity->getProject());
            $future->setTitle($activity->getTitle());
            $future->setHoursPerDay($activity->getHoursPerDay());
            $future->setTimeOff($activity->isTimeOff());
            $future->setColor($activity->getColor());
            $future->setComment($activity->getComment());
            $future->setTentative($activity->isTentative());
            $entityManager->persist($future);
        }
        $entityManager->flush();
    }

    private function daysBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $from = new \DateTimeImmutable($from->format('Y-m-d'));
        $to = new \DateTimeImmutable($to->format('Y-m-d'));

        return (int) $from->diff($to)->format('%r%a');
    }

    private function shiftDates(PlannedActivity $activity, int $beginShift, int $endShift): void
    {
        $begin = $activity->getBegin();
        $end = $activity->getEnd();
        if ($begin === null || $end === null || ($beginShift === 0 && $endShift === 0)) {
            return;
        }

        $newBegin = (new \DateTime($begin->format('Y-m-d')))->modify(\sprintf('%+d days', $beginShift));
        $newEnd = (new \DateTime($end->format('Y-m-d')))->modify(\sprintf('%+d days', $endShift));
        // an activity that was shorter than the edited one must not end before it begins
        if ($newEnd < $newBegin) {
            $newEnd = clone $newBegin;
        }

        $activity->setBegin($newBegin);
        $activity->setEnd($newEnd);
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
            $recurring->setTimeOff($activity->isTimeOff());
            $recurring->setColor($activity->getColor());
            $recurring->setComment($activity->getComment());
            $recurring->setTentative($activity->isTentative());
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
     * @param bool|null $tentative true for tentative activities only, false for confirmed ones only, null for both
     * @return array<PlannedActivity>
     */
    public function findByFilter(
        array $users,
        ?\DateTimeInterface $begin = null,
        ?\DateTimeInterface $end = null,
        array $customers = [],
        array $projects = [],
        ?bool $tentative = null
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

        if ($tentative !== null) {
            $qb->andWhere('p.tentative = :tentative')->setParameter('tentative', $tentative);
        }

        /** @var array<PlannedActivity> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Tentative activities of a project that end on or after the given day, of every user.
     *
     * @return array<PlannedActivity>
     */
    public function findTentativeForProject(Project $project, \DateTimeInterface $from): array
    {
        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.project = :project')
            ->andWhere('p.tentative = :tentative')
            ->andWhere('p.end >= :from')
            ->orderBy('p.begin', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setParameter('project', $project)
            ->setParameter('tentative', true)
            ->setParameter('from', $from->format('Y-m-d'));

        /** @var array<PlannedActivity> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
