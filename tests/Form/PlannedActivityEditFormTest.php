<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Form;

use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityEditForm;
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
        $this->assertTrue($form->has('title'));
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
        $this->assertTrue($form->has('title'));
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
        $this->assertTrue($form->has('title'));
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
        $this->assertTrue($form->has('title'));
        $this->assertTrue($form->has('begin'));
        $this->assertTrue($form->has('end'));
        $this->assertTrue($form->has('hoursPerDay'));
        $this->assertTrue($form->has('color'));
        $this->assertTrue($form->has('comment'));
    }
}
