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
 * The default: no reservations, so the planner looks and works as it always did.
 */
final class NoPlanningReservations implements PlanningReservationProviderInterface
{
    public function getReservations(): array
    {
        return [];
    }
}
