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
use KimaiPlugin\PlannerBundle\Form\PlannedActivityEditForm;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Test\TypeTestCase;

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

    public function testTimeOffNeverKeepsASubmittedCustomerOrProject(): void
    {
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setCustomer($customer);

        // what the form leaves behind when existing time off gets a customer: the unchanged flag is not written again
        $activity = (new PlannedActivity())->setTimeOff(true);
        $activity->setCustomer($customer)->setProject($project);

        $this->dispatchOwnListener(FormEvents::POST_SUBMIT, $activity, $activity);

        $this->assertTrue($activity->isTimeOff());
        $this->assertNull($activity->getCustomer());
        $this->assertNull($activity->getProject());

        $work = (new PlannedActivity())->setCustomer($customer);
        $this->dispatchOwnListener(FormEvents::POST_SUBMIT, $work, $work);
        $this->assertSame($customer, $work->getCustomer());
    }
}
