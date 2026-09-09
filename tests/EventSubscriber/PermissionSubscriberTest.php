<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\EventSubscriber;

use App\Event\PermissionSectionsEvent;
use KimaiPlugin\PlannerBundle\EventSubscriber\PermissionSubscriber;
use PHPUnit\Framework\TestCase;

class PermissionSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = PermissionSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(PermissionSectionsEvent::class, $events);
    }

    public function testOnPermissionSections(): void
    {
        $event = new PermissionSectionsEvent();
        $subscriber = new PermissionSubscriber();
        $subscriber->onPermissionSections($event);

        $sections = $event->getSections();
        $this->assertCount(1, $sections);
        $this->assertSame('Weekly Planner', $sections[0]->getTitle());
        $this->assertTrue($sections[0]->filter('view_planner'));
        $this->assertFalse($sections[0]->filter('view_customer'));
    }
}
