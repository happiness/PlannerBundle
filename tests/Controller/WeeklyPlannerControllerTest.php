<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Controller;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Repository\UserRepository;
use KimaiPlugin\PlannerBundle\Controller\WeeklyPlannerController;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerData;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $formServices = $this->createFormServices();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ...$this->formServiceMap('has', $formServices),
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ...$this->formServiceMap('get', $formServices),
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

        $userProvider = $this->createMock(PlannerUserProvider::class);
        $userProvider->method('getVisibleUsers')->with($user)->willReturn([$user]);

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $userProvider);

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

        $formServices = $this->createFormServices();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ...$this->formServiceMap('has', $formServices),
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ...$this->formServiceMap('get', $formServices),
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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $formServices = $this->createFormServices();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ...$this->formServiceMap('has', $formServices),
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ...$this->formServiceMap('get', $formServices),
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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

        $controller = new WeeklyPlannerController($repo, $plannerService, $userRepo, $this->createMock(PlannerUserProvider::class));

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

    /**
     * @return array<string, array{array<string, string>, int|null, int}>
     */
    public static function weeksScenarios(): array
    {
        return [
            'default is four weeks' => [[], null, 4],
            'query parameter wins' => [['weeks' => '2'], null, 2],
            'invalid parameter falls back to default' => [['weeks' => '3'], null, 4],
            'session value is used without parameter' => [[], 8, 8],
            'invalid session value falls back to default' => [[], 5, 4],
            'parameter overrides session' => [['weeks' => '1'], 8, 1],
        ];
    }

    /**
     * @param array<string, string> $query
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('weeksScenarios')]
    public function testNumberOfWeeksIsResolvedFromQueryAndSession(array $query, ?int $sessionValue, int $expectedWeeks): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $plannerService->expects($this->once())
            ->method('getPlannerData')
            ->with($this->anything(), $this->anything(), $expectedWeeks)
            ->willReturn(new WeeklyPlannerData(new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-13')));

        $controller = new WeeklyPlannerController(
            $this->createMock(PlannedActivityRepository::class),
            $plannerService,
            $this->createMock(UserRepository::class),
            $this->createMock(PlannerUserProvider::class)
        );

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/index.html.twig', $this->callback(function (array $context) use ($expectedWeeks): bool {
                $this->assertSame($expectedWeeks, $context['weeks']);
                $this->assertSame([1, 2, 4, 8], $context['weekOptions']);

                return true;
            }))
            ->willReturn('<html></html>');

        $formServices = $this->createFormServices();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ...$this->formServiceMap('has', $formServices),
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ...$this->formServiceMap('get', $formServices),
            ['twig', $twig],
        ]);
        $controller->setContainer($container);

        $session = new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        if ($sessionValue !== null) {
            $session->set('planner_weeks', $sessionValue);
        }
        $request = new \Symfony\Component\HttpFoundation\Request($query);
        $request->setSession($session);

        $controller->index($request);

        if (isset($query['weeks']) && \in_array((int) $query['weeks'], WeeklyPlannerController::WEEK_OPTIONS, true)) {
            $this->assertSame($expectedWeeks, $session->get('planner_weeks'));
        }
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function teamPreSelectionScenarios(): array
    {
        return [
            'single team is pre-selected' => [1, true],
            'multiple teams are not pre-selected' => [2, false],
            'no team is not pre-selected' => [0, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('teamPreSelectionScenarios')]
    public function testTeamFilterIsPreSelectedOnlyForSingleTeam(int $teamCount, bool $expectPreSelection): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);
        for ($i = 1; $i <= $teamCount; $i++) {
            $user->addTeam(new Team('Team ' . $i));
        }

        $submittedQuery = null;
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($this->createMock(FormView::class));
        $form->method('isSubmitted')->willReturn(false);
        $form->method('getData')->willReturnCallback(static function () use (&$submittedQuery) {
            return $submittedQuery;
        });

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturnCallback(function (string $name, string $type, mixed $data) use ($form, &$submittedQuery) {
            $submittedQuery = $data;

            return $form;
        });

        $userProvider = $this->createMock(PlannerUserProvider::class);
        $userProvider->expects($this->once())
            ->method('getVisibleUsers')
            ->with($user, $this->isInstanceOf(PlannerQuery::class))
            ->willReturn([$user]);

        $plannerService = $this->createMock(WeeklyPlannerService::class);
        $plannerService->method('getPlannerData')
            ->willReturn(new WeeklyPlannerData(new \DateTimeImmutable('2026-09-07'), new \DateTimeImmutable('2026-09-13')));

        $controller = new WeeklyPlannerController(
            $this->createMock(PlannedActivityRepository::class),
            $plannerService,
            $this->createMock(UserRepository::class),
            $userProvider
        );

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Planner/index.html.twig', $this->callback(function (array $context) use ($expectPreSelection): bool {
                $this->assertInstanceOf(PlannerQuery::class, $context['query']);
                $this->assertSame($expectPreSelection ? 1 : 0, \count($context['query']->getTeams()));
                $this->assertArrayHasKey('toolbarForm', $context);

                return true;
            }))
            ->willReturn('<html></html>');

        $formServices = ['form.factory' => $formFactory, 'bookmark' => $this->createMock(BookmarkRepository::class)];
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['security.authorization_checker', true],
            ['security.token_storage', true],
            ...$this->formServiceMap('has', $formServices),
            ['twig', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ...$this->formServiceMap('get', $formServices),
            ['twig', $twig],
        ]);
        $controller->setContainer($container);

        $this->assertInstanceOf(Response::class, $controller->index());
    }

    /**
     * @return array{form.factory: FormFactoryInterface, bookmark: BookmarkRepository}
     */
    private function createFormServices(?PlannerQuery $query = null): array
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($this->createMock(FormView::class));
        $form->method('getData')->willReturn($query ?? new PlannerQuery());
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('createNamed')->willReturn($form);

        return ['form.factory' => $formFactory, 'bookmark' => $this->createMock(BookmarkRepository::class)];
    }

    /**
     * @param array{form.factory: FormFactoryInterface, bookmark: BookmarkRepository} $formServices
     * @return array<int, array{string, mixed}>
     */
    private function formServiceMap(string $type, array $formServices): array
    {
        $value = static fn (mixed $service): mixed => $type === 'has' ? true : $service;

        return [
            ['form.factory', $value($formServices['form.factory'])],
            [BookmarkRepository::class, $value($formServices['bookmark'])],
        ];
    }

    /**
     * @param array<string, string> $query
     * @param callable(PlannedActivity): void $assertions
     */
    private function runCreateActivity(array $query, bool $accessGranted, ?PlannedActivityRepository $repo, callable $assertions): void
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 1);

        $repo ??= $this->createMock(PlannedActivityRepository::class);

        $controller = new WeeklyPlannerController($repo, $this->createMock(WeeklyPlannerService::class), $this->createMock(UserRepository::class), $this->createMock(PlannerUserProvider::class));

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturnCallback(static fn (string $attribute): bool => $attribute !== 'access' || $accessGranted);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/planner/activity/create');

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($this->createMock(FormView::class));
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(
            $this->anything(),
            $this->callback(function (PlannedActivity $activity) use ($assertions): bool {
                $assertions($activity);

                return true;
            })
        )->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html></html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
            ['router', $router],
            ['form.factory', $formFactory],
            ['twig', $twig],
        ]);
        $controller->setContainer($container);

        $controller->createActivity(new Request($query));
    }

    public function testCreateActivityWithEndParameterSpansSeveralDays(): void
    {
        $this->runCreateActivity(['date' => '2026-10-19', 'end' => '2026-10-22'], true, null, function (PlannedActivity $activity): void {
            $this->assertSame('2026-10-19', $activity->getBegin()?->format('Y-m-d'));
            $this->assertSame('2026-10-22', $activity->getEnd()?->format('Y-m-d'));
        });
    }

    public function testCreateActivityIgnoresEndBeforeBeginAndInvalidEnd(): void
    {
        $this->runCreateActivity(['date' => '2026-10-19', 'end' => '2026-10-10'], true, null, function (PlannedActivity $activity): void {
            $this->assertSame('2026-10-19', $activity->getEnd()?->format('Y-m-d'));
        });
        $this->runCreateActivity(['date' => '2026-10-19', 'end' => 'not a date'], true, null, function (PlannedActivity $activity): void {
            $this->assertSame('2026-10-19', $activity->getEnd()?->format('Y-m-d'));
        });
    }

    public function testCreateActivityPrefillsProjectAndItsCustomer(): void
    {
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setCustomer($customer);

        $em = $this->createMock(PlannedActivityRepository::class);
        $em->method('findProject')->with(7)->willReturn($project);

        $this->runCreateActivity(['date' => '2026-10-19', 'project' => '7'], true, $em, function (PlannedActivity $activity) use ($project, $customer): void {
            $this->assertSame($project, $activity->getProject());
            $this->assertSame($customer, $activity->getCustomer());
        });
    }

    public function testCreateActivityPrefillsCustomerOnly(): void
    {
        $customer = new Customer('ACME');

        $em = $this->createMock(PlannedActivityRepository::class);
        $em->method('findCustomer')->with(3)->willReturn($customer);

        $this->runCreateActivity(['customer' => '3'], true, $em, function (PlannedActivity $activity) use ($customer): void {
            $this->assertSame($customer, $activity->getCustomer());
            $this->assertNull($activity->getProject());
        });
    }

    public function testCreateActivityInTheTimeOffRowCreatesTimeOffForTheWholeDay(): void
    {
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('findCustomer');
        $repo->expects($this->never())->method('findProject');

        $this->runCreateActivity(['date' => '2026-11-06', 'off' => '1', 'customer' => '3', 'project' => '7'], true, $repo, function (PlannedActivity $activity): void {
            $this->assertTrue($activity->isTimeOff());
            $this->assertNull($activity->getHoursPerDay());
            $this->assertNull($activity->getCustomer());
        });
    }

    public function testCreateActivityDoesNotPrefillInaccessibleCustomerOrProject(): void
    {
        $customer = new Customer('Secret');
        $project = new Project();
        $project->setCustomer($customer);

        $em = $this->createMock(PlannedActivityRepository::class);
        $em->method('findProject')->willReturn($project);
        $em->method('findCustomer')->willReturn($customer);

        $this->runCreateActivity(['project' => '7', 'customer' => '3'], false, $em, function (PlannedActivity $activity): void {
            $this->assertNull($activity->getCustomer());
            $this->assertNull($activity->getProject());
        });
    }

    /**
     * @param array<string, string> $post
     * @param callable(string, mixed): bool|null $isGranted
     */
    private function runMoveActivity(
        PlannedActivity $activity,
        array $post,
        ?PlannedActivityRepository $repo = null,
        ?UserRepository $userRepo = null,
        bool $validToken = true,
        ?callable $isGranted = null,
        int $violations = 0
    ): JsonResponse {
        $repo ??= $this->createMock(PlannedActivityRepository::class);
        $userRepo ??= $this->createMock(UserRepository::class);

        $controller = new WeeklyPlannerController($repo, $this->createMock(WeeklyPlannerService::class), $userRepo, $this->createMock(PlannerUserProvider::class));

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturnCallback($isGranted ?? static fn (): bool => true);

        $csrfManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn($validToken);

        $list = new ConstraintViolationList();
        for ($i = 0; $i < $violations; ++$i) {
            $list->add(new ConstraintViolation('Invalid dates', null, [], null, 'end', null));
        }
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn($list);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.csrf.token_manager', $csrfManager],
        ]);
        $controller->setContainer($container);

        return $controller->moveActivity($activity, new Request([], $post), $validator);
    }

    private function createMovableActivity(): PlannedActivity
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 1);

        $activity = new PlannedActivity();
        $activity->setUser($user);
        $activity->setBegin(new \DateTime('2026-10-19'));
        $activity->setEnd(new \DateTime('2026-10-20'));
        $activity->setHoursPerDay(6.0);

        return $activity;
    }

    public function testMoveActivityChangesDatesAndKeepsHours(): void
    {
        $activity = $this->createMovableActivity();

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->once())->method('savePlannedActivity')->with($activity);

        $response = $this->runMoveActivity($activity, ['begin' => '2026-10-21', 'end' => '2026-10-23'], $repo);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2026-10-21', $activity->getBegin()?->format('Y-m-d'));
        $this->assertSame('2026-10-23', $activity->getEnd()?->format('Y-m-d'));
        $this->assertSame(6.0, $activity->getHoursPerDay());
    }

    public function testMoveActivityChangesUserCustomerAndProject(): void
    {
        $activity = $this->createMovableActivity();

        $newUser = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($newUser, 2);
        $customer = new Customer('ACME');
        $project = new Project();
        $project->setCustomer($customer);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(2)->willReturn($newUser);
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->method('findCustomer')->with(3)->willReturn($customer);
        $repo->method('findProject')->with(7)->willReturn($project);
        $repo->expects($this->once())->method('savePlannedActivity');

        $this->runMoveActivity($activity, ['begin' => '2026-10-19', 'end' => '2026-10-20', 'user' => '2', 'customer' => '3', 'project' => '7'], $repo, $userRepo);

        $this->assertSame($newUser, $activity->getUser());
        $this->assertSame($customer, $activity->getCustomer());
        $this->assertSame($project, $activity->getProject());
    }

    public function testMoveActivityWithoutProjectClearsProject(): void
    {
        $activity = $this->createMovableActivity();
        $activity->setProject(new Project());
        $customer = new Customer('ACME');

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->method('findCustomer')->willReturn($customer);

        $this->runMoveActivity($activity, ['begin' => '2026-10-19', 'end' => '2026-10-20', 'customer' => '3'], $repo);

        $this->assertSame($customer, $activity->getCustomer());
        $this->assertNull($activity->getProject());
    }

    public function testMoveTimeOffIgnoresTheCustomerOfTheTargetRow(): void
    {
        $activity = $this->createMovableActivity();
        $activity->setTimeOff(true);

        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('findCustomer');
        $repo->expects($this->once())->method('savePlannedActivity');

        $response = $this->runMoveActivity($activity, ['begin' => '2026-10-21', 'end' => '2026-10-21', 'customer' => '3', 'project' => '7'], $repo);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($activity->isTimeOff());
        $this->assertNull($activity->getCustomer());
        $this->assertSame('2026-10-21', $activity->getBegin()?->format('Y-m-d'));
    }

    public function testMoveActivityRejectsInvalidToken(): void
    {
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('savePlannedActivity');

        $response = $this->runMoveActivity($this->createMovableActivity(), ['begin' => '2026-10-21', 'end' => '2026-10-22'], $repo, null, false);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testMoveActivityRejectsInvalidDates(): void
    {
        $activity = $this->createMovableActivity();

        $response = $this->runMoveActivity($activity, ['begin' => 'nope', 'end' => '2026-10-22']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('2026-10-19', $activity->getBegin()?->format('Y-m-d'));
    }

    public function testMoveActivityDeniedWithoutEditPermission(): void
    {
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('savePlannedActivity');

        $this->expectException(AccessDeniedException::class);

        $this->runMoveActivity($this->createMovableActivity(), ['begin' => '2026-10-21', 'end' => '2026-10-22'], $repo, null, true, static fn (): bool => false);
    }

    public function testMoveActivityDeniedWhenMovedToUserWithoutPermission(): void
    {
        $activity = $this->createMovableActivity();
        $newUser = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($newUser, 2);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->willReturn($newUser);
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('savePlannedActivity');

        $this->expectException(AccessDeniedException::class);

        $this->runMoveActivity(
            $activity,
            ['begin' => '2026-10-21', 'end' => '2026-10-22', 'user' => '2'],
            $repo,
            $userRepo,
            true,
            static fn (string $attribute, mixed $subject): bool => !($subject instanceof PlannedActivity && $subject->getUser()?->getId() === 2)
        );
    }

    public function testMoveActivityDeniedForInaccessibleCustomer(): void
    {
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->method('findCustomer')->willReturn(new Customer('Secret'));
        $repo->expects($this->never())->method('savePlannedActivity');

        $this->expectException(AccessDeniedException::class);

        $this->runMoveActivity(
            $this->createMovableActivity(),
            ['begin' => '2026-10-21', 'end' => '2026-10-22', 'customer' => '3'],
            $repo,
            null,
            true,
            static fn (string $attribute): bool => $attribute !== 'access'
        );
    }

    public function testMoveActivityReturnsValidationMessageAndDoesNotSave(): void
    {
        $repo = $this->createMock(PlannedActivityRepository::class);
        $repo->expects($this->never())->method('savePlannedActivity');

        $response = $this->runMoveActivity($this->createMovableActivity(), ['begin' => '2026-10-21', 'end' => '2026-10-22'], $repo, null, true, null, 1);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Invalid dates', (string) $response->getContent());
    }
}
