<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\API;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\PlannerBundle\API\PlannedActivityController;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use KimaiPlugin\PlannerBundle\Planner\TentativeConfirmer;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PlannedActivityControllerTest extends TestCase
{
    private function createController(PlannedActivityRepository $repository, ?ViewHandlerInterface $viewHandler = null): PlannedActivityController
    {
        $viewHandler ??= $this->createStub(ViewHandlerInterface::class);

        $controller = new PlannedActivityController($viewHandler, $repository, $this->createStub(PlannerUserProvider::class));

        $user = new User();
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $authChecker],
            ['security.token_storage', $tokenStorage],
        ]);
        $controller->setContainer($container);

        return $controller;
    }

    private function createRecurringActivity(): PlannedActivity
    {
        return (new PlannedActivity())->setRecurrenceGroup('group');
    }

    public function testDeleteSingleRemovesOnlyThisActivity(): void
    {
        $activity = $this->createRecurringActivity();

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->once())->method('deletePlannedActivity')->with($activity);
        $repository->expects($this->never())->method('deleteFutureRecurringActivities');

        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects($this->once())->method('handle')
            ->with($this->callback(static fn (View $view): bool => $view->getStatusCode() === Response::HTTP_NO_CONTENT))
            ->willReturn(new Response('', Response::HTTP_NO_CONTENT));

        $response = $this->createController($repository, $viewHandler)->deleteAction(new Request(), $activity);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDeleteFutureRemovesFollowingActivitiesOfRecurringSeries(): void
    {
        $activity = $this->createRecurringActivity();

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->once())->method('deleteFutureRecurringActivities')->with($activity);
        $repository->expects($this->never())->method('deletePlannedActivity');

        $request = new Request(['mode' => 'future']);
        $viewHandler = $this->createStub(ViewHandlerInterface::class);
        $viewHandler->method('handle')->willReturn(new Response('', Response::HTTP_NO_CONTENT));

        $this->createController($repository, $viewHandler)->deleteAction($request, $activity);
    }

    public function testDeleteFutureOnNonRecurringActivityDeletesOnlyIt(): void
    {
        $activity = new PlannedActivity();

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->once())->method('deletePlannedActivity')->with($activity);
        $repository->expects($this->never())->method('deleteFutureRecurringActivities');

        $viewHandler = $this->createStub(ViewHandlerInterface::class);
        $viewHandler->method('handle')->willReturn(new Response('', Response::HTTP_NO_CONTENT));

        $this->createController($repository, $viewHandler)->deleteAction(new Request(['mode' => 'future']), $activity);
    }

    public function testDeleteWithInvalidModeIsBadRequest(): void
    {
        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->never())->method('deletePlannedActivity');

        $this->expectException(BadRequestHttpException::class);

        $this->createController($repository)->deleteAction(new Request(['mode' => 'all']), new PlannedActivity());
    }

    public function testPatchWithInvalidEditModeIsBadRequest(): void
    {
        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->never())->method('savePlannedActivity');

        $request = new Request();
        $request->request = new InputBag(['edit_mode' => 'everything']);

        $this->expectException(BadRequestHttpException::class);

        $this->createController($repository)->patchAction($request, new PlannedActivity());
    }

    public function testPatchReadsTheEditModeAsTheApiDeliversIt(): void
    {
        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->never())->method('savePlannedActivity');

        $request = new Request();
        $request->request = new InputBag(['editMode' => 'everything']);

        $this->expectException(BadRequestHttpException::class);

        $this->createController($repository)->patchAction($request, new PlannedActivity());
    }

    public function testPatchDoesNotSubmitTheEditModeToTheForm(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->exactly(2))->method('submit')->with(['comment' => 'x'], false);
        $form->method('isValid')->willReturn(false);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $viewHandler = $this->createStub(ViewHandlerInterface::class);
        $viewHandler->method('handle')->willReturn(new Response());

        $controller = $this->createController($this->createStub(PlannedActivityRepository::class), $viewHandler);
        $user = new User();
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.authorization_checker', $this->createStub(AuthorizationCheckerInterface::class)],
            ['security.token_storage', $tokenStorage],
            ['form.factory', $formFactory],
        ]);
        $controller->setContainer($container);

        foreach (['editMode', 'edit_mode'] as $key) {
            $request = new Request();
            $request->request = new InputBag([$key => 'single', 'comment' => 'x']);
            $controller->patchAction($request, new PlannedActivity());
        }
    }

    public function testConfirmTentativeWithoutProjectIsBadRequest(): void
    {
        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->expects($this->never())->method('confirmPlannedActivities');

        $this->expectException(BadRequestHttpException::class);

        $this->createController($repository)->confirmTentativeAction(new Request(), $this->createStub(ProjectRepository::class), $this->createStub(TentativeConfirmer::class));
    }

    public function testConfirmTentativeWithUnknownProjectIsNotFound(): void
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn(null);

        $request = new Request();
        $request->request = new InputBag(['project' => 7]);

        $this->expectException(NotFoundHttpException::class);

        $this->createController($this->createStub(PlannedActivityRepository::class))->confirmTentativeAction($request, $projects, $this->createStub(TentativeConfirmer::class));
    }

    public function testConfirmTentativeReturnsConfirmedAndSkippedIds(): void
    {
        $project = new Project();
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($project);

        $ref = new \ReflectionProperty(PlannedActivity::class, 'id');
        $mine = (new PlannedActivity())->setTentative(true);
        $ref->setValue($mine, 3);
        $theirs = (new PlannedActivity())->setTentative(true);
        $ref->setValue($theirs, 4);

        $repository = $this->createMock(PlannedActivityRepository::class);
        $repository->method('findTentativeForProject')->with($project)->willReturn([$mine, $theirs]);
        $repository->expects($this->once())->method('confirmPlannedActivities')->with([$mine]);

        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturnCallback(static fn (string $attribute, mixed $subject): bool => $subject === $mine);
        $confirmer = new TentativeConfirmer($repository, $authChecker);

        $viewHandler = $this->createMock(ViewHandlerInterface::class);
        $viewHandler->expects($this->once())->method('handle')
            ->with($this->callback(static fn (View $view): bool => $view->getData() === ['confirmed' => [3], 'skipped' => [4]]))
            ->willReturn(new Response());

        $request = new Request();
        $request->request = new InputBag(['project' => '7']);

        $this->createController($repository, $viewHandler)->confirmTentativeAction($request, $projects, $confirmer);
    }
}
