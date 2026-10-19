<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\UserRepository;
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
     * @return array<User>
     */
    public function getVisibleUsers(User $currentUser): array
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

        return $this->userRepository->getUsersForQuery($query);
    }
}
