<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\EventSubscriber;

use App\Event\SystemConfigurationEvent;
use KimaiPlugin\PlannerBundle\EventSubscriber\HolidaySettingsSubscriber;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayCalendarType;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayObservancesType;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use PHPUnit\Framework\TestCase;

class HolidaySettingsSubscriberTest extends TestCase
{
    public function testAddsThePlannerSection(): void
    {
        $this->assertArrayHasKey(SystemConfigurationEvent::class, HolidaySettingsSubscriber::getSubscribedEvents());

        $event = new SystemConfigurationEvent([]);
        (new HolidaySettingsSubscriber())->onSystemConfiguration($event);

        $sections = $event->getConfigurations();
        $this->assertCount(1, $sections);
        $this->assertSame('planner', $sections[0]->getSection());

        $types = [];
        foreach ($sections[0]->getConfiguration() as $configuration) {
            $types[$configuration->getName()] = $configuration->getType();
        }
        $this->assertSame([
            HolidayCalendars::SETTING_CALENDAR => HolidayCalendarType::class,
            HolidayCalendars::SETTING_OBSERVANCES => HolidayObservancesType::class,
        ], $types);
    }
}
