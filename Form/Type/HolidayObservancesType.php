<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Form\Type;

use App\Configuration\SystemConfiguration;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ticks the "observance" days that count as days off, e.g. Christmas Eve, stored as a comma separated
 * list of Yasumi keys because system settings hold a single value.
 */
final class HolidayObservancesType extends AbstractType
{
    public function __construct(
        private readonly SystemConfiguration $configuration,
        private readonly HolidayCalendars $calendars,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn ($stored): array => HolidayCalendars::parseObservances($stored),
            static function ($keys): ?string {
                $keys = \is_array($keys) ? array_filter($keys, '\is_string') : [];

                // null deletes the setting
                return $keys !== [] ? implode(',', $keys) : null;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'multiple' => true,
            'required' => false,
            'choice_translation_domain' => false,
            // keys as values with a label callback, as two keys can share a label ("Carnival (NL)")
            'choices' => fn (Options $options): array => array_keys($this->getObservances()),
            'choice_label' => fn (Options $options): callable => fn (string $key): string => $this->getObservances()[$key] ?? $key,
        ]);
    }

    /**
     * The choices are this year's and next year's observances. Stored keys outside them (a day
     * Yasumi only has in some years) are kept as choices, else saving the section would drop them.
     *
     * @return array<string, string>
     */
    private function getObservances(): array
    {
        $observances = $this->calendars->getObservances($this->translator->getLocale());
        foreach (HolidayCalendars::parseObservances($this->configuration->find(HolidayCalendars::SETTING_OBSERVANCES)) as $key) {
            $observances[$key] ??= $key;
        }

        return $observances;
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
