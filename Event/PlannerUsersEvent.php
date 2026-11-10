<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched with the users whose plans the current user is about to see, in the planner and its API. Listeners may
 * leave users out (accounts that are not people, say), never add any: the list is already limited to what the current
 * user is allowed to see.
 */
final class PlannerUsersEvent extends Event
{
    /**
     * @param array<User> $users
     */
    public function __construct(
        private readonly User $currentUser,
        private array $users,
    ) {
    }

    public function getCurrentUser(): User
    {
        return $this->currentUser;
    }

    /**
     * @return array<User>
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function removeUser(User $user): void
    {
        $this->users = array_values(array_filter($this->users, static fn (User $candidate): bool => $candidate !== $user));
    }
}
