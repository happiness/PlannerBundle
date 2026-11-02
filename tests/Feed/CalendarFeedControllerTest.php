<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Feed;

use App\Entity\User;
use App\Security\RolePermissionManager;
use App\User\PermissionService;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedBuilder;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use KimaiPlugin\PlannerBundle\Feed\CalendarFeedController;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CalendarFeedControllerTest extends TestCase
{
    private function createController(?User $user, bool $mayPlan = true, ?PlannedActivityRepository $repository = null): CalendarFeedController
    {
        $tokens = $this->createStub(CalendarFeedTokens::class);
        $tokens->method('findUser')->willReturn($user);

        $builder = $this->createStub(CalendarFeedBuilder::class);
        $builder->method('build')->willReturn("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");

        $service = $this->createStub(PermissionService::class);
        $service->method('getPermissions')->willReturn([]);
        $permissions = new RolePermissionManager($service, ['ROLE_USER' => ['view_planner' => $mayPlan]], ['view_planner' => true]);

        return new CalendarFeedController($tokens, $builder, $repository ?? $this->createStub(PlannedActivityRepository::class), $permissions);
    }

    public function testFeed(): void
    {
        $user = new User();
        $user->setEnabled(true);
        $user->setTimezone('Europe/Stockholm');

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->once())
            ->method('findPlannedActivitiesForUserAndRange')
            ->with(
                $user,
                $this->callback(static fn (\DateTimeInterface $begin): bool => $begin->format('Y-m-d H:i:s') === (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Stockholm')))->modify('-60 days')->format('Y-m-d H:i:s')),
                $this->callback(static fn (\DateTimeInterface $end): bool => $end->format('Y-m-d H:i:s') === (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Stockholm')))->modify('+365 days')->format('Y-m-d') . ' 23:59:59')
            )
            ->willReturn([]);

        $response = $this->createController($user, true, $repository)->feed(str_repeat('a', 64));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('BEGIN:VCALENDAR', (string) $response->getContent());
    }

    public function testUnknownToken(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->createController(null)->feed(str_repeat('a', 64));
    }

    public function testDisabledUser(): void
    {
        $user = new User();
        $user->setEnabled(false);

        $this->expectException(NotFoundHttpException::class);
        $this->createController($user)->feed(str_repeat('a', 64));
    }

    public function testUserWithoutPlannerPermission(): void
    {
        $user = new User();
        $user->setEnabled(true);

        $this->expectException(NotFoundHttpException::class);
        $this->createController($user, false)->feed(str_repeat('a', 64));
    }

    public function testIsRegisteredAsAController(): void
    {
        // without AbstractController as parent, the attribute is what makes the route callable
        $this->assertNotEmpty((new \ReflectionClass(CalendarFeedController::class))->getAttributes(\Symfony\Component\HttpKernel\Attribute\AsController::class));
    }
}
