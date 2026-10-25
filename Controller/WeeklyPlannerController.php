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
use KimaiPlugin\PlannerBundle\Planner\CommonProjectService;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/planner')]
#[IsGranted('view_planner')]
final class WeeklyPlannerController extends AbstractController
{
    public const DEFAULT_WEEKS = 4;
    public const WEEK_OPTIONS = [1, 2, 4, 8];
    private const SESSION_KEY = 'planner_weeks';

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
        return $this->renderWeek((new \DateTimeImmutable('today'))->format('Y-m-d'), $this->resolveWeeks($request));
    }

    #[Route(path: '/week/{date}', name: 'planner_week', methods: ['GET'])]
    public function week(string $date, ?Request $request = null): Response
    {
        return $this->renderWeek($date, $this->resolveWeeks($request));
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
    public function createActivity(Request $request): Response
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
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->savePlannedActivity($activity);

                if ($form->has('recurrentWeeks')) {
                    $recurrentWeeksData = $form->get('recurrentWeeks')->getData();
                    if (\is_int($recurrentWeeksData) && $recurrentWeeksData > 0) {
                        $this->repository->createRecurringActivities($activity, $recurrentWeeksData);
                    }
                }

                $this->flashSuccess('action.update.success');

                $selectedDate = $activity->getBegin() ? $activity->getBegin()->format('Y-m-d') : (new \DateTime('today'))->format('Y-m-d');

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
    public function editActivity(PlannedActivity $activity, Request $request): Response
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
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->savePlannedActivity($activity);

                if ($form->has('edit_mode') && $activity->isRecurring()) {
                    $editMode = $form->get('edit_mode')->getData();
                    if ($editMode === 'future') {
                        $this->repository->updateFutureRecurringActivities($activity);
                    }
                }

                $this->flashSuccess('action.update.success');

                $selectedDate = $activity->getBegin() ? $activity->getBegin()->format('Y-m-d') : (new \DateTime('today'))->format('Y-m-d');

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

    private function renderWeek(string $dateString, int $weeks): Response
    {
        try {
            $selectedDate = new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            $selectedDate = new \DateTimeImmutable('today');
        }

        $currentUser = $this->getUser();
        $users = $this->userProvider->getVisibleUsers($currentUser);

        $plannerData = $this->plannerService->getPlannerData($users, $selectedDate, $weeks);

        $page = new PageSetup('planner.title');
        $page->setHelp('planner.html');

        $currentWeekMonday = $selectedDate->modify('this week monday 00:00:00');
        $prevWeek = $currentWeekMonday->modify('-1 week')->format('Y-m-d');
        $nextWeek = $currentWeekMonday->modify('+1 week')->format('Y-m-d');
        $todayWeek = (new \DateTimeImmutable('today'))->modify('this week monday')->format('Y-m-d');

        return $this->render('@Planner/index.html.twig', [
            'page_setup' => $page,
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
