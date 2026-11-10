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
use App\Form\Type\YesNoType;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Adds the calendar feed settings to the planner's section of the system settings: whether
 * comments go into feeds, whether people may choose that for their own feed, and whether
 * feed tokens are stored hashed.
 */
final class CalendarFeedSettingsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // after HolidaySettingsSubscriber (100), which creates the section
            SystemConfigurationEvent::class => ['onSystemConfiguration', 90],
        ];
    }

    public function onSystemConfiguration(SystemConfigurationEvent $event): void
    {
        $section = null;
        foreach ($event->getConfigurations() as $configuration) {
            if ($configuration->getSection() === 'planner') {
                $section = $configuration;
                break;
            }
        }

        if ($section === null) {
            $section = (new SystemConfiguration('planner'))
                ->setTranslation('planner.title')
                ->setTranslationDomain('messages');
            $event->addConfiguration($section);
        }

        // the values shown until an admin saves the section: the safe choices CalendarFeedSettings falls back to
        $section->addConfiguration(
            (new Configuration(CalendarFeedSettings::SETTING_COMMENTS))
                ->setLabel('planner.calendar_feed.comments')
                ->setTranslationDomain('messages')
                ->setType(ChoiceType::class)
                ->setValue(CalendarFeedSettings::COMMENTS_EXCLUDE)
                ->setOptions([
                    'help' => 'planner.calendar_feed.comments_help',
                    'expanded' => true,
                    'search' => false,
                    'choices' => [
                        'planner.calendar_feed.comments_exclude' => CalendarFeedSettings::COMMENTS_EXCLUDE,
                        'planner.calendar_feed.comments_include' => CalendarFeedSettings::COMMENTS_INCLUDE,
                    ],
                ])
        );

        $section->addConfiguration(
            (new Configuration(CalendarFeedSettings::SETTING_COMMENTS_USER_CHOICE))
                ->setLabel('planner.calendar_feed.comments_user')
                ->setTranslationDomain('messages')
                ->setType(YesNoType::class)
                ->setRequired(false)
                ->setValue(false)
                ->setOptions(['help' => 'planner.calendar_feed.comments_user_help'])
        );

        $section->addConfiguration(
            (new Configuration(CalendarFeedSettings::SETTING_HASH_TOKENS))
                ->setLabel('planner.calendar_feed.hash')
                ->setTranslationDomain('messages')
                ->setType(YesNoType::class)
                ->setRequired(false)
                ->setValue(true)
                ->setOptions(['help' => 'planner.calendar_feed.hash_help'])
        );
    }
}
