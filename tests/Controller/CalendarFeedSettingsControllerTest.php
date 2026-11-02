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
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use KimaiPlugin\PlannerBundle\Controller\CalendarFeedSettingsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class CalendarFeedSettingsControllerTest extends TestCase
{
    private User $user;

    private function createController(CalendarFeedTokens $tokens, bool $validCsrf = true, ?Environment $twig = null): CalendarFeedSettingsController
    {
        $this->user = new User();
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($this->user, 'main', $this->user->getRoles()));

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token): bool => $validCsrf && $token->getId() === CalendarFeedSettingsController::CSRF_TOKEN);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => '/' . $route . ($parameters !== [] ? '/' . implode('/', $parameters) : ''));

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $services = [
            'security.token_storage' => $tokenStorage,
            'security.csrf.token_manager' => $csrf,
            'router' => $router,
            'request_stack' => $requestStack,
            'twig' => $twig ?? $this->createStub(Environment::class),
            'translator' => $translator,
        ];
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => \array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(static fn (string $id): ?object => $services[$id] ?? null);

        $controller = new CalendarFeedSettingsController($tokens);
        $controller->setContainer($container);

        return $controller;
    }

    private function post(string $action, string $csrf = 'valid'): Request
    {
        return new Request([], ['_token' => $csrf, 'action' => $action]);
    }

    public function testCreateAndRenew(): void
    {
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->once())->method('resetToken')->willReturnCallback(fn (User $user): string => $user === $this->user ? str_repeat('a', 64) : '');
        $tokens->expects($this->never())->method('removeToken');

        $response = $this->createController($tokens)->update($this->post('reset'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/planner_calendar', $response->headers->get('Location'));
    }

    public function testSwitchOff(): void
    {
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->once())->method('removeToken');
        $tokens->expects($this->never())->method('resetToken');

        $this->createController($tokens)->update($this->post('remove'));
    }

    public function testInvalidCsrfChangesNothing(): void
    {
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->never())->method('resetToken');
        $tokens->expects($this->never())->method('removeToken');

        $response = $this->createController($tokens, false)->update($this->post('remove', 'forged'));

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testPageWithoutTokenShowsNoUrl(): void
    {
        $tokens = $this->createStub(CalendarFeedTokens::class);
        $tokens->method('getToken')->willReturn(null);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@Planner/calendar.html.twig', $this->callback(static fn (array $context): bool => $context['feedUrl'] === null))
            ->willReturn('page');

        $this->createController($tokens, true, $twig)->show();
    }

    public function testPageWithTokenShowsTheFeedUrl(): void
    {
        $tokens = $this->createStub(CalendarFeedTokens::class);
        $tokens->method('getToken')->willReturn(str_repeat('a', 64));

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@Planner/calendar.html.twig', $this->callback(static fn (array $context): bool => $context['feedUrl'] === '/planner_calendar_feed/' . str_repeat('a', 64)))
            ->willReturn('page');

        $this->createController($tokens, true, $twig)->show();
    }
}
