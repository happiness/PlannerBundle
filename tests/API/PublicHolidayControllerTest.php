<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\API;

use App\Entity\User;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\PlannerBundle\API\PublicHolidayController;
use KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface;
use KimaiPlugin\PlannerBundle\Holiday\PublicHoliday;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PublicHolidayControllerTest extends TestCase
{
    /** @var array<mixed> */
    private array $result = [];

    private function createUser(int $id): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    /**
     * @param array<User> $visibleUsers
     */
    private function createController(array $visibleUsers, HolidayProviderInterface $holidays): PublicHolidayController
    {
        $viewHandler = $this->createStub(ViewHandlerInterface::class);
        $viewHandler->method('handle')->willReturnCallback(function (View $view): Response {
            $this->result = (array) $view->getData();

            return new Response();
        });

        $userProvider = $this->createStub(PlannerUserProvider::class);
        $userProvider->method('getVisibleUsers')->willReturn($visibleUsers);

        $controller = new PublicHolidayController($viewHandler, $holidays, $userProvider);

        $user = new User();
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            ['security.token_storage', $tokenStorage],
        ]);
        $controller->setContainer($container);

        return $controller;
    }

    /**
     * @param array<int> $users
     */
    private function createParams(string $begin, string $end, array $users = []): ParamFetcherInterface
    {
        $params = $this->createStub(ParamFetcherInterface::class);
        $params->method('get')->willReturnMap([
            ['begin', null, $begin],
            ['end', null, $end],
            ['users', null, array_map('strval', $users)],
        ]);

        return $params;
    }

    public function testReturnsTheHolidaysOfEveryVisibleUser(): void
    {
        $anna = $this->createUser(1);
        $ben = $this->createUser(2);

        $holidays = $this->createStub(HolidayProviderInterface::class);
        $holidays->method('getHolidays')->willReturnCallback(static fn (User $user): array => $user === $anna
            ? ['2026-12-25' => new PublicHoliday(new \DateTimeImmutable('2026-12-25'), 'Christmas Day', 'SE', 'Sweden', 'official')]
            : ['2026-12-28' => new PublicHoliday(new \DateTimeImmutable('2026-12-28'), 'Boxing Day (substitute day)', 'GB-ENG', 'United Kingdom › England', 'bank')]);

        $this->createController([$anna, $ben], $holidays)->cgetAction($this->createParams('2026-12-01', '2026-12-31'));

        $this->assertSame([
            ['user' => 1, 'date' => '2026-12-25', 'name' => 'Christmas Day', 'calendar' => 'SE', 'calendarName' => 'Sweden', 'type' => 'official'],
            ['user' => 2, 'date' => '2026-12-28', 'name' => 'Boxing Day (substitute day)', 'calendar' => 'GB-ENG', 'calendarName' => 'United Kingdom › England', 'type' => 'bank'],
        ], $this->result);
    }

    public function testUsersNarrowTheVisibleUsers(): void
    {
        $holidays = $this->createMock(HolidayProviderInterface::class);
        $holidays->expects($this->once())->method('getHolidays')->willReturn([]);

        // user 3 is not visible to the caller and is left out
        $this->createController([$this->createUser(1), $this->createUser(2)], $holidays)
            ->cgetAction($this->createParams('2026-12-01', '2026-12-31', [2, 3]));
    }

    public function testEndBeforeBeginIsBadRequest(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->createController([], $this->createStub(HolidayProviderInterface::class))
            ->cgetAction($this->createParams('2026-12-31', '2026-12-01'));
    }

    public function testRangeIsCappedAtOneYear(): void
    {
        $controller = $this->createController([], $this->createStub(HolidayProviderInterface::class));

        // a leap year is 366 days, both ends included
        $controller->cgetAction($this->createParams('2028-01-01', '2028-12-31'));

        $this->expectException(BadRequestHttpException::class);
        $controller->cgetAction($this->createParams('2026-01-01', '2027-01-02'));
    }
}
