<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Calendar;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use KimaiPlugin\PlannerBundle\Entity\CalendarFeedToken;
use PHPUnit\Framework\TestCase;

class CalendarFeedTokensTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 7);

        return $user;
    }

    /**
     * @param EntityRepository<CalendarFeedToken> $repository
     */
    private function createEntityManager(EntityRepository $repository): EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(CalendarFeedToken::class)->willReturn($repository);

        return $entityManager;
    }

    public function testFirstResetCreatesAToken(): void
    {
        $user = $this->createUser();
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $persisted = null;
        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->once())->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $token = (new CalendarFeedTokens($entityManager))->resetToken($user);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertInstanceOf(CalendarFeedToken::class, $persisted);
        $this->assertSame($user, $persisted->getUser());
        $this->assertSame($token, $persisted->getToken());
    }

    public function testResetReplacesTheToken(): void
    {
        $user = $this->createUser();
        $existing = new CalendarFeedToken($user, str_repeat('a', 64));
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['user' => $user])->willReturn($existing);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->once())->method('persist')->with($existing);
        $entityManager->expects($this->once())->method('flush');

        $tokens = new CalendarFeedTokens($entityManager);
        $this->assertSame(str_repeat('a', 64), $tokens->getToken($user));

        $token = $tokens->resetToken($user);

        $this->assertNotSame(str_repeat('a', 64), $token);
        $this->assertSame($token, $existing->getToken());
    }

    public function testRemoveDeletesTheToken(): void
    {
        $user = $this->createUser();
        $existing = new CalendarFeedToken($user, str_repeat('a', 64));
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->once())->method('remove')->with($existing);
        $entityManager->expects($this->once())->method('flush');

        (new CalendarFeedTokens($entityManager))->removeToken($user);
    }

    public function testRemoveWithoutTokenWritesNothing(): void
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->never())->method('remove');
        $entityManager->expects($this->never())->method('flush');

        (new CalendarFeedTokens($entityManager))->removeToken($this->createUser());
    }

    public function testFindUserByToken(): void
    {
        $user = $this->createUser();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('findOneBy')
            ->with(['token' => str_repeat('a', 64)])
            ->willReturn(new CalendarFeedToken($user, str_repeat('a', 64)));

        $this->assertSame($user, (new CalendarFeedTokens($this->createEntityManager($repository)))->findUser(str_repeat('a', 64)));
    }

    public function testMalformedTokensNeverReachTheDatabase(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');
        $tokens = new CalendarFeedTokens($entityManager);

        $this->assertNull($tokens->findUser(''));
        $this->assertNull($tokens->findUser(str_repeat('A', 64)));
        $this->assertNull($tokens->findUser(str_repeat('a', 63)));
    }

    public function testUnsavedUsersHaveNoToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');

        $this->assertNull((new CalendarFeedTokens($entityManager))->getToken(new User()));
    }
}
