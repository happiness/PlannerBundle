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
use App\Form\Type\YesNoType;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use KimaiPlugin\PlannerBundle\EventSubscriber\CalendarFeedSettingsSubscriber;
use KimaiPlugin\PlannerBundle\EventSubscriber\HolidaySettingsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CalendarFeedSettingsSubscriberTest extends TestCase
{
    public function testRunsAfterTheHolidaySettings(): void
    {
        $own = CalendarFeedSettingsSubscriber::getSubscribedEvents()[SystemConfigurationEvent::class];
        $holidays = HolidaySettingsSubscriber::getSubscribedEvents()[SystemConfigurationEvent::class];

        $this->assertIsArray($own);
        $this->assertIsArray($holidays);
        $this->assertLessThan($holidays[1], $own[1]);
    }

    public function testJoinsThePlannerSection(): void
    {
        $event = new SystemConfigurationEvent([]);
        (new HolidaySettingsSubscriber())->onSystemConfiguration($event);
        (new CalendarFeedSettingsSubscriber())->onSystemConfiguration($event);

        $sections = $event->getConfigurations();
        $this->assertCount(1, $sections);
        $section = $sections[0];

        $comments = $section->getConfigurationByName(CalendarFeedSettings::SETTING_COMMENTS);
        $this->assertNotNull($comments);
        $this->assertSame(ChoiceType::class, $comments->getType());
        $this->assertSame(CalendarFeedSettings::COMMENTS_EXCLUDE, $comments->getValue());

        $choice = $section->getConfigurationByName(CalendarFeedSettings::SETTING_COMMENTS_USER_CHOICE);
        $this->assertNotNull($choice);
        $this->assertSame(YesNoType::class, $choice->getType());
        $this->assertFalse($choice->getValue());

        $hash = $section->getConfigurationByName(CalendarFeedSettings::SETTING_HASH_TOKENS);
        $this->assertNotNull($hash);
        $this->assertTrue($hash->getValue());
    }

    public function testCreatesTheSectionWhenAlone(): void
    {
        $event = new SystemConfigurationEvent([]);
        (new CalendarFeedSettingsSubscriber())->onSystemConfiguration($event);

        $this->assertCount(1, $event->getConfigurations());
        $this->assertSame('planner', $event->getConfigurations()[0]->getSection());
    }
}
