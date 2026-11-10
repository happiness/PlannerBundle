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
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
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

    private function createSettings(bool $hash): CalendarFeedSettings
    {
        $settings = $this->createStub(CalendarFeedSettings::class);
        $settings->method('hashTokens')->willReturn($hash);

        return $settings;
    }

    public function testFirstResetCreatesAHashedToken(): void
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

        $token = (new CalendarFeedTokens($entityManager, $this->createSettings(true)))->resetToken($user);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertInstanceOf(CalendarFeedToken::class, $persisted);
        $this->assertSame($user, $persisted->getUser());
        // only the hash is kept
        $this->assertTrue($persisted->isHashed());
        $this->assertSame(hash('sha256', $token), $persisted->getToken());
    }

    public function testWithoutHashingTheTokenIsKeptAndShownAgain(): void
    {
        $user = $this->createUser();
        $existing = new CalendarFeedToken($user, str_repeat('a', 64));
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['user' => $user])->willReturn($existing);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->once())->method('persist')->with($existing);
        $entityManager->expects($this->once())->method('flush');

        $tokens = new CalendarFeedTokens($entityManager, $this->createSettings(false));
        $this->assertTrue($tokens->hasFeed($user));
        $this->assertSame(str_repeat('a', 64), $tokens->getToken($user));

        $token = $tokens->resetToken($user);

        $this->assertNotSame(str_repeat('a', 64), $token);
        $this->assertFalse($existing->isHashed());
        $this->assertSame($token, $existing->getToken());
        $this->assertSame($token, $tokens->getToken($user));
    }

    public function testAHashedTokenCannotBeShown(): void
    {
        $user = $this->createUser();
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new CalendarFeedToken($user, hash('sha256', str_repeat('a', 64)), true));

        $tokens = new CalendarFeedTokens($this->createEntityManager($repository), $this->createSettings(false));

        $this->assertTrue($tokens->hasFeed($user));
        $this->assertNull($tokens->getToken($user));
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

        (new CalendarFeedTokens($entityManager, $this->createSettings(true)))->removeToken($user);
    }

    public function testRemoveWithoutTokenWritesNothing(): void
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->never())->method('remove');
        $entityManager->expects($this->never())->method('flush');

        (new CalendarFeedTokens($entityManager, $this->createSettings(true)))->removeToken($this->createUser());
    }

    public function testFindUserByHashedToken(): void
    {
        $user = $this->createUser();
        $token = str_repeat('a', 64);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('findOneBy')
            ->with(['token' => hash('sha256', $token), 'hashed' => true])
            ->willReturn(new CalendarFeedToken($user, hash('sha256', $token), true));

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->never())->method('flush');

        $this->assertSame($user, (new CalendarFeedTokens($entityManager, $this->createSettings(true)))->findUser($token));
    }

    public function testAPlainTokenIsHashedWhenItsFeedIsFetched(): void
    {
        $user = $this->createUser();
        $token = str_repeat('a', 64);
        $plain = new CalendarFeedToken($user, $token);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CalendarFeedToken => $criteria['hashed'] === false && $criteria['token'] === $token ? $plain : null);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->once())->method('persist')->with($plain);
        $entityManager->expects($this->once())->method('flush');

        $this->assertSame($plain, (new CalendarFeedTokens($entityManager, $this->createSettings(true)))->findFeed($token));
        $this->assertTrue($plain->isHashed());
        $this->assertSame(hash('sha256', $token), $plain->getToken());
    }

    public function testAPlainTokenStaysPlainWithoutHashing(): void
    {
        $user = $this->createUser();
        $token = str_repeat('a', 64);
        $plain = new CalendarFeedToken($user, $token);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?CalendarFeedToken => $criteria['hashed'] === false ? $plain : null);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->never())->method('flush');

        $this->assertSame($user, (new CalendarFeedTokens($entityManager, $this->createSettings(false)))->findUser($token));
        $this->assertFalse($plain->isHashed());
    }

    public function testCommentChoiceIsStoredOnlyWhenItChanges(): void
    {
        $user = $this->createUser();
        $existing = new CalendarFeedToken($user, str_repeat('a', 64));
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $entityManager = $this->createEntityManager($repository);
        $entityManager->expects($this->once())->method('flush');

        $tokens = new CalendarFeedTokens($entityManager, $this->createSettings(true));
        $this->assertNull($tokens->getIncludeComments($user));

        $tokens->setIncludeComments($user, true);
        $tokens->setIncludeComments($user, true);

        $this->assertTrue($tokens->getIncludeComments($user));
    }

    public function testMalformedTokensNeverReachTheDatabase(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');
        $tokens = new CalendarFeedTokens($entityManager, $this->createSettings(true));

        $this->assertNull($tokens->findUser(''));
        $this->assertNull($tokens->findUser(str_repeat('A', 64)));
        $this->assertNull($tokens->findUser(str_repeat('a', 63)));
    }

    public function testUnsavedUsersHaveNoToken(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');
        $tokens = new CalendarFeedTokens($entityManager, $this->createSettings(true));

        $this->assertNull($tokens->getToken(new User()));
        $this->assertFalse($tokens->hasFeed(new User()));
    }
}
