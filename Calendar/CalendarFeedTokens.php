<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Calendar;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use KimaiPlugin\PlannerBundle\Entity\CalendarFeedToken;

/**
 * The secret in a user's calendar feed URL: 64 random hex characters, one per user.
 *
 * With "Store feed addresses hashed" on (the default) only the token's SHA-256 hash is kept, so a
 * leaked database backup reveals no working feed URL; the URL can then only be shown right after
 * it is created. With it off the token is kept as it is, so the settings page can show it again.
 * Plain tokens left from while the setting was off are hashed all at once when it is switched on
 * (and by the migration that introduced hashing), and the next time their feed is fetched.
 *
 * @final not final for mocking in tests
 */
class CalendarFeedTokens
{
    /** MySQL's SHA2() gives the same lowercase hex as hash('sha256'), so hashed rows match findFeed() */
    public const HASH_PLAIN_TOKENS_SQL = 'UPDATE kimai2_planner_calendar_tokens SET token = SHA2(token, 256), hashed = 1 WHERE hashed = 0';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CalendarFeedSettings $settings
    ) {
    }

    public function hasFeed(User $user): bool
    {
        return $this->findByUser($user) !== null;
    }

    /**
     * The token, if it can still be shown: null when there is no feed or it is stored hashed.
     */
    public function getToken(User $user): ?string
    {
        $entity = $this->findByUser($user);

        return $entity === null || $entity->isHashed() ? null : $entity->getToken();
    }

    /**
     * Creates a new token, so a feed URL shared before stops working. Returns the token itself,
     * which is the only time it is known when it is stored hashed.
     */
    public function resetToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $hashed = $this->settings->hashTokens();
        $stored = $hashed ? self::hash($token) : $token;

        $entity = $this->findByUser($user);
        if ($entity === null) {
            $entity = new CalendarFeedToken($user, $stored, $hashed);
        } else {
            $entity->setToken($stored, $hashed);
        }

        $this->save($entity);

        return $token;
    }

    public function removeToken(User $user): void
    {
        $entity = $this->findByUser($user);
        if ($entity === null) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * The user's own choice on comments in their feed, null to follow the organisation.
     */
    public function getIncludeComments(User $user): ?bool
    {
        return $this->findByUser($user)?->getIncludeComments();
    }

    public function setIncludeComments(User $user, ?bool $includeComments): void
    {
        $entity = $this->findByUser($user);
        if ($entity === null || $entity->getIncludeComments() === $includeComments) {
            return;
        }

        $entity->setIncludeComments($includeComments);
        $this->save($entity);
    }

    /**
     * The feed a token opens, or null. A plain token found while hashing is on is hashed on the way.
     */
    public function findFeed(string $token): ?CalendarFeedToken
    {
        if (!self::isValidFormat($token)) {
            return null;
        }

        // hashed and plain tokens live side by side (the setting may have changed); the flag keeps them apart
        $entity = $this->repository()->findOneBy(['token' => self::hash($token), 'hashed' => true])
            ?? $this->repository()->findOneBy(['token' => $token, 'hashed' => false]);

        if ($entity !== null && !$entity->isHashed() && $this->settings->hashTokens()) {
            $entity->setToken(self::hash($token), true);
            $this->save($entity);
        }

        return $entity;
    }

    public function findUser(string $token): ?User
    {
        return $this->findFeed($token)?->getUser();
    }

    public static function isValidFormat(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function save(CalendarFeedToken $entity): void
    {
        // entities use explicit change tracking, an unpersisted change is never written
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function findByUser(User $user): ?CalendarFeedToken
    {
        if ($user->getId() === null) {
            return null;
        }

        return $this->repository()->findOneBy(['user' => $user]);
    }

    /**
     * @return ObjectRepository<CalendarFeedToken>
     */
    private function repository(): ObjectRepository
    {
        return $this->entityManager->getRepository(CalendarFeedToken::class);
    }
}
