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
use KimaiPlugin\PlannerBundle\Entity\Reservation;
use KimaiPlugin\PlannerBundle\Form\ReservationForm;
use KimaiPlugin\PlannerBundle\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration > Reserved time: the list of reservations the planner offers, with name, colour and order. One that
 * is planned can only be archived, which stops offering it while its plannings keep counting.
 */
#[Route(path: '/admin/planner/reserved-time')]
#[IsGranted('manage_reservations_planner')]
final class ReservationController extends AbstractController
{
    public const CSRF_TOKEN = 'planner_reservation_action';

    public function __construct(private readonly ReservationRepository $repository)
    {
    }

    #[Route(path: '', name: 'planner_reservations', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@Planner/reservation/index.html.twig', [
            'reservations' => $this->repository->findAllOrdered(),
            'plannings' => $this->repository->countPlannings(),
            'token' => self::CSRF_TOKEN,
        ]);
    }

    #[Route(path: '/create', name: 'planner_reservation_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->edit(new Reservation(), $request);
    }

    #[Route(path: '/{id}/edit', name: 'planner_reservation_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Reservation $reservation, Request $request): Response
    {
        $isNew = $reservation->getId() === null;
        $form = $this->createForm(ReservationForm::class, $reservation, [
            'action' => $isNew
                ? $this->generateUrl('planner_reservation_create')
                : $this->generateUrl('planner_reservation_edit', ['id' => $reservation->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew ? $this->repository->create($reservation) : $this->repository->save($reservation);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('planner_reservations');
        }

        return $this->render('@Planner/reservation/edit.html.twig', [
            'form' => $form->createView(),
            'reservation' => $reservation,
        ]);
    }

    /**
     * Archives or restores: archived, it is no longer offered, while its plannings stay and keep counting.
     */
    #[Route(path: '/{id}/archive', name: 'planner_reservation_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Reservation $reservation, Request $request): Response
    {
        if ($this->isValidToken($request)) {
            $reservation->setArchived(!$reservation->isArchived());
            $this->repository->save($reservation);
            $this->flashSuccess('action.update.success');
        }

        return $this->redirectToRoute('planner_reservations');
    }

    /**
     * Only while nothing is planned on it; the foreign key would refuse anyway, archiving is for the rest.
     */
    #[Route(path: '/{id}/delete', name: 'planner_reservation_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Reservation $reservation, Request $request): Response
    {
        if ($this->isValidToken($request)) {
            if (($this->repository->countPlannings()[(int) $reservation->getId()] ?? 0) > 0) {
                $this->flashError('planner.reservation.delete_in_use');
            } else {
                $this->repository->delete($reservation);
                $this->flashSuccess('action.delete.success');
            }
        }

        return $this->redirectToRoute('planner_reservations');
    }

    #[Route(path: '/{id}/move/{direction}', name: 'planner_reservation_move', requirements: ['id' => '\d+', 'direction' => 'up|down'], methods: ['POST'])]
    public function move(Reservation $reservation, string $direction, Request $request): Response
    {
        if ($this->isValidToken($request)) {
            $this->repository->move($reservation, $direction === 'up' ? -1 : 1);
        }

        return $this->redirectToRoute('planner_reservations');
    }

    private function isValidToken(Request $request): bool
    {
        if ($this->isCsrfTokenValid(self::CSRF_TOKEN, $request->request->getString('_token'))) {
            return true;
        }
        $this->flashError('action.csrf.error');

        return false;
    }
}
