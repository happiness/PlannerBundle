<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\Configuration\SystemConfiguration;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\DateTimeFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds the projects a user logged most often lately.
 */
class CommonProjectService
{
    public const AMOUNT = 10;

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly SystemConfiguration $configuration)
    {
    }

    /**
     * @return list<Project>
     */
    public function getProjects(User $user, int $amount = self::AMOUNT): array
    {
        if ($amount < 1) {
            return [];
        }

        $now = DateTimeFactory::createByUser($user)->createDateTime();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('IDENTITY(t.project) AS projectId', 'COUNT(t.id) AS cnt', 'MAX(t.begin) AS lastBegin')
            ->from(Timesheet::class, 't')
            ->andWhere($qb->expr()->eq('t.user', ':user'))
            ->groupBy('t.project')
            ->orderBy('cnt', 'DESC')
            ->addOrderBy('lastBegin', 'DESC')
            // fetch more than needed, as invisible projects are removed afterwards
            ->setMaxResults($amount * 3)
            ->setParameter('user', $user);

        $weeks = $this->configuration->find('quick_entry.recent_activity_weeks');
        if (is_numeric($weeks) && (int) $weeks > 0) {
            $qb->andWhere($qb->expr()->gte('t.begin', ':begin'))
                ->setParameter('begin', \DateTimeImmutable::createFromInterface($now)->modify(\sprintf('-%d weeks', (int) $weeks)));
        }

        /** @var list<array{projectId: int|string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();
        if ($rows === []) {
            return [];
        }

        $projects = [];
        foreach ($this->entityManager->getRepository(Project::class)->findBy(['id' => array_map('intval', array_column($rows, 'projectId'))]) as $project) {
            $projects[(int) $project->getId()] = $project;
        }

        $result = [];
        foreach ($rows as $row) {
            $project = $projects[(int) $row['projectId']] ?? null;
            if ($project === null) {
                continue;
            }

            $customer = $project->getCustomer();
            if ($customer === null || !$customer->isVisible() || !$project->isVisibleAtDate($now)) {
                continue;
            }

            $result[] = $project;
            if (\count($result) >= $amount) {
                break;
            }
        }

        return $result;
    }
}
