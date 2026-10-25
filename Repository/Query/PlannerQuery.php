<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Repository\Query;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\UsersTrait;

final class PlannerQuery extends BaseQuery
{
    use UsersTrait;

    public function __construct()
    {
        $this->setDefaults([
            'order' => self::ORDER_ASC,
            'orderBy' => 'username',
            'teams' => [],
            'users' => [],
        ]);
    }

    /**
     * @param User[] $users
     */
    public function setUsers(array $users): self
    {
        $this->users = [];

        foreach ($users as $user) {
            $this->addUser($user);
        }

        return $this;
    }

    /**
     * @return Team[]
     */
    public function getSearchTeams(): array
    {
        return $this->getTeams();
    }

    /**
     * @param Team[] $searchTeams
     */
    public function setSearchTeams(array $searchTeams): self
    {
        return $this->setTeams($searchTeams);
    }

    protected function copyFrom(BaseQuery $query): void
    {
        parent::copyFrom($query);

        if ($query instanceof PlannerQuery) {
            $this->setUsers($query->getUsers());
        }
    }
}
