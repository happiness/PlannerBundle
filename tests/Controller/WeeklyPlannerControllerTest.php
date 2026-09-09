<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\PlannerBundle\Controller\WeeklyPlannerController;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerData;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class WeeklyPlannerControllerTest extends TestCase
{
    public function testIndexRendersWeeklyPlanner(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $plannerData = new WeeklyPlannerData(new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-13'));
        $plannerService->expects($this->once())
            ->method('getPlannerData')
            ->willReturn($plannerData);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/index.html.twig', $this->isType('array'))
            ->willReturn('<html>Planner View</html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ['twig', $twig],
        ]);

        $controller->setContainer($container);

        $response = $controller->index();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>Planner View</html>', $response->getContent());
    }
}
