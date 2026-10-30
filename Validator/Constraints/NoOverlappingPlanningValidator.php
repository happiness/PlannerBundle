<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Validator\Constraints;

use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NoOverlappingPlanningValidator extends ConstraintValidator
{
    public function __construct(private readonly PlannedActivityRepository $repository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoOverlappingPlanning) {
            throw new UnexpectedTypeException($constraint, NoOverlappingPlanning::class);
        }

        if (!$value instanceof PlannedActivity) {
            return;
        }

        // a comment or hours change of a row that already overlapped (planned before this check) stays possible
        if (!$this->repository->isPlanningChanged($value) || $this->repository->countOverlapping($value) === 0) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath('begin')
            ->setTranslationDomain('validators')
            ->setCode(NoOverlappingPlanning::OVERLAPPING)
            ->addViolation();
    }
}
