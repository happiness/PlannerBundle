<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\EventSubscriber;

use App\Entity\Configuration;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;

/**
 * Hashes every plain calendar feed token as soon as "Store feed addresses hashed" is saved as on, so tokens created
 * while it was off do not wait for a fetch that may never come. Kimai dispatches no event when settings are saved,
 * but each one is a Configuration entity.
 */
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Configuration::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Configuration::class)]
final class CalendarFeedHashingListener
{
    public function postPersist(Configuration $configuration, PostPersistEventArgs $args): void
    {
        $this->hashIfSwitchedOn($configuration, $args->getObjectManager()->getConnection());
    }

    public function postUpdate(Configuration $configuration, PostUpdateEventArgs $args): void
    {
        $this->hashIfSwitchedOn($configuration, $args->getObjectManager()->getConnection());
    }

    private function hashIfSwitchedOn(Configuration $configuration, Connection $connection): void
    {
        if ($configuration->getName() !== CalendarFeedSettings::SETTING_HASH_TOKENS || $configuration->getValue() !== '1') {
            return;
        }

        $connection->executeStatement(CalendarFeedTokens::HASH_PLAIN_TOKENS_SQL);
    }
}
