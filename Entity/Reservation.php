<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Entity;

use App\Entity\ColorTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use KimaiPlugin\PlannerBundle\Repository\ReservationRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Reserved time: hours held in someone's planning for something that is not a customer or project, such as a daily
 * buffer or tech leading. Planned and counted like project work, but nobody can log time to it.
 *
 * Plannings reference it by ID, and the foreign key restricts deletion: one that is planned can only be archived.
 */
#[ORM\Table(name: 'kimai2_planner_reservations')]
#[ORM\UniqueConstraint(name: 'UNIQ_PLANNER_RESERVATION_NAME', columns: ['name'])]
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[UniqueEntity(fields: ['name'])]
#[Serializer\ExclusionPolicy('all')]
class Reservation
{
    use ColorTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 100, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $name = null;

    /**
     * Archived: no longer offered for new planning. Its plannings stay visible and keep counting until they end.
     */
    #[ORM\Column(name: 'archived', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private bool $archived = false;

    /**
     * The order of the reservation rows under each person, and in the dialog.
     */
    #[ORM\Column(name: 'position', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name === null ? null : trim($name);

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
