<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Entity\Reservation;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * @return array<Reservation> in the order the planner shows them, archived ones included
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return array<Reservation> the ones offered for new planning, in order
     */
    public function findSelectable(): array
    {
        return $this->findBy(['archived' => false], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * Puts a new reservation last and saves it.
     */
    public function create(Reservation $reservation): void
    {
        $last = $this->createQueryBuilder('r')->select('MAX(r.position)')->getQuery()->getSingleScalarResult();
        $reservation->setPosition($last === null ? 0 : (int) $last + 1);

        $this->save($reservation);
    }

    public function save(Reservation $reservation): void
    {
        // entities use explicit change tracking, an unpersisted change is never written
        $this->getEntityManager()->persist($reservation);
        $this->getEntityManager()->flush();
    }

    /**
     * Only for one without planning: the foreign key refuses the rest, they are archived instead.
     */
    public function delete(Reservation $reservation): void
    {
        $this->getEntityManager()->remove($reservation);
        $this->getEntityManager()->flush();
    }

    /**
     * Swaps the reservation with its neighbour, renumbering all of them so equal positions sort out too.
     */
    public function move(Reservation $reservation, int $direction): void
    {
        $all = array_values($this->findAllOrdered());
        $index = array_search($reservation, $all, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($target === false || $target < 0 || $target >= \count($all)) {
            return;
        }

        [$all[$index], $all[$target]] = [$all[$target], $all[$index]];
        foreach ($all as $position => $item) {
            $item->setPosition($position);
            $this->getEntityManager()->persist($item);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * How many plannings each reservation has, archived ones included.
     *
     * @return array<int, int> by reservation ID
     */
    public function countPlannings(): array
    {
        /** @var array<array{reservation: int|string, plannings: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.reservation) AS reservation', 'COUNT(p.id) AS plannings')
            ->from(PlannedActivity::class, 'p')
            ->where('p.reservation IS NOT NULL')
            ->groupBy('p.reservation')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['reservation']] = (int) $row['plannings'];
        }

        return $counts;
    }
}
