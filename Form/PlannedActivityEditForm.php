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
use App\Form\FormTrait;
use App\Form\Type\ColorPickerType;
use App\Form\Type\CustomerType;
use App\Form\Type\DatePickerType;
use App\Form\Type\UserType;
use App\Form\Type\YesNoType;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Entity\Reservation;
use KimaiPlugin\PlannerBundle\Repository\ReservationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

/**
 * @extends AbstractType<PlannedActivity>
 */
class PlannedActivityEditForm extends AbstractType
{
    use FormTrait;

    public function __construct(
        private readonly ?ReservationRepository $reservations = null,
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

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

        /** @var PlannedActivity|null $activity */
        $activity = $builder->getData();

        $builder->add('customer', CustomerType::class, [
            'label' => 'customer',
            'required' => $activity === null || $activity->isProjectWork(),
            'placeholder' => '',
            'query_builder_for_user' => true,
            'customers' => $activity?->getCustomer(),
            'project_enabled' => true,
            'attr' => [
                'autofocus' => 'autofocus',
            ],
        ]);

        $this->addProject($builder, $activity?->getId() === null, $activity?->getProject(), $activity?->getCustomer(), [
            'label' => 'project',
            'required' => false,
            'activity_enabled' => false,
        ]);

        // after customer and project: choosing a reservation drops them while the data is mapped
        $reservations = $this->canPlanReservations() ? $this->getReservationChoices($activity?->getReservation()) : [];
        if ($reservations !== []) {
            $builder->add('reservation', ChoiceType::class, [
                'label' => 'planner.reservation',
                'help' => 'planner.reservation_help',
                'required' => false,
                'placeholder' => '',
                'choices' => $reservations,
                'choice_value' => static fn (?Reservation $reservation): string => (string) $reservation?->getId(),
                'choice_label' => static fn (Reservation $reservation): string => (string) $reservation->getName(),
                'choice_translation_domain' => false,
                'documentation' => [
                    'type' => 'integer',
                    'description' => 'Reservation ID: reserved time instead of customer and project (default: none)',
                ],
            ]);
        }

        // after customer, project and reservation: switching time off on drops them while the data is mapped
        $builder->add('timeOff', YesNoType::class, [
            'label' => 'planner.time_off',
            'help' => 'planner.time_off_help',
            'required' => false,
        ]);

        $builder
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
                // time off may leave it empty for the whole day, the entity validates everything else
                'required' => !$activity?->isTimeOff(),
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
            ])
            ->add('tentative', YesNoType::class, [
                'label' => 'planner.tentative',
                'help' => 'planner.tentative_help',
                'documentation' => [
                    'description' => 'If true, the work is not confirmed yet and counts apart from the confirmed capacity (default: false)',
                ],
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

        // switching to time off without sending hours (the API) takes the whole day, like the dialog does
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            $activity = $event->getForm()->getData();
            if (!\is_array($data) || !\array_key_exists('timeOff', $data) || \array_key_exists('hoursPerDay', $data)) {
                return;
            }
            if (\in_array($data['timeOff'], [null, false, '', '0', 0, 'false'], true)) {
                return;
            }
            if ($activity instanceof PlannedActivity && $activity->isTimeOff()) {
                return;
            }
            $data['hoursPerDay'] = '';
            $event->setData($data);
        });

        // the flag and the reservation are only written when they change, so a customer or project submitted for
        // existing time off or reserved time would stick: drop them before the validation listener (priority 0) runs
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $activity = $event->getData();
            if ($activity instanceof PlannedActivity && $activity->isTimeOff()) {
                $activity->setTimeOff(true);
            }
            if ($activity instanceof PlannedActivity && $activity->getReservation() !== null) {
                $activity->setReservation($activity->getReservation());
            }
        }, 10);

        $builder->addEventListener(FormEvents::SUBMIT, [self::class, 'fillCustomerFromProject']);
    }

    /**
     * A project belongs to exactly one customer, so a project chosen without a customer brings its own. Runs after the
     * fields are mapped and before validation, which still requires a customer when no project is given either.
     */
    public static function fillCustomerFromProject(FormEvent $event): void
    {
        $activity = $event->getData();
        if (!$activity instanceof PlannedActivity || $activity->getCustomer() !== null) {
            return;
        }

        $customer = $activity->getProject()?->getCustomer();
        if ($customer !== null) {
            $activity->setCustomer($customer);
        }
    }

    private function canPlanReservations(): bool
    {
        return $this->authorizationChecker === null || $this->authorizationChecker->isGranted('plan_reservations_planner');
    }

    /**
     * The reservations that may be chosen, plus the one already set, also when it is archived: the planning stays
     * editable without being moved to something else.
     *
     * @return array<Reservation>
     */
    private function getReservationChoices(?Reservation $current): array
    {
        $choices = $this->reservations?->findSelectable() ?? [];
        if ($current !== null && !\in_array($current, $choices, true)) {
            $choices[] = $current;
        }

        return $choices;
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
