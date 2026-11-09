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
 * Supplies the reservations the planner offers next to customers and projects. Another bundle provides them by
 * decorating this service (see README), the planner itself has none.
 */
interface PlanningReservationProviderInterface
{
    /**
     * Every reservation the planner should know, in the order their rows are shown. Return retired ones as hidden
     * rather than leaving them out: plannings with a key nobody returns are still shown and counted, under that key.
     *
     * @return array<PlanningReservation>
     */
    public function getReservations(): array;
}
