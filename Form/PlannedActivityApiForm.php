<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Form;

use App\Form\Type\DatePickerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form used by the REST API: ISO dates (Y-m-d) instead of the localized date format, no CSRF token.
 */
final class PlannedActivityApiForm extends PlannedActivityEditForm
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        if ($builder->has('user')) {
            $builder->get('user')->setRequired(false);
        }

        foreach (['begin', 'end'] as $field) {
            $builder->add($field, DatePickerType::class, [
                'required' => true,
                'format' => 'yyyy-MM-dd',
                'documentation' => ['type' => 'string', 'format' => 'date', 'example' => '2026-10-19'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'csrf_protection' => false,
            'attr' => [],
        ]);
    }
}
