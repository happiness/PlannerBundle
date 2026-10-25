<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\UserRepository;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class PlannerUserProviderTest extends TestCase
{
    /**
     * @param array<string> $granted
     */
    private function createSecurity(array $granted): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $attribute): bool => \in_array($attribute, $granted, true));

        return $security;
    }

    public function testUserWithoutPermissionOnlySeesSelf(): void
    {
        $user = new User();
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('getUsersForQuery');

        $provider = new PlannerUserProvider($repository, $this->createSecurity([]));

        $this->assertSame([$user], $provider->getVisibleUsers($user));
    }

    private function createTeam(int $id, ?User $teamlead = null): Team
    {
        $team = new Team('Team ' . $id);
        (new \ReflectionProperty(Team::class, 'id'))->setValue($team, $id);
        if ($teamlead !== null) {
            $team->addTeamlead($teamlead);
        }

        return $team;
    }

    private function createUser(int $id): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    public function testViewAllDataUserSeesUsersFromQuery(): void
    {
        $user = $this->createUser(1);
        $other = $this->createUser(2);

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())
            ->method('getUsersForQuery')
            ->with($this->callback(static fn (UserQuery $query): bool => $query->getCurrentUser() === $user && $query->getOrderBy() === 'username' && $query->getSearchTeams() === []))
            ->willReturn([$user, $other]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_all_data']));

        $this->assertSame([$user, $other], $provider->getVisibleUsers($user));
    }

    public function testUserWithoutLedTeamsSeesNobody(): void
    {
        $user = $this->createUser(1);
        $user->addTeam($this->createTeam(10));

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('getUsersForQuery');

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_other_planner']));

        $this->assertSame([], $provider->getVisibleUsers($user));
    }

    public function testTeamleadIsRestrictedToLedTeams(): void
    {
        $user = $this->createUser(1);
        $led = $this->createTeam(10, $user);
        $user->addTeam($led);
        $user->addTeam($this->createTeam(11));

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())
            ->method('getUsersForQuery')
            ->with($this->callback(static fn (UserQuery $query): bool => $query->getSearchTeams() === [$led]))
            ->willReturn([$user]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_other_planner']));

        $this->assertSame([$user], $provider->getVisibleUsers($user));
    }

    public function testTeamleadCannotFilterByForeignTeam(): void
    {
        $user = $this->createUser(1);
        $user->addTeam($this->createTeam(10, $user));
        $foreign = $this->createTeam(99);

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->never())->method('getUsersForQuery');

        $plannerQuery = new PlannerQuery();
        $plannerQuery->setTeams([$foreign]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_other_planner']));

        $this->assertSame([], $provider->getVisibleUsers($user, $plannerQuery));
    }

    public function testTeamleadCanNarrowDownToLedTeam(): void
    {
        $user = $this->createUser(1);
        $first = $this->createTeam(10, $user);
        $second = $this->createTeam(11, $user);
        $user->addTeam($first);
        $user->addTeam($second);

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())
            ->method('getUsersForQuery')
            ->with($this->callback(static fn (UserQuery $query): bool => $query->getSearchTeams() === [$second]))
            ->willReturn([$user]);

        $plannerQuery = new PlannerQuery();
        $plannerQuery->setTeams([$second, $this->createTeam(99)]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_other_planner']));

        $this->assertSame([$user], $provider->getVisibleUsers($user, $plannerQuery));
    }

    public function testViewAllDataUserCanFilterByAnyTeam(): void
    {
        $user = $this->createUser(1);
        $foreign = $this->createTeam(99);

        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())
            ->method('getUsersForQuery')
            ->with($this->callback(static fn (UserQuery $query): bool => $query->getSearchTeams() === [$foreign]))
            ->willReturn([$user]);

        $plannerQuery = new PlannerQuery();
        $plannerQuery->setTeams([$foreign]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_all_data']));

        $this->assertSame([$user], $provider->getVisibleUsers($user, $plannerQuery));
    }

    public function testUserFilterRestrictsResult(): void
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);

        $repository = $this->createMock(UserRepository::class);
        $repository->method('getUsersForQuery')->willReturn([$user1, $user2]);

        $plannerQuery = new PlannerQuery();
        $plannerQuery->setUsers([$user2]);

        $provider = new PlannerUserProvider($repository, $this->createSecurity(['view_all_data']));

        $this->assertSame([$user2], $provider->getVisibleUsers($user1, $plannerQuery));
    }
}
