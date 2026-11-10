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
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;

/**
 * The tentative activities of a project that "confirm all" would change, and how many it has to leave alone.
 */
final class TentativeConfirmation
{
    /**
     * @param array<PlannedActivity> $activities the ones the current user may edit
     * @param array<PlannedActivity> $skipped the ones they may not edit, they stay tentative
     */
    public function __construct(
        private readonly Project $project,
        private readonly array $activities,
        private readonly array $skipped = []
    ) {
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    /**
     * @return array<PlannedActivity>
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * @return array<PlannedActivity>
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    public function getCount(): int
    {
        return \count($this->activities);
    }

    public function getSkippedCount(): int
    {
        return \count($this->skipped);
    }

    public function isEmpty(): bool
    {
        return $this->activities === [];
    }

    public function getUserCount(): int
    {
        $users = [];
        foreach ($this->activities as $activity) {
            $users[$activity->getUser()?->getId() ?? spl_object_id($activity)] = true;
        }

        return \count($users);
    }

    public function getBegin(): ?\DateTimeInterface
    {
        $begin = null;
        foreach ($this->activities as $activity) {
            $date = $activity->getBegin();
            if ($date !== null && ($begin === null || $date < $begin)) {
                $begin = $date;
            }
        }

        return $begin;
    }

    public function getEnd(): ?\DateTimeInterface
    {
        $end = null;
        foreach ($this->activities as $activity) {
            $date = $activity->getEnd();
            if ($date !== null && ($end === null || $date > $end)) {
                $end = $date;
            }
        }

        return $end;
    }
}
