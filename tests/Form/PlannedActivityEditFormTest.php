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
}
