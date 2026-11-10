<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Event;

use App\Entity\User;
use KimaiPlugin\PlannerBundle\Event\PlannerUsersEvent;
use PHPUnit\Framework\TestCase;

class PlannerUsersEventTest extends TestCase
{
    public function testRemovesOnlyTheGivenUser(): void
    {
        $current = new User();
        $alex = new User();
        $sam = new User();
        $event = new PlannerUsersEvent($current, [$alex, $sam]);

        $event->removeUser($alex);
        $event->removeUser(new User());

        self::assertSame($current, $event->getCurrentUser());
        self::assertSame([$sam], $event->getUsers());
    }
}
