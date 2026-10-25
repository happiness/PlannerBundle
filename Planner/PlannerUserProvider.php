<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\UserRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves which users' plans the current user is allowed to see.
 */
class PlannerUserProvider
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly Security $security
    ) {
    }

    /**
     * Users without "view_all_data" only see the members of the teams they lead,
     * the optional planner query narrows the result further by teams and users.
     *
     * @return array<User>
     */
    public function getVisibleUsers(User $currentUser, ?PlannerQuery $plannerQuery = null): array
    {
        $canSeeOther = $this->security->isGranted('view_other_planner') || $this->security->isGranted('view_all_data');

        if (!$canSeeOther) {
            return [$currentUser];
        }

        $query = new UserQuery();
        $query->setSystemAccount(false);
        $query->setCurrentUser($currentUser);
        $query->setOrder(UserQuery::ORDER_ASC);
        $query->setOrderBy('username');

        $hasViewAllData = $this->security->isGranted('view_all_data');
        $ledTeamIds = [];

        if (!$hasViewAllData) {
            /** @var array<Team> $ledTeams */
            $ledTeams = array_values(array_filter($currentUser->getTeams(), static fn (Team $team): bool => $currentUser->isTeamleadOf($team)));
            if ($ledTeams === []) {
                return [];
            }
            $ledTeamIds = array_map(static fn (Team $team): ?int => $team->getId(), $ledTeams);
            $query->setSearchTeams($ledTeams);
        }

        if ($plannerQuery !== null && $plannerQuery->hasTeams()) {
            $selectedTeams = $plannerQuery->getTeams();
            if (!$hasViewAllData) {
                $selectedTeams = array_values(array_filter($selectedTeams, static fn (Team $team): bool => \in_array($team->getId(), $ledTeamIds, true)));
                if ($selectedTeams === []) {
                    return [];
                }
            }
            $query->setSearchTeams($selectedTeams);
        }

        $users = $this->userRepository->getUsersForQuery($query);

        if ($plannerQuery !== null && $plannerQuery->hasUsers()) {
            $selectedUserIds = array_map(static fn (User $user): ?int => $user->getId(), $plannerQuery->getUsers());
            $users = array_values(array_filter($users, static fn (User $user): bool => \in_array($user->getId(), $selectedUserIds, true)));
        }

        return $users;
    }
}
