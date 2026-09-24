<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Form;

use App\Entity\User;
use App\Form\Type\ColorPickerType;
use App\Form\Type\DatePickerType;
use App\Form\Type\UserType;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

/**
 * @extends AbstractType<PlannedActivity>
 */
class PlannedActivityEditForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $options['user'];
        $includeUser = $options['include_user'];
        $includeRecurrence = $options['include_recurrence'];
        $includeRecurrenceMode = $options['include_recurrence_mode'];

        if ($includeUser) {
            $builder->add('user', UserType::class, [
                'label' => 'label.user',
                'required' => true,
                'width' => false,
            ]);
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => true,
                'attr' => [
                    'autofocus' => 'autofocus',
                ],
            ])
            ->add('begin', DatePickerType::class, [
                'label' => 'label.begin',
                'required' => true,
            ])
            ->add('end', DatePickerType::class, [
                'label' => 'label.end',
                'required' => true,
            ])
            ->add('hoursPerDay', NumberType::class, [
                'label' => 'planner.hours_per_day',
                'required' => true,
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'step' => '0.25',
                    'min' => '0.25',
                    'max' => '24',
                ],
            ])
            ->add('color', ColorPickerType::class, [
                'label' => 'label.color',
                'required' => false,
                'empty_data' => null,
            ]);

        if ($includeRecurrence) {
            $builder->add('recurrentWeeks', IntegerType::class, [
                'label' => 'planner.recurrent_weeks',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'min' => '0',
                    'max' => '52',
                    'step' => '1',
                    'placeholder' => '0',
                ],
                'constraints' => [
                    new GreaterThanOrEqual(0),
                    new LessThanOrEqual(104),
                ],
            ]);
        }

        if ($includeRecurrenceMode) {
            $builder->add('edit_mode', ChoiceType::class, [
                'label' => 'planner.edit_recurring_prompt',
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'planner.edit_single' => 'single',
                    'planner.edit_future' => 'future',
                ],
                'data' => 'single',
            ]);
        }

        $builder->add('comment', TextareaType::class, [
            'label' => 'label.comment',
            'required' => false,
            'attr' => [
                'rows' => 3,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlannedActivity::class,
            'csrf_protection' => true,
            'csrf_field_name' => 'token',
            'csrf_token_id' => 'planner_edit',
            'include_user' => false,
            'include_recurrence' => false,
            'include_recurrence_mode' => false,
            'attr' => [
                'data-form-event' => 'kimai.plannerActivityUpdate',
            ],
        ]);

        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', User::class);
        $resolver->setAllowedTypes('include_user', 'bool');
        $resolver->setAllowedTypes('include_recurrence', 'bool');
        $resolver->setAllowedTypes('include_recurrence_mode', 'bool');
    }
}
