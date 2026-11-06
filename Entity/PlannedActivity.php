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
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Validator\Constraints\NoOverlappingPlanning;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Table(name: 'kimai2_planned_activities')]
#[ORM\Index(columns: ['user_id'], name: 'IDX_PLANNED_ACTIVITY_USER')]
#[ORM\Index(columns: ['date_begin', 'date_end'], name: 'IDX_PLANNED_ACTIVITY_DATES')]
#[ORM\Index(columns: ['recurrence_group'], name: 'IDX_PLANNED_ACTIVITY_RECURRENCE')]
#[ORM\Index(columns: ['customer_id'], name: 'IDX_PLANNED_ACTIVITY_CUSTOMER')]
#[ORM\Index(columns: ['project_id'], name: 'IDX_PLANNED_ACTIVITY_PROJECT')]
#[ORM\Entity(repositoryClass: PlannedActivityRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[NoOverlappingPlanning]
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

    /**
     * Nullable in the database only because rows created before customers were introduced have none;
     * the validation requires a customer whenever an activity is saved through the form or the API.
     */
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?Customer $customer = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', nullable: true, onDelete: 'CASCADE')]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?Project $project = null;

    /**
     * Legacy free-text title, only kept for rows created before customers were introduced.
     */
    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
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

    #[ORM\Column(name: 'recurrence_group', type: Types::STRING, length: 64, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $recurrenceGroup = null;

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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @deprecated legacy free-text title, use getName()
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('name')]
    #[Serializer\Groups(['Default'])]
    public function getName(): ?string
    {
        if ($this->project !== null) {
            return $this->project->getName();
        }

        if ($this->customer !== null) {
            return $this->customer->getName();
        }

        return $this->title;
    }

    #[Assert\Callback]
    public function validateProjectBelongsToCustomer(ExecutionContextInterface $context): void
    {
        if ($this->project === null || $this->customer === null) {
            return;
        }

        if ($this->project->getCustomer() !== $this->customer) {
            $context->buildViolation('The project does not belong to the selected customer.')
                ->atPath('project')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
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

    public function hasComment(): bool
    {
        return $this->comment !== null && $this->comment !== '';
    }

    public function getRecurrenceGroup(): ?string
    {
        return $this->recurrenceGroup;
    }

    public function setRecurrenceGroup(?string $recurrenceGroup): self
    {
        $this->recurrenceGroup = $recurrenceGroup;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->recurrenceGroup !== null && $this->recurrenceGroup !== '';
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

    /**
     * Whether $next starts the day after this one ends and is the same planning in everything but its dates,
     * so the two can be joined into one again (as after a split).
     */
    public function isJoinableWith(PlannedActivity $next): bool
    {
        if ($next === $this || $this->end === null || $next->getBegin() === null) {
            return false;
        }

        if ((clone $this->end)->modify('+1 day')->format('Y-m-d') !== $next->getBegin()->format('Y-m-d')) {
            return false;
        }

        // weekly instances of a seven-day series also sit side by side; parts of one instance span a week at most
        if ($this->isRecurring() && $this->begin !== null && $next->getEnd() !== null
            && $this->begin->diff($next->getEnd())->days >= 7) {
            return false;
        }

        return $this->user === $next->getUser()
            && $this->customer === $next->getCustomer()
            && $this->project === $next->getProject()
            && $this->title === $next->getTitle()
            && $this->hoursPerDay === $next->getHoursPerDay()
            && $this->getColor() === $next->getColor()
            && ($this->comment ?? '') === ($next->getComment() ?? '')
            && $this->recurrenceGroup === $next->getRecurrenceGroup();
    }

    public function getTotalHours(): float
    {
        return $this->getDaysCount() * $this->hoursPerDay;
    }
}
