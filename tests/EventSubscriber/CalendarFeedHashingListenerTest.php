<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\EventSubscriber;

use App\Entity\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedTokens;
use KimaiPlugin\PlannerBundle\EventSubscriber\CalendarFeedHashingListener;
use PHPUnit\Framework\TestCase;

class CalendarFeedHashingListenerTest extends TestCase
{
    private function createSetting(string $name, string $value): Configuration
    {
        $configuration = new Configuration();
        $configuration->setName($name);
        $configuration->setValue($value);

        return $configuration;
    }

    private function createEntityManager(int $expectedStatements): EntityManagerInterface
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly($expectedStatements))->method('executeStatement')->with(CalendarFeedTokens::HASH_PLAIN_TOKENS_SQL)->willReturn(0);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    public function testSwitchingHashingOnHashesThePlainTokens(): void
    {
        $on = $this->createSetting(CalendarFeedSettings::SETTING_HASH_TOKENS, '1');
        $entityManager = $this->createEntityManager(2);

        $listener = new CalendarFeedHashingListener();
        $listener->postPersist($on, new PostPersistEventArgs($on, $entityManager));
        $listener->postUpdate($on, new PostUpdateEventArgs($on, $entityManager));
    }

    public function testOtherSettingsAndHashingOffChangeNothing(): void
    {
        $off = $this->createSetting(CalendarFeedSettings::SETTING_HASH_TOKENS, '0');
        $other = $this->createSetting(CalendarFeedSettings::SETTING_COMMENTS_USER_CHOICE, '1');
        $entityManager = $this->createEntityManager(0);

        $listener = new CalendarFeedHashingListener();
        $listener->postUpdate($off, new PostUpdateEventArgs($off, $entityManager));
        $listener->postPersist($other, new PostPersistEventArgs($other, $entityManager));
    }
}
