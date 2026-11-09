<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Reservation;

/**
 * The reservations of the provider, asked once per request, with the lookups the planner needs.
 */
final class PlanningReservations
{
    public const MAX_KEY_LENGTH = 64;

    /**
     * @var array<string, PlanningReservation>|null
     */
    private ?array $reservations = null;

    public function __construct(private readonly PlanningReservationProviderInterface $provider)
    {
    }

    /**
     * @return array<string, PlanningReservation> by key, in display order
     */
    public function all(): array
    {
        if ($this->reservations === null) {
            $this->reservations = [];
            foreach ($this->provider->getReservations() as $reservation) {
                $this->reservations[$reservation->key] ??= $reservation;
            }
        }

        return $this->reservations;
    }

    public function get(?string $key): ?PlanningReservation
    {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    /**
     * The ones that can be chosen for new planning.
     *
     * @return array<string, PlanningReservation>
     */
    public function getSelectable(): array
    {
        return array_filter($this->all(), static fn (PlanningReservation $reservation): bool => !$reservation->hidden);
    }

    public function isSelectable(string $key): bool
    {
        $reservation = $this->get($key);

        return $reservation !== null && !$reservation->hidden;
    }

    public function isHidden(?string $key): bool
    {
        return $this->get($key)?->hidden ?? false;
    }

    /**
     * @return array<string>
     */
    public function getHiddenKeys(): array
    {
        // PHP turns array keys like "7" into integers: hand them back as the strings they are
        return array_map('strval', array_keys(array_filter($this->all(), static fn (PlanningReservation $reservation): bool => $reservation->hidden)));
    }

    /**
     * Where the reservation's row goes among the others; unknown keys come last.
     */
    public function getPosition(string $key): int
    {
        $position = array_search($key, array_map('strval', array_keys($this->all())), true);

        return $position === false ? \PHP_INT_MAX : $position;
    }
}
