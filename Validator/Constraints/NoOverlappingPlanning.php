<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * A user cannot have the same customer/project planned twice on one day.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoOverlappingPlanning extends Constraint
{
    public const OVERLAPPING = 'planner-overlapping-01';

    protected const ERROR_NAMES = [
        self::OVERLAPPING => 'OVERLAPPING',
    ];

    public string $message = 'This project is already planned for this user on one of these days.';

    /**
     * Not a violation of this constraint: the controllers report it for repeated and following weeks of a series.
     */
    public const SERIES_MESSAGE = 'This project is already planned for this user in one of the following weeks.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
