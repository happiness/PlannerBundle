<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Feed;

use App\Security\RolePermissionManager;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedBuilder;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use KimaiPlugin\PlannerBundle\Repository\PlannedActivityRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The calendar feed, fetched by calendar apps that cannot log in.
 *
 * Its route sits outside "/{_locale}/", which Kimai's access control leaves public: the secret token
 * in the URL is the only credential. Unknown tokens, disabled users and users who lost the
 * planner permission all get the same 404.
 */
#[AsController]
final class CalendarFeedController
{
    /** days of past planning in the feed */
    public const DAYS_BACK = 60;
    /** days of future planning in the feed */
    public const DAYS_AHEAD = 365;

    public function __construct(
        private readonly CalendarFeedTokens $tokens,
        private readonly CalendarFeedBuilder $builder,
        private readonly PlannedActivityRepository $repository,
        private readonly RolePermissionManager $permissions
    ) {
    }

    #[Route(path: '/planner-calendar/{token}.ics', name: 'planner_calendar_feed', requirements: ['token' => '[0-9a-f]{64}'], methods: ['GET'])]
    public function feed(string $token): Response
    {
        $user = $this->tokens->findUser($token);
        if ($user === null || !$user->isEnabled() || !$this->permissions->hasRolePermission($user, 'view_planner')) {
            throw new NotFoundHttpException();
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone($user->getTimezone()));
        $activities = $this->repository->findPlannedActivitiesForUserAndRange(
            $user,
            $today->modify(\sprintf('-%d days', self::DAYS_BACK)),
            $today->modify(\sprintf('+%d days', self::DAYS_AHEAD))->setTime(23, 59, 59)
        );

        return new Response($this->builder->build($user, $activities), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="planner.ics"',
            'Cache-Control' => 'private, no-store',
            // the URL is a credential: keep it out of search engines and referrers
            'X-Robots-Tag' => 'noindex',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
