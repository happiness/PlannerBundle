<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Form;

use App\Entity\Customer;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityEditForm;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservation;
use KimaiPlugin\PlannerBundle\Reservation\PlanningReservations;
use KimaiPlugin\PlannerBundle\Tests\Reservation\PlanningReservationsTest;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlannedActivityEditFormTest extends TypeTestCase
{
    public function testFormFieldsWithoutUser(): void
    {
        $user = new User();
        $activity = new PlannedActivity();

        $form = $this->factory->createBuilder(PlannedActivityEditForm::class, $activity, [
            'user' => $user,
            'include_user' => false,
        ]);

        $this->assertFalse($form->has('user'));
        $this->assertFalse($form->has('recurrentWeeks'));
        $this->assertFalse($form->has('title'));
        $this->assertTrue($form->has('customer'));
        $this->assertTrue($form->has('project'));
        $this->assertTrue($form->has('begin'));
        $this->assertTrue($form->has('end'));
        $this->assertTrue($form->has('hoursPerDay'));
        $this->assertTrue($form->has('color'));
        $this->assertTrue($form->has('comment'));
    }

    public function testFormFieldsWithUser(): void
    {
        $user = new User();
        $activity = new PlannedActivity();

        $form = $this->factory->createBuilder(PlannedActivityEditForm::class, $activity, [
            'user' => $user,
            'include_user' => true,
        ]);

        $this->assertTrue($form->has('user'));
        $this->assertFalse($form->has('recurrentWeeks'));
        $this->assertFalse($form->has('title'));
        $this->assertTrue($form->has('customer'));
        $this->assertTrue($form->has('project'));
        $this->assertTrue($form->has('begin'));
        $this->assertTrue($form->has('end'));
        $this->assertTrue($form->has('hoursPerDay'));
        $this->assertTrue($form->has('color'));
        $this->assertTrue($form->has('comment'));
    }

    public function testFormFieldsWithRecurrence(): void
    {
        $user = new User();
        $activity = new PlannedActivity();

        $form = $this->factory->createBuilder(PlannedActivityEditForm::class, $activity, [
            'user' => $user,
            'include_recurrence' => true,
        ]);

        $this->assertFalse($form->has('user'));
        $this->assertTrue($form->has('recurrentWeeks'));
        $this->assertFalse($form->has('edit_mode'));
        $this->assertFalse($form->has('title'));
        $this->assertTrue($form->has('customer'));
        $this->assertTrue($form->has('project'));
        $this->assertTrue($form->has('begin'));
        $this->assertTrue($form->has('end'));
        $this->assertTrue($form->has('hoursPerDay'));
        $this->assertTrue($form->has('color'));
        $this->assertTrue($form->has('comment'));
    }

    public function testFormFieldsWithRecurrenceMode(): void
    {
        $user = new User();
        $activity = new PlannedActivity();

        $form = $this->factory->createBuilder(PlannedActivityEditForm::class, $activity, [
            'user' => $user,
            'include_recurrence_mode' => true,
        ]);

        $this->assertFalse($form->has('user'));
        $this->assertFalse($form->has('recurrentWeeks'));
        $this->assertTrue($form->has('edit_mode'));
        $this->assertFalse($form->has('title'));
        $this->assertTrue($form->has('customer'));
        $this->assertTrue($form->has('project'));
        $this->assertTrue($form->has('begin'));
        $this->assertTrue($form->has('end'));
        $this->assertTrue($form->has('hoursPerDay'));
        $this->assertTrue($form->has('color'));
        $this->assertTrue($form->has('comment'));
    }

    public function testReservationIsOfferedOnlyWhenThereAreAny(): void
    {
        $form = $this->factory->createBuilder(PlannedActivityEditForm::class, new PlannedActivity(), ['user' => new User()]);
        $this->assertFalse($form->has('reservation'));

        $reservations = PlanningReservationsTest::create([new PlanningReservation('buffer', 'Agency buffer'), new PlanningReservation('old', 'Old', null, true)]);
        $builder = $this->createFormFactoryWith($reservations)->createBuilder(PlannedActivityEditForm::class, new PlannedActivity(), ['user' => new User()]);
        $this->assertTrue($builder->has('reservation'));
        $this->assertSame(['Agency buffer' => 'buffer'], $builder->get('reservation')->getOption('choices'));

        // the reservation a planning already has stays choosable, also when hidden or unknown
        $builder = $this->createFormFactoryWith($reservations)->createBuilder(PlannedActivityEditForm::class, (new PlannedActivity())->setReservation('gone'), ['user' => new User()]);
        $this->assertSame(['Agency buffer' => 'buffer', 'gone' => 'gone'], $builder->get('reservation')->getOption('choices'));

        // without any reservations, a planning that has one still shows it
        $builder = $this->createFormFactoryWith(PlanningReservationsTest::create([]))->createBuilder(PlannedActivityEditForm::class, (new PlannedActivity())->setReservation('gone'), ['user' => new User()]);
        $this->assertSame(['gone' => 'gone'], $builder->get('reservation')->getOption('choices'));
    }

    public function testReservationNeverKeepsASubmittedCustomer(): void
    {
        $customer = new Customer('ACME');

        // what the form leaves behind when an existing reservation gets a customer: the unchanged key is not written again
        $activity = (new PlannedActivity())->setReservation('buffer');
        $activity->setCustomer($customer);

        $builder = $this->factory->createBuilder(PlannedActivityEditForm::class, $activity, ['user' => new User()]);
        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn($activity);
        foreach ($builder->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT) as $listener) {
            if ($listener instanceof \Closure && (new \ReflectionFunction($listener))->getClosureScopeClass()?->getName() === PlannedActivityEditForm::class) {
                $listener(new FormEvent($form, $activity));
            }
        }

        $this->assertSame('buffer', $activity->getReservation());
        $this->assertNull($activity->getCustomer());
    }

    private function createFormFactoryWith(PlanningReservations $reservations): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->addType(new PlannedActivityEditForm($reservations))
            // the API documentation option, which NelmioApiDocBundle adds in the application
            ->addTypeExtension(new class() extends AbstractTypeExtension {
                public function configureOptions(OptionsResolver $resolver): void
                {
                    $resolver->setDefined('documentation');
                }

                public static function getExtendedTypes(): iterable
                {
                    return [FormType::class];
                }
            })
            ->getFormFactory();
    }
}
