<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Entity;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PlannedActivityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $activity = new PlannedActivity();

        $this->assertNull($activity->getId());

        $user = new User();
        $this->assertSame($activity, $activity->setUser($user));
        $this->assertSame($user, $activity->getUser());

        $begin = new \DateTime('2026-09-01');
        $this->assertSame($activity, $activity->setBegin($begin));
        $this->assertSame($begin, $activity->getBegin());

        $end = new \DateTime('2026-09-05');
        $this->assertSame($activity, $activity->setEnd($end));
        $this->assertSame($end, $activity->getEnd());

        $this->assertSame($activity, $activity->setTitle('Sprint Planning'));
        $this->assertSame('Sprint Planning', $activity->getTitle());
        $this->assertSame('Sprint Planning', $activity->getName());

        $this->assertSame($activity, $activity->setHoursPerDay(4.5));
        $this->assertSame(4.5, $activity->getHoursPerDay());

        $activity->setColor('#123456');
        $this->assertSame('#123456', $activity->getColor());

        $this->assertSame($activity, $activity->setComment('Important tasks'));
        $this->assertSame('Important tasks', $activity->getComment());
        $this->assertTrue($activity->hasComment());

        $activity->setComment('');
        $this->assertFalse($activity->hasComment());

        $activity->setComment(null);
        $this->assertFalse($activity->hasComment());

        $this->assertNull($activity->getRecurrenceGroup());
        $this->assertFalse($activity->isRecurring());

        $this->assertSame($activity, $activity->setRecurrenceGroup('abc-123'));
        $this->assertSame('abc-123', $activity->getRecurrenceGroup());
        $this->assertTrue($activity->isRecurring());

        $activity->setRecurrenceGroup('');
        $this->assertFalse($activity->isRecurring());

        $activity->setRecurrenceGroup(null);
        $this->assertFalse($activity->isRecurring());
    }

    public function testCoversDateAndHoursCalculation(): void
    {
        $activity = new PlannedActivity();
        $activity->setBegin(new \DateTime('2026-09-01'));
        $activity->setEnd(new \DateTime('2026-09-05'));
        $activity->setHoursPerDay(6.0);

        $this->assertTrue($activity->coversDate(new \DateTime('2026-09-01')));
        $this->assertTrue($activity->coversDate(new \DateTime('2026-09-03')));
        $this->assertTrue($activity->coversDate(new \DateTime('2026-09-05')));
        $this->assertFalse($activity->coversDate(new \DateTime('2026-08-31')));
        $this->assertFalse($activity->coversDate(new \DateTime('2026-09-06')));

        $this->assertSame(5, $activity->getDaysCount());
        $this->assertSame(30.0, $activity->getTotalHours());
    }

    public function testNameIsDerivedFromProjectThenCustomerThenLegacyTitle(): void
    {
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setName('Website');
        $project->setCustomer($customer);

        $activity = new PlannedActivity();
        $this->assertNull($activity->getName());

        $activity->setTitle('Legacy');
        $this->assertSame('Legacy', $activity->getName());

        $activity->setCustomer($customer);
        $this->assertSame('ACME', $activity->getName());

        $activity->setProject($project);
        $this->assertSame('Website', $activity->getName());
        $this->assertSame($customer, $activity->getCustomer());
        $this->assertSame($project, $activity->getProject());
    }

    public function testProjectMustBelongToCustomer(): void
    {
        $customer = new Customer('ACME');
        $other = new Customer('Other');
        $project = new Project();
        $project->setCustomer($other);

        $activity = new PlannedActivity();
        $activity->setCustomer($customer)->setProject($project);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->with('project')->willReturnSelf();
        $builder->method('setTranslationDomain')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('buildViolation')->willReturn($builder);

        $activity->validateProjectBelongsToCustomer($context);
    }

    public function testMatchingProjectAndMissingParentsAreValid(): void
    {
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setCustomer($customer);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        (new PlannedActivity())->setCustomer($customer)->setProject($project)->validateProjectBelongsToCustomer($context);
        (new PlannedActivity())->setCustomer($customer)->validateProjectBelongsToCustomer($context);
        (new PlannedActivity())->validateProjectBelongsToCustomer($context);
    }

    public function testOnlyTheNextDayOfTheSamePlanningIsJoinable(): void
    {
        $user = new User();
        $customer = new Customer('ACME');
        $make = static function (string $begin, string $end) use ($user, $customer): PlannedActivity {
            $activity = (new PlannedActivity())
                ->setUser($user)
                ->setCustomer($customer)
                ->setHoursPerDay(4.0)
                ->setComment(null)
                ->setRecurrenceGroup('series')
                ->setBegin(new \DateTime($begin))
                ->setEnd(new \DateTime($end));
            $activity->setColor('#ff0000');

            return $activity;
        };

        $first = $make('2026-11-02', '2026-11-03');
        $this->assertTrue($first->isJoinableWith($make('2026-11-04', '2026-11-06')));
        $this->assertTrue($first->isJoinableWith($make('2026-11-04', '2026-11-04')->setComment('')));

        $this->assertFalse($first->isJoinableWith($first));
        $this->assertFalse($first->isJoinableWith($make('2026-11-05', '2026-11-06')), 'a gap');
        $this->assertFalse($first->isJoinableWith($make('2026-11-03', '2026-11-04')), 'overlapping');
        $this->assertFalse($make('2026-11-04', '2026-11-06')->isJoinableWith($first), 'wrong order');
        $this->assertFalse($first->isJoinableWith($make('2026-11-04', '2026-11-04')->setHoursPerDay(2.0)));
        $this->assertFalse($first->isJoinableWith($make('2026-11-04', '2026-11-04')->setComment('other')));
        $this->assertFalse($first->isJoinableWith($make('2026-11-04', '2026-11-04')->setRecurrenceGroup(null)));
        $this->assertFalse($first->isJoinableWith($make('2026-11-04', '2026-11-04')->setUser(new User())));
        $this->assertFalse($first->isJoinableWith($make('2026-11-04', '2026-11-04')->setCustomer(new Customer('Other'))));
        $otherColor = $make('2026-11-04', '2026-11-04');
        $otherColor->setColor('#00ff00');
        $this->assertFalse($first->isJoinableWith($otherColor));
    }

    public function testWeeksOfASevenDaySeriesAreNotJoinable(): void
    {
        $user = new User();
        $customer = new Customer('ACME');
        $make = static fn (string $begin, string $end): PlannedActivity => (new PlannedActivity())
            ->setUser($user)
            ->setCustomer($customer)
            ->setRecurrenceGroup('weekly')
            ->setBegin(new \DateTime($begin))
            ->setEnd(new \DateTime($end));

        // two instances of a Monday to Sunday series
        $this->assertFalse($make('2026-11-02', '2026-11-08')->isJoinableWith($make('2026-11-09', '2026-11-15')));
        // the two parts of one split instance
        $this->assertTrue($make('2026-11-02', '2026-11-04')->isJoinableWith($make('2026-11-05', '2026-11-08')));
        // without a series, any length joins
        $this->assertTrue($make('2026-11-02', '2026-11-08')->setRecurrenceGroup(null)->isJoinableWith($make('2026-11-09', '2026-11-15')->setRecurrenceGroup(null)));
    }
}
