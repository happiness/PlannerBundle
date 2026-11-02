<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\EventSubscriber;

use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayCalendarPreferenceType;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds the public holiday calendar to a user's preferences, overriding the organisation's default.
 *
 * Holidays change a user's capacity like their work contract does, so only those who may edit the
 * contract can change it. Everyone else gets core's disabled hidden field, which leaves the stored
 * value alone when they save their preferences.
 */
final class HolidayPreferenceSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AuthorizationCheckerInterface $voter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserPreferenceEvent::class => ['registerPreference', 100],
        ];
    }

    public function registerPreference(UserPreferenceEvent $event): void
    {
        // the permission check needs a security token, which kernel boot does not have;
        // the planner reads the stored value from the user's preferences either way
        if ($event->isBooting()) {
            return;
        }

        $preference = (new UserPreference(HolidayCalendars::PREFERENCE))
            ->setOrder(1100)
            ->setSection('planner')
            ->setType(HolidayCalendarPreferenceType::class)
            ->setOptions(['required' => false, 'help' => 'planner.holidays.preference_help'])
            ->setEnabled($this->voter->isGranted('contract', $event->getUser()));

        try {
            $event->addPreference($preference);
        } catch (\InvalidArgumentException) {
            // the name is taken, e.g. by a user custom field of the same name: lose the field, not the page
        }
    }
}
