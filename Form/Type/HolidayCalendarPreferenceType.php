<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The calendar on a user's preferences: empty follows the organisation, or "no public holidays".
 *
 * A type of its own rather than an option, because core renders a preference the editor may not change
 * as a HiddenType with the same options, and HiddenType rejects options it does not know.
 */
final class HolidayCalendarPreferenceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('user_override', true);
    }

    public function getParent(): string
    {
        return HolidayCalendarType::class;
    }
}
