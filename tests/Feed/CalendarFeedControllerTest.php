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
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use KimaiPlugin\PlannerBundle\Entity\CalendarFeedToken;
use KimaiPlugin\PlannerBundle\Feed\CalendarFeedController;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CalendarFeedControllerTest extends TestCase
{
    private function createController(?User $user, bool $mayUseFeed = true, ?PlannedActivityRepository $repository = null, ?CalendarFeedBuilder $builder = null, ?bool $userChoice = null): CalendarFeedController
    {
        $feed = null;
        if ($user !== null) {
            $feed = new CalendarFeedToken($user, str_repeat('b', 64), true);
            $feed->setIncludeComments($userChoice);
        }
        $tokens = $this->createStub(CalendarFeedTokens::class);
        $tokens->method('findFeed')->willReturn($feed);

        if ($builder === null) {
            $builder = $this->createStub(CalendarFeedBuilder::class);
            $builder->method('build')->willReturn("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");
        }

        // the organisation leaves comments out but lets people choose
        $settings = $this->createStub(CalendarFeedSettings::class);
        $settings->method('includeComments')->willReturnCallback(static fn (?bool $choice): bool => $choice ?? false);

        $service = $this->createStub(PermissionService::class);
        $service->method('getPermissions')->willReturn([]);
        $permissions = new RolePermissionManager($service, ['ROLE_USER' => ['view_planner' => true, 'calendar_feed_planner' => $mayUseFeed]], ['view_planner' => true, 'calendar_feed_planner' => true]);

        return new CalendarFeedController($tokens, $settings, $builder, $repository ?? $this->createStub(PlannedActivityRepository::class), $permissions);
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

    public function testUserWithoutFeedPermission(): void
    {
        // view_planner alone is not enough: the feed needs calendar_feed_planner, so admins decide who may use it
        $user = new User();
        $user->setEnabled(true);

        $this->expectException(NotFoundHttpException::class);
        $this->createController($user, false)->feed(str_repeat('a', 64));
    }

    public function testTheUsersCommentChoiceReachesTheBuilder(): void
    {
        $user = new User();
        $user->setEnabled(true);
        $user->setTimezone('Europe/Stockholm');

        $builder = $this->createMock(CalendarFeedBuilder::class);
        $builder->expects($this->once())->method('build')->with($user, [], true)->willReturn("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n");

        $repository = $this->createStub(PlannedActivityRepository::class);
        $repository->method('findPlannedActivitiesForUserAndRange')->willReturn([]);

        $this->createController($user, true, $repository, $builder, true)->feed(str_repeat('a', 64));
    }

    public function testIsRegisteredAsAController(): void
    {
        // without AbstractController as parent, the attribute is what makes the route callable
        $this->assertNotEmpty((new \ReflectionClass(CalendarFeedController::class))->getAttributes(\Symfony\Component\HttpKernel\Attribute\AsController::class));
    }
}
