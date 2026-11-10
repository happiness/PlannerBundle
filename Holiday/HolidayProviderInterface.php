<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Holiday;

use App\Entity\User;

/**
 * Answers which public holidays a user has off.
 *
 * The planner treats every returned day like a day without contract hours. The default
 * implementation is YasumiHolidayProvider; another plugin can replace it by decorating
 * this service, for example to read holidays from its own holiday groups.
 */
interface HolidayProviderInterface
{
    /**
     * @return array<string, PublicHoliday> the holidays between both days (inclusive), keyed by date as Y-m-d
     */
    public function getHolidays(User $user, \DateTimeInterface $begin, \DateTimeInterface $end): array;
}
