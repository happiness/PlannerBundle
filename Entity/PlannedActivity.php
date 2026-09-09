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
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'kimai2_planned_activities')]
#[ORM\Index(columns: ['user_id'], name: 'IDX_PLANNED_ACTIVITY_USER')]
#[ORM\Index(columns: ['date_begin', 'date_end'], name: 'IDX_PLANNED_ACTIVITY_DATES')]
#[ORM\Entity(repositoryClass: PlannedActivityRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[Serializer\ExclusionPolicy('all')]
class PlannedActivity
{
    use ColorTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?User $user = null;

    #[ORM\Column(name: 'date_begin', type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?\DateTime $begin = null;

    #[ORM\Column(name: 'date_end', type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(propertyPath: 'begin')]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?\DateTime $end = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $title = null;

    #[ORM\Column(name: 'hours_per_day', type: Types::FLOAT, nullable: false)]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(24.0)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private float $hoursPerDay = 8.0;

    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $comment = null;

    public function __construct()
    {
        $this->begin = new \DateTime('today');
        $this->end = new \DateTime('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getBegin(): ?\DateTime
    {
        return $this->begin;
    }

    public function setBegin(?\DateTime $begin): self
    {
        $this->begin = $begin;

        return $this;
    }

    public function getEnd(): ?\DateTime
    {
        return $this->end;
    }

    public function setEnd(?\DateTime $end): self
    {
        $this->end = $end;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->title;
    }

    public function getHoursPerDay(): float
    {
        return $this->hoursPerDay;
    }

    public function setHoursPerDay(float $hoursPerDay): self
    {
        $this->hoursPerDay = $hoursPerDay;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function coversDate(\DateTimeInterface $date): bool
    {
        if ($this->begin === null || $this->end === null) {
            return false;
        }

        $check = $date->format('Y-m-d');
        $start = $this->begin->format('Y-m-d');
        $finish = $this->end->format('Y-m-d');

        return $check >= $start && $check <= $finish;
    }

    public function getDaysCount(): int
    {
        if ($this->begin === null || $this->end === null) {
            return 0;
        }

        $interval = $this->begin->diff($this->end);

        return (int) $interval->days + 1;
    }

    public function getTotalHours(): float
    {
        return $this->getDaysCount() * $this->hoursPerDay;
    }
}
