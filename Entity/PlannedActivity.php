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
use App\Utils\Color;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Table(name: 'kimai2_planned_activities')]
#[ORM\Index(columns: ['user_id'], name: 'IDX_PLANNED_ACTIVITY_USER')]
#[ORM\Index(columns: ['date_begin', 'date_end'], name: 'IDX_PLANNED_ACTIVITY_DATES')]
#[ORM\Index(columns: ['recurrence_group'], name: 'IDX_PLANNED_ACTIVITY_RECURRENCE')]
#[ORM\Index(columns: ['customer_id'], name: 'IDX_PLANNED_ACTIVITY_CUSTOMER')]
#[ORM\Index(columns: ['project_id'], name: 'IDX_PLANNED_ACTIVITY_PROJECT')]
#[ORM\Index(columns: ['reservation_id'], name: 'IDX_PLANNED_ACTIVITY_RESERVATION_ID')]
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

    /**
     * Nullable in the database because rows created before customers were introduced have none and time off has none;
     * the validation requires a customer for every other activity saved through the form or the API.
     */
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: true, onDelete: 'CASCADE')]
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

    /**
     * Only time off may leave it empty, which means the whole working day of the user's contract.
     */
    #[ORM\Column(name: 'hours_per_day', type: Types::FLOAT, nullable: true)]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(24.0)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?float $hoursPerDay = 8.0;

    /**
     * The user is not available: no customer or project, and the hours are taken off the day's capacity instead of being planned.
     */
    #[ORM\Column(name: 'time_off', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private bool $timeOff = false;

    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $comment = null;

    #[ORM\Column(name: 'recurrence_group', type: Types::STRING, length: 64, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $recurrenceGroup = null;

    /**
     * Work that is not confirmed yet (e.g. a deal that is not signed), shown faded and counted apart from confirmed capacity.
     */
    #[ORM\Column(name: 'tentative', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private bool $tentative = false;

    /**
     * Reserved time instead of a customer and project: held, planned and counted like project work, never loggable.
     * A reservation with planning can only be archived, the foreign key refuses to delete it.
     */
    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: true, onDelete: 'RESTRICT')]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?Reservation $reservation = null;

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

        return $this->reservation?->getName() ?? $this->title;
    }

    #[Assert\Callback]
    public function validateRequiredFields(ExecutionContextInterface $context): void
    {
        if ($this->timeOff) {
            return;
        }

        if ($this->isProjectWork() && $this->customer === null) {
            $context->buildViolation('This value should not be null.')
                ->atPath('customer')
                ->setTranslationDomain('validators')
                ->addViolation();
        }

        if ($this->hoursPerDay === null) {
            $context->buildViolation('This value should not be null.')
                ->atPath('hoursPerDay')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
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

    public function getHoursPerDay(): ?float
    {
        return $this->hoursPerDay;
    }

    public function setHoursPerDay(?float $hoursPerDay): self
    {
        $this->hoursPerDay = $hoursPerDay;

        return $this;
    }

    public function isTimeOff(): bool
    {
        return $this->timeOff;
    }

    /**
     * Time off belongs to no customer or project and is never tentative, switching it on drops them.
     */
    public function setTimeOff(bool $timeOff): self
    {
        $this->timeOff = $timeOff;

        if ($timeOff) {
            $this->clearProjectWork();
            $this->reservation = null;
        }

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    /**
     * Reserved time belongs to no customer or project and is never tentative or time off, setting it drops them. The
     * colour goes too: its bars take the reservation's colour.
     */
    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;

        if ($reservation !== null) {
            $this->clearProjectWork();
            $this->color = null;
            $this->timeOff = false;
        }

        return $this;
    }

    /**
     * Work for a customer or project, the planner's usual kind: neither time off nor reserved time. Only project work
     * has a customer, a project, a title or a tentative state.
     */
    public function isProjectWork(): bool
    {
        return !$this->timeOff && $this->reservation === null;
    }

    /**
     * What time off and reserved time have in common: no customer, project, title or tentative state.
     */
    private function clearProjectWork(): void
    {
        $this->customer = null;
        $this->project = null;
        $this->title = null;
        $this->tentative = false;
    }

    /**
     * Copies everything but the dates and the recurrence group to another activity, for splitting, repeating and
     * "all future" edits: one place that knows every field, so no copy forgets one.
     */
    public function copyPlanningTo(self $target): self
    {
        $target->user = $this->user;
        $target->customer = $this->customer;
        $target->project = $this->project;
        $target->title = $this->title;
        $target->reservation = $this->reservation;
        $target->timeOff = $this->timeOff;
        $target->hoursPerDay = $this->hoursPerDay;
        $target->color = $this->color;
        $target->comment = $this->comment;
        $target->tentative = $this->tentative;

        return $target;
    }

    /**
     * Reserved time takes the reservation's colour, everything else its own or one derived from the name.
     */
    public function getColorSafe(): string
    {
        return $this->reservation?->getColorSafe() ?? $this->getColor() ?? (new Color())->getRandom($this->getName());
    }

    public function isWholeDay(): bool
    {
        return $this->timeOff && $this->hoursPerDay === null;
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

    /**
     * Only project work can be tentative.
     */
    public function isTentative(): bool
    {
        return $this->tentative && $this->isProjectWork();
    }

    public function setTentative(bool $tentative): self
    {
        $this->tentative = $tentative && $this->isProjectWork();

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
            && $this->reservation === $next->getReservation()
            && $this->title === $next->getTitle()
            && $this->hoursPerDay === $next->getHoursPerDay()
            && $this->timeOff === $next->isTimeOff()
            && $this->tentative === $next->isTentative()
            && $this->getColor() === $next->getColor()
            && ($this->comment ?? '') === ($next->getComment() ?? '')
            && $this->recurrenceGroup === $next->getRecurrenceGroup();
    }

    public function getTotalHours(): float
    {
        return $this->getDaysCount() * ($this->hoursPerDay ?? 0.0);
    }
}
