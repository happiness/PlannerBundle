<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The secret in a user's calendar feed URL.
 *
 * A table of its own rather than a user preference: preferences are versioned by audit plugins
 * (every token would stay readable in the audit log after renewing it) and writable by the user
 * through PATCH /api/users/{id}/preferences.
 */
#[ORM\Table(name: 'kimai2_planner_calendar_tokens')]
#[ORM\UniqueConstraint(name: 'UNIQ_PLANNER_CALENDAR_USER', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_PLANNER_CALENDAR_TOKEN', columns: ['token'])]
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CalendarFeedToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * The token itself, or its SHA-256 hash when $hashed is set.
     */
    #[ORM\Column(name: 'token', type: Types::STRING, length: 64, nullable: false)]
    private string $token;

    #[ORM\Column(name: 'hashed', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    private bool $hashed = false;

    /**
     * The user's own choice on comments in their feed, null to follow the organisation.
     */
    #[ORM\Column(name: 'include_comments', type: Types::BOOLEAN, nullable: true)]
    private ?bool $includeComments = null;

    public function __construct(User $user, string $token, bool $hashed = false)
    {
        $this->user = $user;
        $this->token = $token;
        $this->hashed = $hashed;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token, bool $hashed = false): void
    {
        $this->token = $token;
        $this->hashed = $hashed;
    }

    public function isHashed(): bool
    {
        return $this->hashed;
    }

    public function getIncludeComments(): ?bool
    {
        return $this->includeComments;
    }

    public function setIncludeComments(?bool $includeComments): void
    {
        $this->includeComments = $includeComments;
    }
}
