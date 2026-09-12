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
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerData;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
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

    public function testCreateActivityRendersForm(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/activity/create');

        $formView = $this->createMock(FormView::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/form.html.twig', $this->callback(function (array $context) {
                return isset($context['form'], $context['activity'], $context['title']);
            }))
            ->willReturn('<html>Form View</html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ['router', true],
            ['form.factory', true],
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ['router', $router],
            ['form.factory', $formFactory],
            ['twig', $twig],
        ]);

        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->createActivity($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>Form View</html>', $response->getContent());
    }

    public function testEditActivityRendersForm(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $actRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $actRef->setValue($activity, 10);
        $activity->setUser($user);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/activity/10/edit');

        $formView = $this->createMock(FormView::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/form.html.twig', $this->callback(function (array $context) {
                return isset($context['form'], $context['activity'], $context['title']);
            }))
            ->willReturn('<html>Edit Form View</html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ['router', true],
            ['form.factory', true],
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ['router', $router],
            ['form.factory', $formFactory],
            ['twig', $twig],
        ]);

        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->editActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>Edit Form View</html>', $response->getContent());
    }

    public function testCreateActivityWithRecurrenceSubmitsSuccessfully(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('savePlannedActivity');
        $repo->expects($this->once())->method('createRecurringActivities')->with($this->isInstanceOf(PlannedActivity::class), 3);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/week/2026-09-07');

        $recurrentField = $this->createMock(FormInterface::class);
        $recurrentField->method('getData')->willReturn(3);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('has')->with('recurrentWeeks')->willReturn(true);
        $form->method('get')->with('recurrentWeeks')->willReturn($recurrentField);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $flashBag = new \Symfony\Component\HttpFoundation\Session\Flash\FlashBag();
        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ['router', true],
            ['form.factory', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ['router', $router],
            ['form.factory', $formFactory],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->createActivity($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-07'));
    }
}
