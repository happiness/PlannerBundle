<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityEditForm;
use KimaiPlugin\PlannerBundle\Form\Toolbar\PlannerToolbarForm;
use KimaiPlugin\PlannerBundle\Planner\CommonProjectService;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Planner\WorkingDayRanges;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/planner')]
#[IsGranted('view_planner')]
final class WeeklyPlannerController extends AbstractController
{
    public const DEFAULT_WEEKS = 4;
    public const WEEK_OPTIONS = [1, 2, 4, 8];
    private const SESSION_KEY = 'planner_weeks';
    private const MOVE_TOKEN = 'planner_move';

    public function __construct(
        private readonly PlannedActivityRepository $repository,
        private readonly WeeklyPlannerService $plannerService,
        private readonly UserRepository $userRepository,
        private readonly PlannerUserProvider $userProvider
    ) {
    }

    #[Route(path: '', name: 'planner', methods: ['GET'])]
    public function index(?Request $request = null): Response
    {
        $request ??= new Request();

        return $this->renderWeek((new \DateTimeImmutable('today'))->format('Y-m-d'), $request, $this->resolveWeeks($request));
    }

    #[Route(path: '/week/{date}', name: 'planner_week', methods: ['GET'])]
    public function week(string $date, ?Request $request = null): Response
    {
        $request ??= new Request();

        return $this->renderWeek($date, $request, $this->resolveWeeks($request));
    }

    /**
     * Frequently used customer/project pairs of a user, one per project.
     */
    #[Route(path: '/combinations/{user}', name: 'planner_combinations', requirements: ['user' => '\d+'], methods: ['GET'])]
    public function combinations(int $user, CommonProjectService $commonProjects): JsonResponse
    {
        $currentUser = $this->getUser();
        $target = $currentUser;

        if ($user > 0 && $user !== $currentUser->getId()) {
            if (!$this->isGranted('edit_other_planner') && !$this->isGranted('view_all_data')) {
                throw $this->createAccessDeniedException();
            }
            $target = $this->userRepository->find($user);
            if (!$target instanceof User) {
                throw $this->createNotFoundException();
            }
        }

        $result = [];
        foreach ($commonProjects->getProjects($target) as $project) {
            $customer = $project->getCustomer();
            if ($customer === null) {
                continue;
            }
            $result[] = [
                'customerId' => $customer->getId(),
                'customerName' => $customer->getName(),
                'projectId' => $project->getId(),
                'projectName' => $project->getName(),
            ];
        }

        return new JsonResponse($result);
    }

    #[Route(path: '/activity/create', name: 'planner_activity_create', methods: ['GET', 'POST'])]
    public function createActivity(Request $request, WorkingDayRanges $workingDays): Response
    {
        $currentUser = $this->getUser();
        $targetUserId = $request->query->getInt('user', 0);
        $targetUser = $currentUser;

        if ($targetUserId > 0 && $targetUserId !== $currentUser->getId()) {
            $foundUser = $this->userRepository->find($targetUserId);
            if ($foundUser instanceof User) {
                $targetUser = $foundUser;
            }
        }

        $activity = new PlannedActivity();
        $activity->setUser($targetUser);

        $dateParam = $request->query->getString('date');
        if ($dateParam !== '') {
            try {
                $begin = new \DateTime($dateParam);
                $activity->setBegin($begin);
                $activity->setEnd($begin);
            } catch (\Exception) {
                // Keep default today
            }
        }

        // a drag over several days in the planner passes the last day
        $endParam = $request->query->getString('end');
        if ($endParam !== '') {
            try {
                $end = new \DateTime($endParam);
                if ($end >= $activity->getBegin()) {
                    $activity->setEnd($end);
                }
            } catch (\Exception) {
                // Keep the single day
            }
        }

        $this->prefillCustomerAndProject($activity, $request);

        if (!$this->isGranted(PlannedActivityVoter::CREATE, $activity)) {
            throw $this->createAccessDeniedException();
        }

        $canEditOther = $this->isGranted('edit_other_planner') || $this->isGranted('view_all_data');

        $form = $this->createForm(PlannedActivityEditForm::class, $activity, [
            'action' => $this->generateUrl('planner_activity_create', [
                'user' => $targetUser->getId(),
                'date' => $activity->getBegin()?->format('Y-m-d'),
            ]),
            'method' => 'POST',
            'user' => $currentUser,
            'include_user' => $canEditOther,
            'include_recurrence' => true,
            'skip_days_off' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // the week the user planned in, also when its first days were days off
                $selectedDate = $activity->getBegin() ? $activity->getBegin()->format('Y-m-d') : (new \DateTime('today'))->format('Y-m-d');

                // a range over days the user does not work becomes one activity per run of working days
                $activities = $this->repository->savePlannedActivityInRanges($activity, $workingDays->forActivity($activity));

                if ($form->has('recurrentWeeks')) {
                    $recurrentWeeksData = $form->get('recurrentWeeks')->getData();
                    if (\is_int($recurrentWeeksData) && $recurrentWeeksData > 0) {
                        $this->repository->createRecurringActivitiesForAll($activities, $recurrentWeeksData);
                    }
                }

                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('planner_week', ['date' => $selectedDate]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('@Planner/form.html.twig', [
            'form' => $form->createView(),
            'activity' => $activity,
            'title' => 'planner.create_entry',
        ]);
    }

    #[Route(path: '/activity/{id}/edit', name: 'planner_activity_edit', methods: ['GET', 'POST'])]
    public function editActivity(PlannedActivity $activity, Request $request, WorkingDayRanges $workingDays): Response
    {
        if (!$this->isGranted(PlannedActivityVoter::EDIT, $activity)) {
            throw $this->createAccessDeniedException();
        }

        $currentUser = $this->getUser();
        $canEditOther = $this->isGranted('edit_other_planner') || $this->isGranted('view_all_data');

        $dateParam = $request->query->getString('date');
        $formActionParams = ['id' => $activity->getId()];
        if ($dateParam !== '') {
            $formActionParams['date'] = $dateParam;
        }

        $form = $this->createForm(PlannedActivityEditForm::class, $activity, [
            'action' => $this->generateUrl('planner_activity_edit', $formActionParams),
            'method' => 'POST',
            'user' => $currentUser,
            'include_user' => $canEditOther,
            'include_recurrence_mode' => $activity->isRecurring(),
            // "all future" keeps the dates as entered, so only say it where every save splits
            'skip_days_off' => !$activity->isRecurring(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $selectedDate = $activity->getBegin() ? $activity->getBegin()->format('Y-m-d') : (new \DateTime('today'))->format('Y-m-d');
                $future = $form->has('edit_mode') && $activity->isRecurring() && $form->get('edit_mode')->getData() === 'future';

                if ($future) {
                    // the following activities take over this one as it is, so its dates stay as entered
                    $this->repository->savePlannedActivity($activity);
                    $this->repository->updateFutureRecurringActivities($activity);
                } else {
                    // new dates over days the user does not work become one activity per run of working days
                    $this->repository->savePlannedActivityInRanges($activity, $workingDays->forActivity($activity));
                }

                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('planner_week', ['date' => $selectedDate]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('@Planner/form.html.twig', [
            'form' => $form->createView(),
            'activity' => $activity,
            'title' => 'planner.edit_entry',
            'date' => $dateParam,
        ]);
    }

    /**
     * Moves (drag and drop) or resizes an activity in the grid: new dates and, when dropped on another row,
     * a new user and customer/project. The hours per day stay as they are, so resizing repeats them on the added days.
     * Days the user does not work are left out, so stretching Thursday to Monday gives Thursday to Friday and Monday.
     */
    #[Route(path: '/activity/{id}/move', name: 'planner_activity_move', methods: ['POST'])]
    public function moveActivity(PlannedActivity $activity, Request $request, ValidatorInterface $validator, WorkingDayRanges $workingDays): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::MOVE_TOKEN, $request->request->getString('_token'))) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        if (!$this->canEdit($activity)) {
            throw $this->createAccessDeniedException();
        }

        try {
            $begin = new \DateTime($request->request->getString('begin'));
            $end = new \DateTime($request->request->getString('end'));
        } catch (\Exception) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $activity->setBegin($begin);
        $activity->setEnd($end);

        $userId = $request->request->getInt('user');
        if ($userId > 0 && $userId !== $activity->getUser()?->getId()) {
            $user = $this->userRepository->find($userId);
            if (!$user instanceof User) {
                throw $this->createNotFoundException();
            }
            $activity->setUser($user);
        }

        $customerId = $request->request->getInt('customer');
        if ($customerId > 0) {
            $customer = $this->repository->findCustomer($customerId);
            $projectId = $request->request->getInt('project');
            $project = $projectId > 0 ? $this->repository->findProject($projectId) : null;

            if (!$customer instanceof Customer || !$this->isGranted('access', $customer)
                || ($projectId > 0 && (!$project instanceof Project || !$this->isGranted('access', $project)))) {
                throw $this->createAccessDeniedException();
            }

            $activity->setCustomer($customer);
            $activity->setProject($project);
        }

        // the voter looks at the (possibly new) owner, so a user may not move planning to somebody they cannot plan for
        if (!$this->canEdit($activity)) {
            throw $this->createAccessDeniedException();
        }

        $violations = $validator->validate($activity);
        if (\count($violations) > 0) {
            return new JsonResponse(['message' => $violations->get(0)->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // stretched or moved over days the user does not work: one activity per run of working days
            $this->repository->savePlannedActivityInRanges($activity, $workingDays->forActivity($activity));
        } catch (\Exception $ex) {
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['id' => $activity->getId()]);
    }

    #[Route(path: '/activity/{id}/delete', name: 'planner_activity_delete', methods: ['GET', 'POST'])]
    public function deleteActivity(PlannedActivity $activity, Request $request): Response
    {
        if (!$this->isGranted(PlannedActivityVoter::DELETE, $activity)) {
            throw $this->createAccessDeniedException();
        }

        $dateParam = $request->query->getString('date');
        if ($dateParam === '') {
            $dateParam = $request->request->getString('date');
        }

        $selectedDate = $activity->getBegin() ? $activity->getBegin()->format('Y-m-d') : (new \DateTime('today'))->format('Y-m-d');
        if ($dateParam !== '') {
            try {
                $selectedDate = (new \DateTimeImmutable($dateParam))->format('Y-m-d');
            } catch (\Exception) {
                // Keep activity begin date
            }
        }

        if ($activity->isRecurring()) {
            $deleteActionParams = ['id' => $activity->getId()];
            if ($dateParam !== '') {
                $deleteActionParams['date'] = $dateParam;
            }

            $deleteForm = $this->createFormBuilder(null, [
                'attr' => [
                    'data-form-event' => 'kimai.plannerActivityUpdate',
                    'data-msg-success' => 'action.delete.success',
                    'data-msg-error' => 'action.delete.error',
                ],
            ])
                ->add('delete_mode', ChoiceType::class, [
                    'label' => false,
                    'expanded' => true,
                    'multiple' => false,
                    'choices' => [
                        'planner.delete_single' => 'single',
                        'planner.delete_future' => 'future',
                    ],
                    'data' => 'single',
                ])
                ->setAction($this->generateUrl('planner_activity_delete', $deleteActionParams))
                ->setMethod('POST')
                ->getForm();

            $deleteForm->handleRequest($request);

            if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
                try {
                    $mode = $deleteForm->get('delete_mode')->getData();
                    if (($mode === 'future' || $mode === 'all') && $activity->getRecurrenceGroup() !== null) {
                        $this->repository->deleteFutureRecurringActivities($activity);
                    } else {
                        $this->repository->deletePlannedActivity($activity);
                    }
                    $this->flashSuccess('action.delete.success');

                    return $this->redirectToRoute('planner_week', ['date' => $selectedDate]);
                } catch (\Exception $ex) {
                    $this->flashDeleteException($ex);

                    return $this->redirectToRoute('planner');
                }
            }

            return $this->render('@Planner/delete.html.twig', [
                'activity' => $activity,
                'form' => $deleteForm->createView(),
                'date' => $dateParam,
            ]);
        }

        $token = $request->query->get('token') ?? $request->getPayload()->getString('token');
        if (!$this->isCsrfTokenValid('planner_delete_' . (string) $activity->getId(), $token)) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('planner_week', ['date' => $selectedDate]);
        }

        try {
            $this->repository->deletePlannedActivity($activity);
            $this->flashSuccess('action.delete.success');

            return $this->redirectToRoute('planner_week', ['date' => $selectedDate]);
        } catch (\Exception $ex) {
            $this->flashDeleteException($ex);

            return $this->redirectToRoute('planner');
        }
    }

    /**
     * @phpstan-impure the voter looks at the activity, which is changed between two checks
     */
    private function canEdit(PlannedActivity $activity): bool
    {
        return $this->isGranted(PlannedActivityVoter::EDIT, $activity);
    }

    /**
     * Pre-selects customer/project (e.g. from the row a day was dragged in), ignoring anything the user may not access.
     */
    private function prefillCustomerAndProject(PlannedActivity $activity, Request $request): void
    {
        $projectId = $request->query->getInt('project');
        if ($projectId > 0) {
            $project = $this->repository->findProject($projectId);
            if ($project instanceof Project && $this->isGranted('access', $project)) {
                $activity->setProject($project);
                $activity->setCustomer($project->getCustomer());
            }
        }

        $customerId = $request->query->getInt('customer');
        if ($activity->getCustomer() === null && $customerId > 0) {
            $customer = $this->repository->findCustomer($customerId);
            if ($customer instanceof Customer && $this->isGranted('access', $customer)) {
                $activity->setCustomer($customer);
            }
        }
    }

    /**
     * The number of weeks comes from the "weeks" query parameter and is remembered in the session,
     * so it survives navigation and the redirects after saving an activity.
     */
    private function resolveWeeks(?Request $request): int
    {
        if ($request === null) {
            return self::DEFAULT_WEEKS;
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        $requested = $request->query->getInt('weeks');
        if (\in_array($requested, self::WEEK_OPTIONS, true)) {
            $session?->set(self::SESSION_KEY, $requested);

            return $requested;
        }

        $stored = $session?->get(self::SESSION_KEY);

        return \is_int($stored) && \in_array($stored, self::WEEK_OPTIONS, true) ? $stored : self::DEFAULT_WEEKS;
    }

    private function renderWeek(string $dateString, Request $request, int $weeks): Response
    {
        try {
            $selectedDate = new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            $selectedDate = new \DateTimeImmutable('today');
        }

        $currentUser = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($currentUser);

        $query = new PlannerQuery();
        $query->setCurrentUser($currentUser);

        if (\count($currentUser->getTeams()) === 1) {
            $query->setTeams($currentUser->getTeams());
        }

        $form = $this->createSearchForm(PlannerToolbarForm::class, $query, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
        ]);

        if ($this->handleSearch($form, $request)) {
            return $this->redirectToRoute('planner_week', ['date' => $selectedDate->format('Y-m-d')]);
        }

        /** @var PlannerQuery $query */
        $query = $form->getData();

        $users = $this->userProvider->getVisibleUsers($currentUser, $query);

        $plannerData = $this->plannerService->getPlannerData($users, $selectedDate, $weeks);

        $page = new PageSetup('planner.title');
        $page->setHelp('planner.html');
        $page->setPaginationForm($form);

        $currentWeekMonday = $selectedDate->modify('this week monday 00:00:00');
        $prevWeek = $currentWeekMonday->modify('-1 week')->format('Y-m-d');
        $nextWeek = $currentWeekMonday->modify('+1 week')->format('Y-m-d');
        $todayWeek = (new \DateTimeImmutable('today'))->modify('this week monday')->format('Y-m-d');

        return $this->render('@Planner/index.html.twig', [
            'page_setup' => $page,
            'toolbarForm' => $form->createView(),
            'query' => $query,
            'plannerData' => $plannerData,
            'selectedDate' => $selectedDate,
            'currentWeek' => $currentWeekMonday,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'todayWeek' => $todayWeek,
            'weeks' => $weeks,
            'weekOptions' => self::WEEK_OPTIONS,
            'canCreate' => $this->isGranted('create_planner'),
        ]);
    }
}
