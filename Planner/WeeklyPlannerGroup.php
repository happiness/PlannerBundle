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

/**
 * All planned activities of one user for one customer/project within the displayed week.
 *
 * Rows of the same group are rendered on one line, consecutive days of the same activity are merged into one segment.
 */
final class WeeklyPlannerGroup
{
    /**
     * @var array<PlannedActivity>
     */
    private array $activities = [];

    public function __construct(
        private readonly string $key,
        private readonly ?Customer $customer,
        private readonly ?Project $project,
        private readonly ?string $legacyTitle
    ) {
    }

    public static function keyFor(PlannedActivity $activity): string
    {
        if ($activity->getCustomer() === null) {
            return 'legacy:' . ($activity->getTitle() ?? '');
        }

        $customer = $activity->getCustomer();
        $project = $activity->getProject();

        // unsaved entities have no id yet, the object id keeps them apart
        return 'c' . ($customer->getId() ?? 'o' . spl_object_id($customer))
            . ':p' . ($project === null ? 0 : ($project->getId() ?? 'o' . spl_object_id($project)));
    }

    public static function createFor(PlannedActivity $activity): self
    {
        return new self(self::keyFor($activity), $activity->getCustomer(), $activity->getProject(), $activity->getTitle());
    }

    public function getKey(): string
    {
        return $this->key;
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
        return $this->customer?->getName() ?? $this->legacyTitle ?? '';
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
     * Planned hours of this group within the given days only.
     *
     * @param array<WeeklyPlannerDay> $days
     */
    public function getPlannedHours(array $days): float
    {
        $total = 0.0;
        foreach ($days as $day) {
            foreach ($this->activities as $activity) {
                if ($activity->coversDate($day->getDate())) {
                    $total += $activity->getHoursPerDay();
                }
            }
        }

        return $total;
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
