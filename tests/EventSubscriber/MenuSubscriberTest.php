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
use App\Event\ConfigureMainMenuEvent;
use KimaiPlugin\PlannerBundle\EventSubscriber\MenuSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class MenuSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = MenuSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(ConfigureMainMenuEvent::class, $events);
    }

    public function testOnMenuConfigureWhenGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $attribute): bool => $attribute === 'view_planner');

        $user = new User();
        $event = new ConfigureMainMenuEvent($user);

        $subscriber = new MenuSubscriber($security);
        $subscriber->onMenuConfigure($event);

        $menu = $event->getMenu();
        $plannerChild = $menu->getChild('planner');

        $this->assertNotNull($plannerChild);
        $this->assertSame('planner', $plannerChild->getRoute());
        $this->assertSame('planner.title', $plannerChild->getLabel());
        $this->assertTrue($plannerChild->isChildRoute('planner_week'));
    }

    public function testOnMenuConfigureWhenDenied(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(false);

        $user = new User();
        $event = new ConfigureMainMenuEvent($user);

        $subscriber = new MenuSubscriber($security);
        $subscriber->onMenuConfigure($event);

        $menu = $event->getMenu();
        $plannerChild = $menu->getChild('planner');

        $this->assertNull($plannerChild);
    }

    public function testReservedTimeIsInTheAdminMenuForWhoeverManagesIt(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $attribute): bool => $attribute === 'manage_reservations_planner');

        $event = new ConfigureMainMenuEvent(new User());
        (new MenuSubscriber($security))->onMenuConfigure($event);

        $item = $event->getAdminMenu()->getChild('planner_reservations');
        $this->assertNotNull($item);
        $this->assertSame('planner.reservations', $item->getLabel());
        $this->assertTrue($item->isChildRoute('planner_reservation_edit'));
        // without view_planner there is no planner menu item
        $this->assertNull($event->getMenu()->getChild('planner'));
    }
}
