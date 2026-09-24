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
use App\Entity\Team;
use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\PlannerBundle\Entity\PlannedActivity;
use KimaiPlugin\PlannerBundle\Form\PlannedActivityEditForm;
use KimaiPlugin\PlannerBundle\Form\Toolbar\PlannerToolbarForm;
use KimaiPlugin\PlannerBundle\Planner\WeeklyPlannerService;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use KimaiPlugin\PlannerBundle\Repository\Query\PlannerQuery;
use KimaiPlugin\PlannerBundle\Voter\PlannedActivityVoter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/planner')]
#[IsGranted('view_planner')]
final class WeeklyPlannerController extends AbstractController
{
    public function __construct(
        private readonly PlannedActivityRepository $repository,
        private readonly WeeklyPlannerService $plannerService,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route(path: '', name: 'planner', methods: ['GET'])]
    public function index(Request $request = new Request()): Response
    {
        return $this->renderWeek((new \DateTimeImmutable('today'))->format('Y-m-d'), $request);
    }

    #[Route(path: '/week/{date}', name: 'planner_week', methods: ['GET'])]
    public function week(string $date, Request $request = new Request()): Response
    {
        return $this->renderWeek($date, $request);
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

        $form = $this->createForm(PlannedActivityEditForm::class, $activity, [
            'action' => $this->generateUrl('planner_activity_edit', ['id' => $activity->getId()]),
            'method' => 'POST',
            'user' => $currentUser,
            'include_user' => $canEditOther,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->savePlannedActivity($activity);
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
        ]);
    }

    #[Route(path: '/activity/{id}/delete', name: 'planner_activity_delete', methods: ['GET', 'POST'])]
    public function deleteActivity(PlannedActivity $activity, Request $request): Response
    {
        if (!$this->isGranted(PlannedActivityVoter::DELETE, $activity)) {
            throw $this->createAccessDeniedException();
        }

        $selectedDate = $activity->getBegin() ? $activity->getBegin()->format('Y-m-d') : (new \DateTime('today'))->format('Y-m-d');

        if ($activity->isRecurring()) {
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
                        'planner.delete_all' => 'all',
                    ],
                    'data' => 'single',
                ])
                ->setAction($this->generateUrl('planner_activity_delete', ['id' => $activity->getId()]))
                ->setMethod('POST')
                ->getForm();

            $deleteForm->handleRequest($request);

            if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
                try {
                    $mode = $deleteForm->get('delete_mode')->getData();
                    if ($mode === 'all' && $activity->getRecurrenceGroup() !== null) {
                        $this->repository->deleteRecurringActivities($activity->getRecurrenceGroup());
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

    private function renderWeek(string $dateString, Request $request): Response
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

        $users = $this->getVisibleUsers($currentUser, $query);

        $plannerData = $this->plannerService->getPlannerData($users, $selectedDate);

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
            'canCreate' => $this->isGranted('create_planner'),
        ]);
    }

    /**
     * @return array<User>
     */
    private function getVisibleUsers(User $currentUser, PlannerQuery $plannerQuery): array
    {
        $canSeeOther = $this->isGranted('view_other_planner') || $this->isGranted('view_all_data');

        if (!$canSeeOther) {
            return [$currentUser];
        }

        $query = new UserQuery();
        $query->setSystemAccount(false);
        $query->setCurrentUser($currentUser);
        $query->setOrder(UserQuery::ORDER_ASC);
        $query->setOrderBy('username');

        $hasViewAllData = $this->isGranted('view_all_data');

        if (!$hasViewAllData) {
            /** @var Team[] $ledTeams */
            $ledTeams = array_values(array_filter($currentUser->getTeams(), fn (Team $team) => $currentUser->isTeamleadOf($team)));
            if ($ledTeams === []) {
                return [];
            }
            $query->setSearchTeams($ledTeams);
        }

        if ($plannerQuery->hasTeams()) {
            $selectedTeams = $plannerQuery->getTeams();
            if (!$hasViewAllData) {
                /** @var Team[] $ledTeams */
                $ledTeams = array_values(array_filter($currentUser->getTeams(), fn (Team $team) => $currentUser->isTeamleadOf($team)));
                $ledTeamIds = array_map(fn (Team $t) => $t->getId(), $ledTeams);
                $selectedTeams = array_values(array_filter($selectedTeams, fn (Team $t) => \in_array($t->getId(), $ledTeamIds, true)));
                if ($selectedTeams === []) {
                    return [];
                }
            }
            $query->setSearchTeams($selectedTeams);
        }

        $users = $this->userRepository->getUsersForQuery($query);

        if ($plannerQuery->hasUsers()) {
            $selectedUserIds = array_map(fn (User $u) => $u->getId(), $plannerQuery->getUsers());
            $users = array_values(array_filter($users, fn (User $u) => \in_array($u->getId(), $selectedUserIds, true)));
        }

        return $users;
    }
}
