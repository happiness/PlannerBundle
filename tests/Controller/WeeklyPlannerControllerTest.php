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

    public function testWeekRendersSpecificWeek(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $plannerData = new WeeklyPlannerData(new \DateTimeImmutable('2026-08-17'), new \DateTimeImmutable('2026-08-23'));
        $plannerService->expects($this->once())
            ->method('getPlannerData')
            ->with([$user], $this->callback(function (\DateTimeImmutable $date) {
                return $date->format('Y-m-d') === '2026-08-17';
            }))
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
            ->with(
                '@Planner/index.html.twig',
                $this->callback(function (array $context) {
                    $this->assertArrayHasKey('currentWeek', $context);
                    $this->assertArrayHasKey('prevWeek', $context);
                    $this->assertArrayHasKey('nextWeek', $context);
                    $this->assertArrayHasKey('todayWeek', $context);
                    $this->assertArrayHasKey('selectedDate', $context);
                    $this->assertArrayHasKey('plannerData', $context);
                    $this->assertArrayHasKey('canCreate', $context);

                    $this->assertInstanceOf(\DateTimeImmutable::class, $context['currentWeek']);
                    $this->assertSame('2026-08-17', $context['currentWeek']->format('Y-m-d'));
                    $this->assertSame('34', $context['currentWeek']->format('W'));
                    $this->assertSame('2026-08-10', $context['prevWeek']);
                    $this->assertSame('2026-08-24', $context['nextWeek']);

                    $expectedToday = (new \DateTimeImmutable('today'))->modify('this week monday')->format('Y-m-d');
                    $this->assertSame($expectedToday, $context['todayWeek']);

                    return true;
                })
            )
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

        $response = $controller->week('2026-08-17');
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>Planner View</html>', $response->getContent());
    }

    public function testWeekWithInvalidDateFallsBackToToday(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $expectedToday = new \DateTimeImmutable('today');
        $expectedMonday = $expectedToday->modify('this week monday 00:00:00');

        $plannerData = new WeeklyPlannerData($expectedMonday, $expectedMonday->modify('+6 days'));
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
            ->with(
                '@Planner/index.html.twig',
                $this->callback(function (array $context) use ($expectedMonday) {
                    $this->assertInstanceOf(\DateTimeImmutable::class, $context['currentWeek']);
                    $this->assertSame($expectedMonday->format('Y-m-d'), $context['currentWeek']->format('Y-m-d'));
                    $this->assertSame($expectedMonday->modify('-1 week')->format('Y-m-d'), $context['prevWeek']);
                    $this->assertSame($expectedMonday->modify('+1 week')->format('Y-m-d'), $context['nextWeek']);

                    return true;
                })
            )
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

        $response = $controller->week('not-a-valid-date');
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

    public function testDeleteNonRecurringActivityWithValidToken(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-07'));

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('deletePlannedActivity')->with($activity);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $csrfManager = $this->createMock(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/week/2026-09-07');

        $flashBag = new \Symfony\Component\HttpFoundation\Session\Flash\FlashBag();
        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $request = new Request(['token' => 'valid_token']);
        $request->setSession($session);
        $requestStack->push($request);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.csrf.token_manager', true],
            ['router', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.csrf.token_manager', $csrfManager],
            ['router', $router],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-07'));
    }

    public function testDeleteRecurringActivityRendersPrompt(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setRecurrenceGroup('group-xyz');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('deletePlannedActivity');
        $repo->expects($this->never())->method('deleteRecurringActivities');

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/activity/5/delete');

        $formView = $this->createMock(FormView::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $form->method('isSubmitted')->willReturn(false);

        $formBuilder = $this->createMock(\Symfony\Component\Form\FormBuilderInterface::class);
        $formBuilder->method('add')->willReturnSelf();
        $formBuilder->method('setAction')->willReturnSelf();
        $formBuilder->method('setMethod')->willReturnSelf();
        $formBuilder->method('getForm')->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createBuilder')->willReturn($formBuilder);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/delete.html.twig', $this->callback(function (array $context) use ($activity) {
                return isset($context['form'], $context['activity']) && $context['activity'] === $activity;
            }))
            ->willReturn('<html>Delete Confirmation Prompt</html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['router', true],
            ['form.factory', true],
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['router', $router],
            ['form.factory', $formFactory],
            ['twig', $twig],
        ]);

        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>Delete Confirmation Prompt</html>', $response->getContent());
    }

    public function testDeleteRecurringActivitySubmitsDeleteSingle(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setRecurrenceGroup('group-xyz');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('deletePlannedActivity')->with($activity);
        $repo->expects($this->never())->method('deleteRecurringActivities');

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/activity/5/delete');

        $deleteModeField = $this->createMock(FormInterface::class);
        $deleteModeField->method('getData')->willReturn('single');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('delete_mode')->willReturn($deleteModeField);

        $formBuilder = $this->createMock(\Symfony\Component\Form\FormBuilderInterface::class);
        $formBuilder->method('add')->willReturnSelf();
        $formBuilder->method('setAction')->willReturnSelf();
        $formBuilder->method('setMethod')->willReturnSelf();
        $formBuilder->method('getForm')->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createBuilder')->willReturn($formBuilder);

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
            ['router', true],
            ['form.factory', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['router', $router],
            ['form.factory', $formFactory],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/activity/5/delete'));
    }

    public function testDeleteRecurringActivitySubmitsDeleteFuture(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setRecurrenceGroup('group-xyz');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('deletePlannedActivity');
        $repo->expects($this->once())->method('deleteFutureRecurringActivities')->with($activity);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/week/2026-09-07');

        $deleteModeField = $this->createMock(FormInterface::class);
        $deleteModeField->method('getData')->willReturn('future');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('delete_mode')->willReturn($deleteModeField);

        $formBuilder = $this->createMock(\Symfony\Component\Form\FormBuilderInterface::class);
        $formBuilder->method('add')->willReturnSelf();
        $formBuilder->method('setAction')->willReturnSelf();
        $formBuilder->method('setMethod')->willReturnSelf();
        $formBuilder->method('getForm')->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createBuilder')->willReturn($formBuilder);

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
            ['router', true],
            ['form.factory', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['router', $router],
            ['form.factory', $formFactory],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-07'));
    }

    public function testDeleteRecurringActivitySubmitsDeleteAllBackwardCompatibility(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-07'));
        $activity->setRecurrenceGroup('group-xyz');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('deletePlannedActivity');
        $repo->expects($this->once())->method('deleteFutureRecurringActivities')->with($activity);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/week/2026-09-07');

        $deleteModeField = $this->createMock(FormInterface::class);
        $deleteModeField->method('getData')->willReturn('all');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('delete_mode')->willReturn($deleteModeField);

        $formBuilder = $this->createMock(\Symfony\Component\Form\FormBuilderInterface::class);
        $formBuilder->method('add')->willReturnSelf();
        $formBuilder->method('setAction')->willReturnSelf();
        $formBuilder->method('setMethod')->willReturnSelf();
        $formBuilder->method('getForm')->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createBuilder')->willReturn($formBuilder);

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
            ['router', true],
            ['form.factory', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['router', $router],
            ['form.factory', $formFactory],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-07'));
    }

    public function testEditActivityWithDateQueryPassesDateToFormAndTemplate(): void
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
        $router->expects($this->once())
            ->method('generate')
            ->with('planner_activity_edit', ['id' => 10, 'date' => '2026-09-15'])
            ->willReturn('/planner/activity/10/edit?date=2026-09-15');

        $formView = $this->createMock(FormView::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/form.html.twig', $this->callback(function (array $context) use ($activity) {
                return isset($context['form'], $context['activity'], $context['title'], $context['date'])
                    && $context['activity'] === $activity
                    && $context['date'] === '2026-09-15';
            }))
            ->willReturn('<html>Edit Form View With Date</html>');

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

        $request = new Request(['date' => '2026-09-15']);
        $response = $controller->editActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>Edit Form View With Date</html>', $response->getContent());
    }

    public function testDeleteNonRecurringActivityDeletesPlannedActivity(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-16'));

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('deletePlannedActivity')->with($activity);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $csrfManager = $this->createMock(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('planner_week', ['date' => '2026-09-15'])->willReturn('/planner/week/2026-09-15');

        $flashBag = new \Symfony\Component\HttpFoundation\Session\Flash\FlashBag();
        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $request = new Request(['token' => 'valid_token', 'date' => '2026-09-15']);
        $request->setSession($session);
        $requestStack->push($request);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.csrf.token_manager', true],
            ['router', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.csrf.token_manager', $csrfManager],
            ['router', $router],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-15'));
    }

    public function testDeleteRecurringActivitySubmitsDeleteSingleDeletesPlannedActivity(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $activityRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $activityRef->setValue($activity, 5);
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setEnd(new \DateTime('2026-09-16'));
        $activity->setRecurrenceGroup('group-xyz');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('deletePlannedActivity')->with($activity);
        $repo->expects($this->never())->method('deleteRecurringActivities');
        $repo->expects($this->never())->method('deleteFutureRecurringActivities');

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route, array $params = []) {
            if ($route === 'planner_week') {
                return '/planner/week/' . ($params['date'] ?? '');
            }

            return '/planner/activity/5/delete';
        });

        $deleteModeField = $this->createMock(FormInterface::class);
        $deleteModeField->method('getData')->willReturn('single');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('delete_mode')->willReturn($deleteModeField);

        $formBuilder = $this->createMock(\Symfony\Component\Form\FormBuilderInterface::class);
        $formBuilder->method('add')->willReturnSelf();
        $formBuilder->method('setAction')->willReturnSelf();
        $formBuilder->method('setMethod')->willReturnSelf();
        $formBuilder->method('getForm')->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createBuilder')->willReturn($formBuilder);

        $flashBag = new \Symfony\Component\HttpFoundation\Session\Flash\FlashBag();
        $session = $this->createMock(\Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface::class);
        $session->method('getFlashBag')->willReturn($flashBag);

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $request = new Request(['date' => '2026-09-15']);
        $request->setSession($session);
        $requestStack->push($request);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['router', true],
            ['form.factory', true],
            ['request_stack', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['router', $router],
            ['form.factory', $formFactory],
            ['request_stack', $requestStack],
        ]);

        $controller->setContainer($container);

        $response = $controller->deleteActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-15'));
    }

    public function testEditRecurringActivityWithSingleModeDoesNotCallUpdateFutureRecurring(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $actRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $actRef->setValue($activity, 10);
        $activity->setUser($user);
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setRecurrenceGroup('group-rec-1');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('savePlannedActivity')->with($activity);
        $repo->expects($this->never())->method('updateFutureRecurringActivities');

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/week/2026-09-14');

        $editModeField = $this->createMock(FormInterface::class);
        $editModeField->method('getData')->willReturn('single');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('has')->with('edit_mode')->willReturn(true);
        $form->method('get')->with('edit_mode')->willReturn($editModeField);

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

        $response = $controller->editActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-14'));
    }

    public function testEditRecurringActivityWithFutureModeCallsUpdateFutureRecurring(): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $activity = new PlannedActivity();
        $actRef = new \ReflectionProperty(PlannedActivity::class, 'id');
        $actRef->setValue($activity, 10);
        $activity->setUser($user);
        $activity->setBegin(new \DateTime('2026-09-14'));
        $activity->setRecurrenceGroup('group-rec-1');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('savePlannedActivity')->with($activity);
        $repo->expects($this->once())->method('updateFutureRecurringActivities')->with($activity);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $userRepo = $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/week/2026-09-14');

        $editModeField = $this->createMock(FormInterface::class);
        $editModeField->method('getData')->willReturn('future');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('has')->with('edit_mode')->willReturn(true);
        $form->method('get')->with('edit_mode')->willReturn($editModeField);

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

        $response = $controller->editActivity($activity, $request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isRedirect('/planner/week/2026-09-14'));
    }
}
