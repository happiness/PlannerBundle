<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\Entity\Project;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Confirms all tentative work of a project at once, e.g. when the deal is signed.
 *
 * Only ongoing and future activities change, past ones stay as they were planned. Activities of users the current user
 * may not edit are skipped and stay tentative.
 *
 * @final not final for mocking in tests
 */
class TentativeConfirmer
{
    public function __construct(
        private readonly PlannedActivityRepository $repository,
        private readonly AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    public function prepare(Project $project, ?\DateTimeInterface $today = null): TentativeConfirmation
    {
        $today ??= new \DateTimeImmutable('today');

        $activities = [];
        $skipped = [];
        foreach ($this->repository->findTentativeForProject($project, $today) as $activity) {
            if ($this->authorizationChecker->isGranted(PlannedActivityVoter::EDIT, $activity)) {
                $activities[] = $activity;
            } else {
                $skipped[] = $activity;
            }
        }

        return new TentativeConfirmation($project, $activities, $skipped);
    }

    public function confirm(TentativeConfirmation $confirmation): void
    {
        $this->repository->confirmPlannedActivities($confirmation->getActivities());
    }
}
