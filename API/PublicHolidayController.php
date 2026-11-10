<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\API;

use App\API\BaseApiController;
use App\Entity\User;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface;
use KimaiPlugin\PlannerBundle\Planner\PlannerUserProvider;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints;

#[Route(path: '/planner/holidays')]
#[IsGranted('API')]
#[IsGranted('view_planner')]
#[OA\Tag(name: 'Planner')]
final class PublicHolidayController extends BaseApiController
{
    /** the most days one request may ask for, both ends included */
    public const MAX_DAYS = 366;

    public function __construct(
        private readonly ViewHandlerInterface $viewHandler,
        private readonly HolidayProviderInterface $holidayProvider,
        private readonly PlannerUserProvider $userProvider
    ) {
    }

    /**
     * Fetch the public holidays users have off
     */
    #[OA\Response(response: 200, description: 'Returns the public holidays of every requested user, ordered by user and date', content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
        new OA\Property(property: 'user', type: 'integer', description: 'User ID'),
        new OA\Property(property: 'date', type: 'string', format: 'date', description: 'Format: Y-m-d'),
        new OA\Property(property: 'name', type: 'string', description: 'Name of the holiday in the language of the request'),
        new OA\Property(property: 'calendar', type: 'string', description: 'Code of the holiday calendar the user follows, e.g. SE or GB-ENG'),
        new OA\Property(property: 'calendarName', type: 'string', description: 'Name of the holiday calendar'),
        new OA\Property(property: 'type', type: 'string', description: 'Kind of holiday: official, bank or observance'),
    ])))]
    #[Route(methods: ['GET'], path: '', name: 'get_planner_holidays')]
    #[Rest\QueryParam(name: 'users', map: true, requirements: '\d+', strict: true, nullable: true, default: [], description: 'List of user IDs, e.g.: users[]=1&users[]=2 (default: all users you are allowed to see)')]
    #[Rest\QueryParam(name: 'begin', requirements: [new Constraints\DateTime(format: 'Y-m-d', message: 'This value is not a valid date, expected format: Y-m-d (2026-10-19).')], strict: true, nullable: false, description: 'First day. Format: Y-m-d')]
    #[Rest\QueryParam(name: 'end', requirements: [new Constraints\DateTime(format: 'Y-m-d', message: 'This value is not a valid date, expected format: Y-m-d (2026-10-19).')], strict: true, nullable: false, description: 'Last day, at most one year after the first. Format: Y-m-d')]
    public function cgetAction(ParamFetcherInterface $paramFetcher): Response
    {
        $begin = $this->getDate($paramFetcher, 'begin');
        $end = $this->getDate($paramFetcher, 'end');

        if ($end < $begin) {
            throw new BadRequestHttpException('The end must not be before the begin');
        }
        if ($begin->diff($end)->days + 1 > self::MAX_DAYS) {
            throw new BadRequestHttpException(\sprintf('The range must not be longer than %d days', self::MAX_DAYS));
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $users = $this->userProvider->getVisibleUsers($currentUser);

        /** @var array<int|string> $userIds */
        $userIds = $paramFetcher->get('users');
        if ($userIds !== []) {
            $userIds = array_map('intval', $userIds);
            $users = array_values(array_filter($users, static fn (User $user): bool => \in_array($user->getId(), $userIds, true)));
        }

        $result = [];
        foreach ($users as $user) {
            foreach ($this->holidayProvider->getHolidays($user, $begin, $end) as $date => $holiday) {
                $result[] = [
                    'user' => $user->getId(),
                    'date' => $date,
                    'name' => $holiday->getName(),
                    'calendar' => $holiday->getCalendar(),
                    'calendarName' => $holiday->getCalendarName(),
                    'type' => $holiday->getType(),
                ];
            }
        }

        return $this->viewHandler->handle(new View($result, Response::HTTP_OK));
    }

    /**
     * The route already checks the format; this only narrows the fetched value to the string it is.
     */
    private function getDate(ParamFetcherInterface $paramFetcher, string $name): \DateTimeImmutable
    {
        $value = $paramFetcher->get($name);
        if (!\is_string($value) || $value === '') {
            throw new BadRequestHttpException(\sprintf('The "%s" parameter is required, format: Y-m-d', $name));
        }

        return new \DateTimeImmutable($value);
    }
}
