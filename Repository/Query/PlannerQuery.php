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

    public const PLANNING_ALL = 'all';
    public const PLANNING_CONFIRMED = 'confirmed';
    public const PLANNING_TENTATIVE = 'tentative';
    public const PLANNING_CHOICES = [self::PLANNING_ALL, self::PLANNING_CONFIRMED, self::PLANNING_TENTATIVE];

    private string $planning = self::PLANNING_ALL;

    public function __construct()
    {
        $this->setDefaults([
            'order' => self::ORDER_ASC,
            'orderBy' => 'username',
            'teams' => [],
            'users' => [],
            'planning' => self::PLANNING_ALL,
        ]);
    }

    /**
     * Which planned activities are shown: all, confirmed only or tentative only. The capacity figures always count all.
     */
    public function getPlanning(): string
    {
        return $this->planning;
    }

    public function setPlanning(?string $planning): self
    {
        $this->planning = \in_array($planning, self::PLANNING_CHOICES, true) ? $planning : self::PLANNING_ALL;

        return $this;
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
            $this->setPlanning($query->getPlanning());
        }
    }
}
