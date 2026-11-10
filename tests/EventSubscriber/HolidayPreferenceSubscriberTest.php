<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\EventSubscriber;

use App\Entity\User;
use App\Event\UserPreferenceEvent;
use KimaiPlugin\PlannerBundle\EventSubscriber\HolidayPreferenceSubscriber;
use KimaiPlugin\PlannerBundle\Form\Type\HolidayCalendarPreferenceType;
use KimaiPlugin\PlannerBundle\Holiday\HolidayCalendars;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HolidayPreferenceSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $this->assertArrayHasKey(UserPreferenceEvent::class, HolidayPreferenceSubscriber::getSubscribedEvents());
    }

    public function testOnlyContractEditorsCanChangeTheCalendar(): void
    {
        foreach ([true, false] as $granted) {
            $user = new User();
            $voter = $this->createMock(AuthorizationCheckerInterface::class);
            $voter->expects($this->once())->method('isGranted')->with('contract', $user)->willReturn($granted);

            $event = new UserPreferenceEvent($user, [], false);
            (new HolidayPreferenceSubscriber($voter))->registerPreference($event);

            $preferences = $event->getPreferences();
            $this->assertCount(1, $preferences);
            $this->assertSame(HolidayCalendars::PREFERENCE, $preferences[0]->getName());
            $this->assertSame(HolidayCalendarPreferenceType::class, $preferences[0]->getType());
            $this->assertSame($granted, $preferences[0]->isEnabled());
            // without the permission core renders the field as a HiddenType with these options
            $this->assertSame([], array_diff(array_keys($preferences[0]->getOptions()), ['required', 'help']));
        }
    }

    public function testSkippedWhileBooting(): void
    {
        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->expects($this->never())->method('isGranted');

        $event = new UserPreferenceEvent(new User(), [], true);
        (new HolidayPreferenceSubscriber($voter))->registerPreference($event);

        $this->assertSame([], $event->getPreferences());
    }
}
