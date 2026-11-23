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
use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Entity\Reservation;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityEditForm;
use KimaiPlugin\PlannerBundle\Repository\ReservationRepository;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

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
        $this->assertTrue($form->has('tentative'));
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

    /**
     * Runs only the listeners this form adds, not the project reload of FormTrait.
     */
    private function dispatchOwnListener(string $eventName, PlannedActivity $activity, mixed $data): mixed
    {
        $builder = $this->factory->createBuilder(PlannedActivityEditForm::class, $activity, ['user' => new User()]);
        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn($activity);
        $event = new FormEvent($form, $data);

        foreach ($builder->getEventDispatcher()->getListeners($eventName) as $listener) {
            if ($listener instanceof \Closure && (new \ReflectionFunction($listener))->getClosureScopeClass()?->getName() === PlannedActivityEditForm::class) {
                $listener($event);
            }
        }

        return $event->getData();
    }

    public function testSwitchingToTimeOffWithoutHoursTakesTheWholeDay(): void
    {
        $work = (new PlannedActivity())->setHoursPerDay(6.0);

        $this->assertSame(['timeOff' => true, 'hoursPerDay' => ''], $this->dispatchOwnListener(FormEvents::PRE_SUBMIT, $work, ['timeOff' => true]));
        // explicit hours, no switch, switching off, or already time off: left alone
        $this->assertSame(['timeOff' => true, 'hoursPerDay' => '4'], $this->dispatchOwnListener(FormEvents::PRE_SUBMIT, $work, ['timeOff' => true, 'hoursPerDay' => '4']));
        $this->assertSame(['comment' => 'x'], $this->dispatchOwnListener(FormEvents::PRE_SUBMIT, $work, ['comment' => 'x']));
        $this->assertSame(['timeOff' => false], $this->dispatchOwnListener(FormEvents::PRE_SUBMIT, $work, ['timeOff' => false]));
        $this->assertSame(['timeOff' => true], $this->dispatchOwnListener(FormEvents::PRE_SUBMIT, (new PlannedActivity())->setTimeOff(true), ['timeOff' => true]));
    }

    public function testTimeOffNeverKeepsASubmittedCustomerProjectOrTentative(): void
    {
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setCustomer($customer);

        // what the form leaves behind when existing time off gets a customer: the unchanged flag is not written again
        $activity = (new PlannedActivity())->setTimeOff(true);
        $activity->setCustomer($customer)->setProject($project)->setTentative(true);

        $this->dispatchOwnListener(FormEvents::POST_SUBMIT, $activity, $activity);

        $this->assertTrue($activity->isTimeOff());
        $this->assertNull($activity->getCustomer());
        $this->assertNull($activity->getProject());
        $this->assertFalse($activity->isTentative());

        $work = (new PlannedActivity())->setCustomer($customer);
        $this->dispatchOwnListener(FormEvents::POST_SUBMIT, $work, $work);
        $this->assertSame($customer, $work->getCustomer());
    }

    public function testProjectWithoutCustomerBringsItsCustomer(): void
    {
        $customer = new Customer('Customer');
        $project = new Project();
        $project->setCustomer($customer);

        $activity = new PlannedActivity();
        $activity->setProject($project);

        PlannedActivityEditForm::fillCustomerFromProject(new FormEvent($this->createMock(FormInterface::class), $activity));

        $this->assertSame($customer, $activity->getCustomer());
    }

    public function testChosenCustomerIsKept(): void
    {
        $project = new Project();
        $project->setCustomer(new Customer('Project customer'));
        $chosen = new Customer('Chosen customer');

        $activity = new PlannedActivity();
        $activity->setCustomer($chosen);
        $activity->setProject($project);

        PlannedActivityEditForm::fillCustomerFromProject(new FormEvent($this->createMock(FormInterface::class), $activity));

        // a mismatch is left for validateProjectBelongsToCustomer() to report, not silently replaced
        $this->assertSame($chosen, $activity->getCustomer());
    }

    public function testNoProjectLeavesCustomerEmpty(): void
    {
        $activity = new PlannedActivity();

        PlannedActivityEditForm::fillCustomerFromProject(new FormEvent($this->createMock(FormInterface::class), $activity));

        $this->assertNull($activity->getCustomer());
    }

    private function createFormFactoryWith(ReservationRepository $reservations, ?AuthorizationCheckerInterface $authorizationChecker = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->addType(new PlannedActivityEditForm($reservations, $authorizationChecker))
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

    public function testReservationIsOfferedOnlyWhenThereAreAny(): void
    {
        $this->assertFalse($this->factory->createBuilder(PlannedActivityEditForm::class, new PlannedActivity(), ['user' => new User()])->has('reservation'));

        $buffer = (new Reservation())->setName('Agency buffer');
        $old = (new Reservation())->setName('Old')->setArchived(true);
        $repository = $this->createStub(ReservationRepository::class);
        $repository->method('findSelectable')->willReturn([$buffer]);

        $builder = $this->createFormFactoryWith($repository)->createBuilder(PlannedActivityEditForm::class, new PlannedActivity(), ['user' => new User()]);
        $this->assertTrue($builder->has('reservation'));
        $this->assertSame([$buffer], $builder->get('reservation')->getOption('choices'));

        // an archived reservation a planning already has stays choosable, so it can be edited without moving it
        $builder = $this->createFormFactoryWith($repository)->createBuilder(PlannedActivityEditForm::class, (new PlannedActivity())->setReservation($old), ['user' => new User()]);
        $this->assertSame([$buffer, $old], $builder->get('reservation')->getOption('choices'));
    }

    public function testReservationIsOnlyOfferedWithThePermission(): void
    {
        $repository = $this->createStub(ReservationRepository::class);
        $repository->method('findSelectable')->willReturn([(new Reservation())->setName('Agency buffer')]);

        $denied = $this->createStub(AuthorizationCheckerInterface::class);
        $denied->method('isGranted')->willReturn(false);
        $builder = $this->createFormFactoryWith($repository, $denied)->createBuilder(PlannedActivityEditForm::class, new PlannedActivity(), ['user' => new User()]);
        $this->assertFalse($builder->has('reservation'));

        $granted = $this->createMock(AuthorizationCheckerInterface::class);
        $granted->method('isGranted')->with('plan_reservations_planner')->willReturn(true);
        $builder = $this->createFormFactoryWith($repository, $granted)->createBuilder(PlannedActivityEditForm::class, new PlannedActivity(), ['user' => new User()]);
        $this->assertTrue($builder->has('reservation'));
    }

    public function testReservationNeverKeepsASubmittedCustomer(): void
    {
        $buffer = (new Reservation())->setName('Agency buffer');

        // what the form leaves behind when an existing reservation gets a customer: the unchanged reservation is not written again
        $activity = (new PlannedActivity())->setReservation($buffer);
        $activity->setCustomer(new Customer('ACME'));

        $this->dispatchOwnListener(FormEvents::POST_SUBMIT, $activity, $activity);

        $this->assertSame($buffer, $activity->getReservation());
        $this->assertNull($activity->getCustomer());
    }
}
