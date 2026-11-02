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

    public const ORDER_BY_NAME = 'username';
    public const ORDER_BY_FREE_HOURS = 'free';
    public const ORDER_BY_CHOICES = [self::ORDER_BY_NAME, self::ORDER_BY_FREE_HOURS];

    private string $planning = self::PLANNING_ALL;

    public function __construct()
    {
        $this->setDefaults([
            'order' => self::ORDER_ASC,
            'orderBy' => self::ORDER_BY_NAME,
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
     * Users are listed by name, or with the most free hours of the shown weeks first.
     */
    public function setOrderBy(?string $orderBy): self
    {
        return parent::setOrderBy(\in_array($orderBy, self::ORDER_BY_CHOICES, true) ? $orderBy : self::ORDER_BY_NAME);
    }

    public function isOrderedByFreeHours(): bool
    {
        return $this->getOrderBy() === self::ORDER_BY_FREE_HOURS;
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
