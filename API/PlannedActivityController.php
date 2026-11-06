<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\API;

use App\API\BaseApiController;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityApiForm;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints;

#[Route(path: '/planner/activities')]
#[IsGranted('API')]
#[IsGranted('view_planner')]
#[OA\Tag(name: 'Planner')]
final class PlannedActivityController extends BaseApiController
{
    public const GROUPS_ENTITY = ['Default'];

    public function __construct(
        private readonly ViewHandlerInterface $viewHandler,
        private readonly PlannedActivityRepository $repository,
        private readonly PlannerUserProvider $userProvider
    ) {
    }

    /**
     * Fetch planned activities
     */
    #[OA\Response(response: 200, description: 'Returns a collection of planned activities', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: PlannedActivity::class, groups: self::GROUPS_ENTITY))))]
    #[Route(methods: ['GET'], path: '', name: 'get_planned_activities')]
    #[Rest\QueryParam(name: 'user', requirements: '\d+', strict: true, nullable: true, description: 'User ID to filter (default: all users you are allowed to see)')]
    #[Rest\QueryParam(name: 'customers', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of customer IDs to filter, e.g.: customers[]=1&customers[]=2')]
    #[Rest\QueryParam(name: 'projects', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of project IDs to filter, e.g.: projects[]=1&projects[]=2')]
    #[Rest\QueryParam(name: 'begin', requirements: [new Constraints\DateTime(format: 'Y-m-d', message: 'This value is not a valid date, expected format: Y-m-d (2026-10-19).')], strict: true, nullable: true, description: 'Only activities ending on or after this day are returned. Format: Y-m-d')]
    #[Rest\QueryParam(name: 'end', requirements: [new Constraints\DateTime(format: 'Y-m-d', message: 'This value is not a valid date, expected format: Y-m-d (2026-10-19).')], strict: true, nullable: true, description: 'Only activities beginning on or before this day are returned. Format: Y-m-d')]
    public function cgetAction(ParamFetcherInterface $paramFetcher, CustomerRepository $customerRepository, ProjectRepository $projectRepository): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $users = $this->userProvider->getVisibleUsers($currentUser);

        $userId = $paramFetcher->get('user');
        if (is_numeric($userId)) {
            $users = array_values(array_filter($users, static fn (User $user): bool => $user->getId() === (int) $userId));
        }

        /** @var array<int|string> $customerIds */
        $customerIds = $paramFetcher->get('customers');
        $customers = $customerRepository->findByIds(array_unique($customerIds));
        foreach ($customers as $customer) {
            if (!$this->isGranted('access', $customer)) {
                throw $this->createAccessDeniedException('Cannot access Customer: ' . $customer->getId());
            }
        }

        /** @var array<int|string> $projectIds */
        $projectIds = $paramFetcher->get('projects');
        $projects = $projectRepository->findByIds(array_unique($projectIds));
        foreach ($projects as $project) {
            if (!$this->isGranted('access', $project)) {
                throw $this->createAccessDeniedException('Cannot access Project: ' . $project->getId());
            }
        }

        $begin = $paramFetcher->get('begin');
        $end = $paramFetcher->get('end');

        $activities = $this->repository->findByFilter(
            $users,
            \is_string($begin) && $begin !== '' ? new \DateTimeImmutable($begin) : null,
            \is_string($end) && $end !== '' ? new \DateTimeImmutable($end) : null,
            $customers,
            $projects
        );

        $view = new View($activities, Response::HTTP_OK);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Fetch a planned activity
     */
    #[IsGranted('view', 'activity')]
    #[OA\Response(response: 200, description: 'Returns one planned activity', content: new OA\JsonContent(ref: new Model(type: PlannedActivity::class, groups: self::GROUPS_ENTITY)))]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Planned activity ID', required: true)]
    #[Route(methods: ['GET'], path: '/{id}', name: 'get_planned_activity', requirements: ['id' => '\d+'])]
    public function getAction(PlannedActivity $activity): Response
    {
        $view = new View($activity, Response::HTTP_OK);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Create a planned activity
     */
    #[IsGranted('create_planner')]
    #[OA\Post(description: 'Creates a new planned activity and returns it. Pass "recurrentWeeks" to also create copies for the following weeks. "user" defaults to the current user.', responses: [new OA\Response(response: 200, description: 'Returns the new planned activity', content: new OA\JsonContent(ref: new Model(type: PlannedActivity::class, groups: self::GROUPS_ENTITY)))])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: PlannedActivityApiForm::class)))]
    #[Route(methods: ['POST'], path: '', name: 'post_planned_activity')]
    public function postAction(Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $activity = new PlannedActivity();
        $activity->setUser($currentUser);

        $form = $this->createForm(PlannedActivityApiForm::class, $activity, [
            'user' => $currentUser,
            'include_user' => true,
            'include_recurrence' => true,
        ]);

        $form->submit($request->request->all(), false);

        if (!$form->isValid()) {
            return $this->handleForm($form);
        }

        $this->assertAccess(PlannedActivityVoter::CREATE, $activity);

        $this->repository->savePlannedActivity($activity);

        $weeks = $form->get('recurrentWeeks')->getData();
        if (\is_int($weeks) && $weeks > 0) {
            $this->repository->createRecurringActivities($activity, $weeks);
        }

        $view = new View($activity, Response::HTTP_OK);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Update a planned activity
     */
    #[IsGranted('edit', 'activity')]
    #[OA\Patch(description: 'Update an existing planned activity, you can pass all or just a subset of attributes. Pass "edit_mode": "future" to apply the change to all following activities of a recurring series (default: "single").', responses: [new OA\Response(response: 200, description: 'Returns the updated planned activity', content: new OA\JsonContent(ref: new Model(type: PlannedActivity::class, groups: self::GROUPS_ENTITY)))])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: PlannedActivityApiForm::class)))]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Planned activity ID to update', required: true)]
    #[Route(methods: ['PATCH'], path: '/{id}', name: 'patch_planned_activity', requirements: ['id' => '\d+'])]
    public function patchAction(Request $request, PlannedActivity $activity): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = $request->request->all();
        // Kimai's API turns snake_case JSON keys into camelCase before they get here (edit_mode arrives as editMode),
        // and the form rejects either as an extra field
        $editMode = $data['editMode'] ?? $data['edit_mode'] ?? 'single';
        unset($data['editMode'], $data['edit_mode']);
        if (!\in_array($editMode, ['single', 'future'], true)) {
            throw new BadRequestHttpException('Invalid edit_mode, allowed values: single, future');
        }

        $previousUser = $activity->getUser();

        $form = $this->createForm(PlannedActivityApiForm::class, $activity, [
            'user' => $currentUser,
            'include_user' => true,
        ]);

        $form->submit($data, false);

        if (!$form->isValid()) {
            return $this->handleForm($form);
        }

        // moving an activity to another user requires permission for both the old and the new owner
        if ($activity->getUser() !== $previousUser) {
            $this->assertAccess(PlannedActivityVoter::EDIT, $activity);
        }

        $this->repository->savePlannedActivity($activity);

        if ($editMode === 'future' && $activity->isRecurring()) {
            $this->repository->updateFutureRecurringActivities($activity);
        }

        $view = new View($activity, Response::HTTP_OK);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    /**
     * Delete a planned activity
     */
    #[IsGranted('delete', 'activity')]
    #[OA\Delete(responses: [new OA\Response(response: 204, description: 'Empty')])]
    #[OA\Parameter(name: 'id', in: 'path', description: 'Planned activity ID to delete', required: true)]
    #[OA\Parameter(name: 'mode', in: 'query', description: 'For recurring activities: "single" deletes only this one (default), "future" also deletes all following ones', required: false, schema: new OA\Schema(type: 'string', enum: ['single', 'future']))]
    #[Route(methods: ['DELETE'], path: '/{id}', name: 'delete_planned_activity', requirements: ['id' => '\d+'])]
    public function deleteAction(Request $request, PlannedActivity $activity): Response
    {
        $mode = $request->query->getString('mode', 'single');
        if (!\in_array($mode, ['single', 'future'], true)) {
            throw new BadRequestHttpException('Invalid mode, allowed values: single, future');
        }

        if ($mode === 'future' && $activity->isRecurring()) {
            $this->repository->deleteFutureRecurringActivities($activity);
        } else {
            $this->repository->deletePlannedActivity($activity);
        }

        return $this->viewHandler->handle(new View(null, Response::HTTP_NO_CONTENT));
    }

    private function assertAccess(string $attribute, PlannedActivity $activity): void
    {
        if (!$this->isGranted($attribute, $activity)) {
            throw $this->createAccessDeniedException('Not allowed to ' . $attribute . ' planned activities for this user');
        }
    }

    private function handleForm(FormInterface $form): Response
    {
        $view = new View($form, Response::HTTP_OK);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }
}
