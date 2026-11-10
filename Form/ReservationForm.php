<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Form;

use App\Form\Type\ColorPickerType;
use KimaiPlugin\PlannerBundle\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Reservation>
 */
final class ReservationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
                'help' => 'planner.reservation.name_help',
                'attr' => ['autofocus' => 'autofocus', 'maxlength' => 100],
            ])
            ->add('color', ColorPickerType::class, [
                'label' => 'color',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'planner_reservation',
            'attr' => [
                'data-form-event' => 'kimai.plannerReservationUpdate',
            ],
        ]);
    }
}
