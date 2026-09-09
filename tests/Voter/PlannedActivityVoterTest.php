<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Voter;

use App\Entity\Team;
use App\Entity\User;
use App\Tests\Voter\AbstractVoterTestCase;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class PlannedActivityVoterTest extends AbstractVoterTestCase
{
    public function testVoteForOwner(): void
    {
        $user = self::getUser(1, User::ROLE_USER);

        $activity = new PlannedActivity();
        $activity->setUser($user);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $manager = $this->getRolePermissionManager([
            'ROLE_USER' => [
                'view_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
            ],
        ], true);

        $voter = new PlannedActivityVoter($manager);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['view']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['create']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['edit']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['delete']));
    }

    public function testVoteForTeamlead(): void
    {
        $member = self::getUser(1, User::ROLE_USER);
        $teamlead = self::getUser(2, User::ROLE_TEAMLEAD);

        $team = new Team('Team 1');
        $team->addTeamlead($teamlead);
        $team->addUser($member);

        $activity = new PlannedActivity();
        $activity->setUser($member);

        $token = new UsernamePasswordToken($teamlead, 'main', $teamlead->getRoles());

        $manager = $this->getRolePermissionManager([
            'ROLE_TEAMLEAD' => [
                'view_other_planner',
                'edit_other_planner',
                'create_planner',
                'edit_planner',
                'delete_planner',
            ],
        ], true);

        $voter = new PlannedActivityVoter($manager);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['view']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['create']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['edit']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['delete']));
    }

    public function testVoteForUnrelatedUser(): void
    {
        $user1 = self::getUser(1, User::ROLE_USER);
        $user2 = self::getUser(2, User::ROLE_USER);

        $activity = new PlannedActivity();
        $activity->setUser($user1);

        $token = new UsernamePasswordToken($user2, 'main', $user2->getRoles());

        $manager = $this->getRolePermissionManager([
            'ROLE_USER' => [
                'view_planner',
                'create_planner',
            ],
        ], true);

        $voter = new PlannedActivityVoter($manager);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $activity, ['view']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $activity, ['create']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $activity, ['edit']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $activity, ['delete']));
    }

    public function testVoteForAdminWithViewAllData(): void
    {
        $user1 = self::getUser(1, User::ROLE_USER);
        $admin = self::getUser(99, User::ROLE_ADMIN);

        $activity = new PlannedActivity();
        $activity->setUser($user1);

        $token = new UsernamePasswordToken($admin, 'main', $admin->getRoles());

        $manager = $this->getRolePermissionManager([
            'ROLE_ADMIN' => [
                'view_all_data',
                'create_planner',
                'edit_planner',
                'delete_planner',
            ],
        ], true);

        $voter = new PlannedActivityVoter($manager);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['view']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['create']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['edit']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $activity, ['delete']));
    }
}
