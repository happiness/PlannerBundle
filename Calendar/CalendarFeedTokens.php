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
 * Stored in plain text so the settings page can show the URL again; anyone who can read the
 * database can read far more than a user's planning anyway.
 *
 * @final not final for mocking in tests
 */
class CalendarFeedTokens
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getToken(User $user): ?string
    {
        return $this->findByUser($user)?->getToken();
    }

    /**
     * Creates a new token, so a feed URL shared before stops working.
     */
    public function resetToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        $entity = $this->findByUser($user);
        if ($entity === null) {
            $entity = new CalendarFeedToken($user, $token);
        } else {
            $entity->setToken($token);
        }

        // entities use explicit change tracking, an unpersisted change is never written
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

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

    public function findUser(string $token): ?User
    {
        if (!self::isValidFormat($token)) {
            return null;
        }

        return $this->repository()->findOneBy(['token' => $token])?->getUser();
    }

    public static function isValidFormat(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1;
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
