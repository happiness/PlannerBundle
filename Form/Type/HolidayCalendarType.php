<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Form\Type;

use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Picks a public holiday calendar. Empty means "no public holidays" in the system settings and
 * "the organisation's default" on a user, who can also opt out with HolidayCalendars::NONE.
 */
final class HolidayCalendarType extends AbstractType
{
    public function __construct(
        private readonly HolidayCalendars $calendars,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['user_override']);
        $resolver->setAllowedTypes('user_override', 'bool');

        $resolver->setDefaults([
            'user_override' => false,
            'required' => false,
            'choice_translation_domain' => false,
            'choices' => function (Options $options): array {
                $codes = array_keys($this->calendars->getCalendars($this->translator->getLocale()));

                return $options['user_override'] ? array_merge([HolidayCalendars::NONE], $codes) : $codes;
            },
            'choice_label' => fn (Options $options): callable => function (string $code): string {
                if ($code === HolidayCalendars::NONE) {
                    return $this->translator->trans('planner.holidays.none');
                }

                return $this->calendars->getCalendarName($code, $this->translator->getLocale());
            },
            'placeholder' => function (Options $options): string {
                return $options['user_override'] ? 'planner.holidays.organisation_default' : 'planner.holidays.none';
            },
            'translation_domain' => 'messages',
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
