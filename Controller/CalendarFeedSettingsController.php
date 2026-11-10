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
use App\Utils\PageSetup;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Where users get, renew or switch off the URL of their own planning as a calendar feed, and
 * choose whether it carries comments when the organisation lets them.
 */
#[Route(path: '/planner/calendar')]
#[IsGranted('calendar_feed_planner')]
final class CalendarFeedSettingsController extends AbstractController
{
    public const CSRF_TOKEN = 'planner_calendar';
    /** a token stored hashed is only known right after it is created: the redirect carries it here once */
    private const SESSION_NEW_TOKEN = 'planner_calendar_new_token';

    public function __construct(
        private readonly CalendarFeedTokens $tokens,
        private readonly CalendarFeedSettings $settings
    ) {
    }

    #[Route(path: '', name: 'planner_calendar', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $user = $this->getUser();
        $newToken = $request->hasSession() ? $request->getSession()->remove(self::SESSION_NEW_TOKEN) : null;
        $hasFeed = $this->tokens->hasFeed($user);
        $token = $hasFeed && \is_string($newToken) ? $newToken : $this->tokens->getToken($user);

        $page = new PageSetup('planner.calendar.title');
        $page->setHelp('planner.html');

        return $this->render('@Planner/calendar.html.twig', [
            'page_setup' => $page,
            'hasFeed' => $hasFeed,
            'feedUrl' => $token === null ? null : $this->generateUrl('planner_calendar_feed', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            // the URL is on the page now and stored hashed: it is never shown again
            'shownOnce' => \is_string($newToken) && $this->tokens->getToken($user) === null,
            'canChooseComments' => $this->settings->mayUsersChooseComments(),
            'includeComments' => $this->settings->includeComments($this->tokens->getIncludeComments($user)),
        ]);
    }

    #[Route(path: '', name: 'planner_calendar_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN, $request->request->getString('_token'))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('planner_calendar');
        }

        $user = $this->getUser();
        $action = $request->request->getString('action');

        if ($action === 'remove') {
            $this->tokens->removeToken($user);

            return $this->redirectToRoute('planner_calendar');
        }

        if ($action !== 'save') {
            $token = $this->tokens->resetToken($user);
            if ($request->hasSession()) {
                $request->getSession()->set(self::SESSION_NEW_TOKEN, $token);
            }
        }

        if ($this->settings->mayUsersChooseComments()) {
            // only a choice that differs from the organisation's is kept, so the rest follow it if it changes
            $include = $request->request->getBoolean('include_comments');
            $this->tokens->setIncludeComments($user, $include === $this->settings->includeCommentsByDefault() ? null : $include);
        }

        return $this->redirectToRoute('planner_calendar');
    }
}
