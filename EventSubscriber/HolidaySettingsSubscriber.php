<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use App\Form\Model\Configuration;
use App\Form\Model\SystemConfiguration;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayCalendarType;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayObservancesType;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a "Planner" section to the system settings: the organisation's default public holiday
 * calendar and the observance days that also count as days off.
 */
final class HolidaySettingsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigurationEvent::class => ['onSystemConfiguration', 100],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $section = (new SystemConfiguration('planner'))
            ->setTranslation('planner.title')
            ->setTranslationDomain('messages');

        $section->addConfiguration(
            (new Configuration(HolidayCalendars::SETTING_CALENDAR))
                ->setLabel('planner.holidays.calendar')
                ->setTranslationDomain('messages')
                ->setOptions(['help' => 'planner.holidays.calendar_help'])
                ->setRequired(false)
                ->setType(HolidayCalendarType::class)
        );

        $section->addConfiguration(
            (new Configuration(HolidayCalendars::SETTING_OBSERVANCES))
                ->setLabel('planner.holidays.observances')
                ->setTranslationDomain('messages')
                ->setOptions(['help' => 'planner.holidays.observances_help'])
                ->setRequired(false)
                ->setType(HolidayObservancesType::class)
        );

        $event->addConfiguration($section);
    }
}
