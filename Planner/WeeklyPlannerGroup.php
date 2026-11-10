<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Planner;

use App\Entity\Customer;
use App\Entity\Project;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservation;

/**
 * All planned activities of one user for one customer/project, or one reservation, within the displayed week.
 *
 * Rows of the same group are rendered on one line, consecutive days of the same activity are merged into one segment.
 */
final class WeeklyPlannerGroup
{
    /**
     * @var array<PlannedActivity>
     */
    private array $activities = [];

    public const TIME_OFF_KEY = 'off';
    public const RESERVATION_PREFIX = 'reservation:';

    /**
     * @param string|null $reservationKey also set for a key the provider does not know, then $reservation is null
     */
    public function __construct(
        private readonly string $key,
        private readonly ?Customer $customer,
        private readonly ?Project $project,
        private readonly ?string $legacyTitle,
        private readonly ?string $reservationKey = null,
        private readonly ?PlanningReservation $reservation = null
    ) {
    }

    public static function keyFor(PlannedActivity $activity): string
    {
        if ($activity->isTimeOff()) {
            return self::TIME_OFF_KEY;
        }

        if ($activity->getReservation() !== null) {
            return self::RESERVATION_PREFIX . $activity->getReservation();
        }

        if ($activity->getCustomer() === null) {
            return 'legacy:' . ($activity->getTitle() ?? '');
        }

        $customer = $activity->getCustomer();
        $project = $activity->getProject();

        // unsaved entities have no id yet, the object id keeps them apart
        return 'c' . ($customer->getId() ?? 'o' . spl_object_id($customer))
            . ':p' . ($project === null ? 0 : ($project->getId() ?? 'o' . spl_object_id($project)));
    }

    public static function createFor(PlannedActivity $activity, ?PlanningReservation $reservation = null): self
    {
        return new self(self::keyFor($activity), $activity->getCustomer(), $activity->getProject(), $activity->getTitle(), $activity->getReservation(), $reservation);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function isTimeOff(): bool
    {
        return $this->key === self::TIME_OFF_KEY;
    }

    public function isReservation(): bool
    {
        return $this->reservationKey !== null;
    }

    public function getReservationKey(): ?string
    {
        return $this->reservationKey;
    }

    /**
     * Null for a key the provider does not know (any more), which is shown under the key itself.
     */
    public function getReservation(): ?PlanningReservation
    {
        return $this->reservation;
    }

    public function getReservationName(): ?string
    {
        return $this->reservation->name ?? $this->reservationKey;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function getCustomerName(): string
    {
        return $this->customer?->getName() ?? $this->legacyTitle ?? $this->reservationKey ?? '';
    }

    public function getProjectName(): ?string
    {
        return $this->project?->getName();
    }

    public function addActivity(PlannedActivity $activity): void
    {
        if (!\in_array($activity, $this->activities, true)) {
            $this->activities[] = $activity;
        }
    }

    /**
     * @return array<PlannedActivity>
     */
    public function getActivities(): array
    {
        return $this->activities;
    }

    /**
     * Confirmed planned hours of this group within the given days only, for time off the hours taken off.
     *
     * @param array<WeeklyPlannerDay> $days
     */
    public function getPlannedHours(array $days): float
    {
        return $this->sumHours($days, false);
    }

    /**
     * Tentative hours of this group within the given days only.
     *
     * @param array<WeeklyPlannerDay> $days
     */
    public function getTentativeHours(array $days): float
    {
        return $this->sumHours($days, true);
    }

    public function hasTentative(): bool
    {
        foreach ($this->activities as $activity) {
            if ($activity->isTentative()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<WeeklyPlannerDay> $days
     */
    private function sumHours(array $days, bool $tentative): float
    {
        $total = 0.0;
        foreach ($days as $day) {
            foreach ($this->activities as $activity) {
                if ($activity->isTentative() === $tentative && $activity->coversDate($day->getDate())) {
                    $hours = $day->getHoursOf($activity);
                    // like the day itself, time off never takes more than the contract hours
                    $total += $activity->isTimeOff() ? min($hours, $day->getExpectedHours()) : $hours;
                }
            }
        }

        return $total;
    }

    /**
     * The activity of this group that can be joined to the end of $activity, if one is shown.
     */
    public function getJoinableAfter(PlannedActivity $activity): ?PlannedActivity
    {
        foreach ($this->activities as $next) {
            if ($activity->isJoinableWith($next)) {
                return $next;
            }
        }

        return null;
    }

    /**
     * Splits the days into render segments: a single activity covering several consecutive days becomes one
     * segment with a span, empty days and days with more than one activity are one segment each.
     *
     * @param array<WeeklyPlannerDay> $days in display order
     * @return array<int, array{date: \DateTimeImmutable, span: int, activities: array<PlannedActivity>}>
     */
    public function getSegments(array $days): array
    {
        $segments = [];

        foreach (array_values($days) as $day) {
            $date = $day->getDate();
            $covering = array_values(array_filter(
                $this->activities,
                static fn (PlannedActivity $activity): bool => $activity->coversDate($date)
            ));

            $last = $segments === [] ? null : $segments[\count($segments) - 1];
            if ($last !== null
                && \count($covering) === 1
                && \count($last['activities']) === 1
                && $last['activities'][0] === $covering[0]
            ) {
                ++$segments[\count($segments) - 1]['span'];

                continue;
            }

            $segments[] = ['date' => $date, 'span' => 1, 'activities' => $covering];
        }

        return $segments;
    }
}
