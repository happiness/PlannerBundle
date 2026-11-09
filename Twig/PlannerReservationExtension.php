<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Twig;

use KimaiPlugin\PlannerBundle\Reservation\PlanningReservation;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservations;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PlannerReservationExtension extends AbstractExtension
{
    public function __construct(private readonly PlanningReservations $reservations)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('planner_reservation', $this->getReservation(...)),
            new TwigFunction('planner_reservations', $this->reservations->getSelectable(...)),
        ];
    }

    public function getReservation(?string $key): ?PlanningReservation
    {
        return $this->reservations->get($key);
    }
}
