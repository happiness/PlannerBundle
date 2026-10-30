<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Validator\Constraints;

use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Validator\Constraints\NoOverlappingPlanning;
use KimaiPlugin\PlannerBundle\Validator\Constraints\NoOverlappingPlanningValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class NoOverlappingPlanningValidatorTest extends TestCase
{
    private function validate(int $overlapping, int $expectedViolations, bool $changed = true): void
    {
        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->method('isPlanningChanged')->willReturn($changed);
        $repository->method('countOverlapping')->willReturn($overlapping);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->with('begin')->willReturnSelf();
        $builder->method('setTranslationDomain')->willReturnSelf();
        $builder->method('setCode')->with(NoOverlappingPlanning::OVERLAPPING)->willReturnSelf();
        $builder->expects($this->exactly($expectedViolations))->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->exactly($expectedViolations))->method('buildViolation')->willReturn($builder);

        $validator = new NoOverlappingPlanningValidator($repository);
        $validator->initialize($context);
        $validator->validate(new PlannedActivity(), new NoOverlappingPlanning());
    }

    public function testOverlappingPlanningIsAViolation(): void
    {
        $this->validate(1, 1);
    }

    public function testNoOverlapIsValid(): void
    {
        $this->validate(0, 0);
    }

    public function testAnUnchangedRowThatAlreadyOverlapsCanStillBeSaved(): void
    {
        $this->validate(1, 0, false);
    }

    public function testRejectsOtherConstraints(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        (new NoOverlappingPlanningValidator($this->createMock(PlannedActivityRepository::class)))->validate(new PlannedActivity(), new NotNull());
    }

    public function testIsAClassConstraint(): void
    {
        $this->assertSame(NoOverlappingPlanning::CLASS_CONSTRAINT, (new NoOverlappingPlanning())->getTargets());
    }
}
