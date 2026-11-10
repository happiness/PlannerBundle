<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Reservation;

use App\Utils\Color;

/**
 * Time held in a schedule for something that is not a customer or project, e.g. a daily buffer or tech leading.
 * It is planned and counted like project work, but carries no customer or project, so no time can be logged to it.
 */
final class PlanningReservation
{
    /**
     * @param string $key stable identifier stored on the planning, never shown
     * @param string $name shown as the row label
     * @param string|null $color hex colour of the bars, one is derived from the name when empty
     * @param bool $archived no longer offered for new planning; its existing plannings are still shown and counted
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly ?string $color = null,
        public readonly bool $archived = false,
    ) {
        if ($key === '' || \strlen($key) > PlanningReservations::MAX_KEY_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('A reservation key must have 1 to %d characters, got "%s"', PlanningReservations::MAX_KEY_LENGTH, $key));
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColorSafe(): string
    {
        return $this->color ?? (new Color())->getRandom($this->name);
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }
}
