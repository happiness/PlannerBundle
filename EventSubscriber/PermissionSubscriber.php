<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\EventSubscriber;

use App\Event\PermissionSectionsEvent;
use App\Model\PermissionSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PermissionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PermissionSectionsEvent::class => ['onPermissionSections', 100],
        ];
    }

    public function onPermissionSections(PermissionSectionsEvent $event): void
    {
        $event->addSection(new PermissionSection('Weekly Planner', '_planner'));
    }
}
