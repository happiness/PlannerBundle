<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Controller;

use KimaiPlugin\PlannerBundle\Controller\ReservationController;
use KimaiPlugin\PlannerBundle\Entity\Reservation;
use KimaiPlugin\PlannerBundle\Repository\ReservationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReservationControllerTest extends TestCase
{
    private function createController(ReservationRepository $repository, bool $validCsrf = true): ReservationController
    {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => $validCsrf && $token->getId() === ReservationController::CSRF_TOKEN);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => '/' . $route);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $services = [
            'security.csrf.token_manager' => $csrf,
            'router' => $router,
            'request_stack' => $requestStack,
            'translator' => $translator,
        ];
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => \array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(static fn (string $id): ?object => $services[$id] ?? null);

        $controller = new ReservationController($repository);
        $controller->setContainer($container);

        return $controller;
    }

    private function createReservation(int $id, string $name): Reservation
    {
        $reservation = (new Reservation())->setName($name);
        (new \ReflectionProperty(Reservation::class, 'id'))->setValue($reservation, $id);

        return $reservation;
    }

    private function post(): Request
    {
        return new Request([], ['_token' => 'valid']);
    }

    public function testAPlannedReservationCannotBeDeleted(): void
    {
        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('countPlannings')->willReturn([3 => 12]);
        $repository->expects($this->never())->method('delete');

        $response = $this->createController($repository)->delete($this->createReservation(3, 'Agency buffer'), $this->post());

        $this->assertSame('/planner_reservations', $response->headers->get('Location'));
    }

    public function testAnUnplannedReservationIsDeleted(): void
    {
        $reservation = $this->createReservation(4, 'Spare');
        $repository = $this->createMock(ReservationRepository::class);
        $repository->method('countPlannings')->willReturn([3 => 12]);
        $repository->expects($this->once())->method('delete')->with($reservation);

        $this->createController($repository)->delete($reservation, $this->post());
    }

    public function testArchiveToggles(): void
    {
        $reservation = $this->createReservation(3, 'Agency buffer');
        $repository = $this->createMock(ReservationRepository::class);
        $repository->expects($this->exactly(2))->method('save')->with($reservation);
        $controller = $this->createController($repository);

        $controller->archive($reservation, $this->post());
        $this->assertTrue($reservation->isArchived());

        $controller->archive($reservation, $this->post());
        $this->assertFalse($reservation->isArchived());
    }

    public function testInvalidCsrfChangesNothing(): void
    {
        $reservation = $this->createReservation(4, 'Spare');
        $repository = $this->createMock(ReservationRepository::class);
        $repository->expects($this->never())->method('delete');
        $repository->expects($this->never())->method('save');
        $repository->expects($this->never())->method('move');
        $controller = $this->createController($repository, false);

        $controller->delete($reservation, $this->post());
        $controller->archive($reservation, $this->post());
        $controller->move($reservation, 'up', $this->post());
        $this->assertFalse($reservation->isArchived());
    }

    public function testRequiresTheManagePermission(): void
    {
        $attributes = (new \ReflectionClass(ReservationController::class))->getAttributes(IsGranted::class);

        $this->assertSame(['manage_reservations_planner'], array_map(static fn (\ReflectionAttribute $attribute): mixed => $attribute->getArguments()[0], $attributes));
    }
}
