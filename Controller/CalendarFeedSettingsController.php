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
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Where users get, renew or switch off the URL of their own planning as a calendar feed.
 */
#[Route(path: '/planner/calendar')]
#[IsGranted('view_planner')]
final class CalendarFeedSettingsController extends AbstractController
{
    public const CSRF_TOKEN = 'planner_calendar';

    public function __construct(private readonly CalendarFeedTokens $tokens)
    {
    }

    #[Route(path: '', name: 'planner_calendar', methods: ['GET'])]
    public function show(): Response
    {
        $token = $this->tokens->getToken($this->getUser());

        $page = new PageSetup('planner.calendar.title');
        $page->setHelp('planner.html');

        return $this->render('@Planner/calendar.html.twig', [
            'page_setup' => $page,
            'feedUrl' => $token === null ? null : $this->generateUrl('planner_calendar_feed', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route(path: '', name: 'planner_calendar_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN, $request->request->getString('_token'))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('planner_calendar');
        }

        if ($request->request->getString('action') === 'remove') {
            $this->tokens->removeToken($this->getUser());
        } else {
            $this->tokens->resetToken($this->getUser());
        }

        return $this->redirectToRoute('planner_calendar');
    }
}
