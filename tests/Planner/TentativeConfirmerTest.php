<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Planner;

use App\Entity\Project;
use App\Entity\User;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\TentativeConfirmer;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class TentativeConfirmerTest extends TestCase
{
    private function createActivity(int $userId, string $begin, string $end): PlannedActivity
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $userId);

        return (new PlannedActivity())
            ->setUser($user)
            ->setTentative(true)
            ->setBegin(new \DateTime($begin))
            ->setEnd(new \DateTime($end));
    }

    public function testPrepareSplitsEditableAndSkippedActivities(): void
    {
        $project = new Project();
        $today = new \DateTimeImmutable('2026-11-02');

        $ongoing = $this->createActivity(1, '2026-10-30', '2026-11-04');
        $future = $this->createActivity(2, '2026-11-16', '2026-11-18');
        $notMine = $this->createActivity(3, '2026-11-09', '2026-11-09');

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->once())->method('findTentativeForProject')->with($project, $today)->willReturn([$ongoing, $future, $notMine]);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturnCallback(static fn (string $attribute, mixed $subject): bool => $attribute === PlannedActivityVoter::EDIT && $subject !== $notMine);

        $confirmation = (new TentativeConfirmer($repository, $authChecker))->prepare($project, $today);

        $this->assertSame($project, $confirmation->getProject());
        $this->assertSame([$ongoing, $future], $confirmation->getActivities());
        $this->assertSame([$notMine], $confirmation->getSkipped());
        $this->assertSame(2, $confirmation->getCount());
        $this->assertSame(1, $confirmation->getSkippedCount());
        $this->assertSame(2, $confirmation->getUserCount());
        $this->assertFalse($confirmation->isEmpty());
        $this->assertSame('2026-10-30', $confirmation->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-11-18', $confirmation->getEnd()?->format('Y-m-d'));
    }

    public function testConfirmChangesOnlyTheEditableActivities(): void
    {
        $mine = $this->createActivity(1, '2026-11-02', '2026-11-02');
        $notMine = $this->createActivity(2, '2026-11-02', '2026-11-02');

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->method('findTentativeForProject')->willReturn([$mine, $notMine]);
        $repository->expects($this->once())->method('confirmPlannedActivities')->with([$mine]);

        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturnCallback(static fn (string $attribute, mixed $subject): bool => $subject === $mine);

        $confirmer = new TentativeConfirmer($repository, $authChecker);
        $confirmer->confirm($confirmer->prepare(new Project()));
    }

    public function testEmptyConfirmation(): void
    {
        $repository = $this->createStub(PlannedActivityRepository::class);
        $repository->method('findTentativeForProject')->willReturn([]);

        $confirmation = (new TentativeConfirmer($repository, $this->createStub(AuthorizationCheckerInterface::class)))->prepare(new Project());

        $this->assertTrue($confirmation->isEmpty());
        $this->assertSame(0, $confirmation->getUserCount());
        $this->assertNull($confirmation->getBegin());
        $this->assertNull($confirmation->getEnd());
    }
}
