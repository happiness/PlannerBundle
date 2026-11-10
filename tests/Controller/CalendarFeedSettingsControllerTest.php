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
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
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

    private function createController(CalendarFeedTokens $tokens, bool $validCsrf = true, ?Environment $twig = null, bool $usersMayChoose = false): CalendarFeedSettingsController
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

        // the organisation leaves comments out; whether people may choose is up to the test
        $settings = $this->createStub(CalendarFeedSettings::class);
        $settings->method('mayUsersChooseComments')->willReturn($usersMayChoose);
        $settings->method('includeCommentsByDefault')->willReturn(false);
        $settings->method('includeComments')->willReturnCallback(static fn (?bool $choice): bool => $usersMayChoose && $choice !== null ? $choice : false);

        $controller = new CalendarFeedSettingsController($tokens, $settings);
        $controller->setContainer($container);

        return $controller;
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $action, string $csrf = 'valid', array $fields = [], ?Session $session = null): Request
    {
        $request = new Request([], ['_token' => $csrf, 'action' => $action] + $fields);
        $request->setSession($session ?? new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function get(?Session $session = null): Request
    {
        $request = new Request();
        $request->setSession($session ?? new Session(new MockArraySessionStorage()));

        return $request;
    }

    /**
     * @param callable(array<string, mixed>): bool $check
     */
    private function expectPage(callable $check): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')
            ->with('@Planner/calendar.html.twig', $this->callback($check))
            ->willReturn('page');

        return $twig;
    }

    public function testANewHashedAddressIsShownOnce(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->once())->method('resetToken')->willReturnCallback(fn (User $user): string => $user === $this->user ? str_repeat('a', 64) : '');
        $tokens->expects($this->never())->method('removeToken');
        $tokens->method('hasFeed')->willReturn(true);
        // stored hashed: the service cannot give it back
        $tokens->method('getToken')->willReturn(null);

        $response = $this->createController($tokens)->update($this->post('reset', 'valid', [], $session));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/planner_calendar', $response->headers->get('Location'));

        $first = $this->expectPage(static fn (array $context): bool => $context['feedUrl'] === '/planner_calendar_feed/' . str_repeat('a', 64) && $context['shownOnce'] === true);
        $this->createController($tokens, true, $first)->show($this->get($session));

        // the next visit no longer knows it
        $second = $this->expectPage(static fn (array $context): bool => $context['feedUrl'] === null && $context['hasFeed'] === true);
        $this->createController($tokens, true, $second)->show($this->get($session));
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
        $tokens->expects($this->never())->method('setIncludeComments');

        $response = $this->createController($tokens, false, null, true)->update($this->post('remove', 'forged'));

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testPageWithoutFeedShowsNoUrl(): void
    {
        $tokens = $this->createStub(CalendarFeedTokens::class);
        $tokens->method('hasFeed')->willReturn(false);
        $tokens->method('getToken')->willReturn(null);

        $twig = $this->expectPage(static fn (array $context): bool => $context['feedUrl'] === null && $context['hasFeed'] === false);

        $this->createController($tokens, true, $twig)->show($this->get());
    }

    public function testPageWithAPlainTokenShowsTheFeedUrl(): void
    {
        $tokens = $this->createStub(CalendarFeedTokens::class);
        $tokens->method('hasFeed')->willReturn(true);
        $tokens->method('getToken')->willReturn(str_repeat('a', 64));

        $twig = $this->expectPage(static fn (array $context): bool => $context['feedUrl'] === '/planner_calendar_feed/' . str_repeat('a', 64) && $context['shownOnce'] === false);

        $this->createController($tokens, true, $twig)->show($this->get());
    }

    public function testPeopleMayChooseCommentsWhenAllowed(): void
    {
        $stored = [];
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->never())->method('resetToken');
        $tokens->method('setIncludeComments')->willReturnCallback(static function (User $user, ?bool $include) use (&$stored): void {
            $stored[] = $include;
        });

        $controller = $this->createController($tokens, true, null, true);
        $controller->update($this->post('save', 'valid', ['include_comments' => '1']));
        $controller->update($this->post('save'));

        // a choice equal to the organisation's is not kept, so it follows the organisation later
        $this->assertSame([true, null], $stored);
    }

    public function testCommentChoiceIsIgnoredWhenNotAllowed(): void
    {
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->never())->method('setIncludeComments');

        $this->createController($tokens)->update($this->post('save', 'valid', ['include_comments' => '1']));
    }

    public function testAnUnknownActionChangesNothing(): void
    {
        $tokens = $this->createMock(CalendarFeedTokens::class);
        $tokens->expects($this->never())->method('resetToken');
        $tokens->expects($this->never())->method('removeToken');
        $tokens->expects($this->never())->method('setIncludeComments');

        $controller = $this->createController($tokens, true, null, true);
        $controller->update($this->post(''));
        $controller->update($this->post('renew'));
    }

    public function testRequiresThePlannerAndTheFeedPermission(): void
    {
        $attributes = (new \ReflectionClass(CalendarFeedSettingsController::class))->getAttributes(\Symfony\Component\Security\Http\Attribute\IsGranted::class);

        $this->assertSame(['view_planner', 'calendar_feed_planner'], array_map(static fn (\ReflectionAttribute $attribute): mixed => $attribute->getArguments()[0], $attributes));
    }
}
