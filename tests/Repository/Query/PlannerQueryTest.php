<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Repository\Query;

use App\Entity\Team;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use PHPUnit\Framework\TestCase;

class PlannerQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $sut = new PlannerQuery();
        self::assertSame('username', $sut->getOrderBy());
        self::assertSame(PlannerQuery::ORDER_ASC, $sut->getOrder());
        self::assertSame([], $sut->getTeams());
        self::assertSame([], $sut->getSearchTeams());
        self::assertSame([], $sut->getUsers());
        self::assertFalse($sut->hasTeams());
        self::assertFalse($sut->hasUsers());
    }

    public function testSetAndGetUsers(): void
    {
        $sut = new PlannerQuery();
        $user1 = new User();
        $ref1 = new \ReflectionProperty(User::class, 'id');
        $ref1->setValue($user1, 1);

        $user2 = new User();
        $ref2 = new \ReflectionProperty(User::class, 'id');
        $ref2->setValue($user2, 2);

        $sut->setUsers([$user1, $user2]);
        self::assertTrue($sut->hasUsers());
        self::assertSame([$user1, $user2], array_values($sut->getUsers()));

        $sut->setUsers([]);
        self::assertFalse($sut->hasUsers());
        self::assertSame([], $sut->getUsers());
    }

    public function testSetAndGetSearchTeams(): void
    {
        $sut = new PlannerQuery();
        $team1 = new Team('Team A');
        $ref1 = new \ReflectionProperty(Team::class, 'id');
        $ref1->setValue($team1, 10);

        $team2 = new Team('Team B');
        $ref2 = new \ReflectionProperty(Team::class, 'id');
        $ref2->setValue($team2, 20);

        $sut->setSearchTeams([$team1, $team2]);
        self::assertTrue($sut->hasTeams());
        self::assertSame([$team1, $team2], array_values($sut->getSearchTeams()));
        self::assertSame([$team1, $team2], array_values($sut->getTeams()));
    }

    public function testCopyFrom(): void
    {
        $query1 = new PlannerQuery();
        $user = new User();
        $ref1 = new \ReflectionProperty(User::class, 'id');
        $ref1->setValue($user, 1);

        $team = new Team('Developers');
        $ref2 = new \ReflectionProperty(Team::class, 'id');
        $ref2->setValue($team, 10);

        $query1->setUsers([$user]);
        $query1->setSearchTeams([$team]);

        $query2 = new PlannerQuery();
        $query1->copyTo($query2);

        self::assertSame([$user], array_values($query2->getUsers()));
        self::assertSame([$team], array_values($query2->getSearchTeams()));
    }
}
