<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261111000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reserved time: its own table, referenced by planned activities';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        if (!$schema->hasTable('kimai2_planner_reservations')) {
            $reservations = $schema->createTable('kimai2_planner_reservations');
            $reservations->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $reservations->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
            $reservations->addColumn('color', 'string', ['length' => 7, 'notnull' => false]);
            $reservations->addColumn('archived', 'boolean', ['notnull' => true, 'default' => false]);
            $reservations->addColumn('position', 'integer', ['notnull' => true, 'default' => 0]);
            $reservations->setPrimaryKey(['id']);
            $reservations->addUniqueIndex(['name'], 'UNIQ_PLANNER_RESERVATION_NAME');
        }

        $activities = $schema->getTable('kimai2_planned_activities');
        if (!$activities->hasColumn('reservation_id')) {
            $activities->addColumn('reservation_id', 'integer', ['notnull' => false]);
            $activities->addIndex(['reservation_id'], 'IDX_PLANNED_ACTIVITY_RESERVATION_ID');
            // a reservation that is planned can only be archived, never deleted
            $activities->addForeignKeyConstraint('kimai2_planner_reservations', ['reservation_id'], ['id'], ['onDelete' => 'RESTRICT'], 'FK_PLANNED_ACTIVITY_RESERVATION');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_planned_activities')) {
            $activities = $schema->getTable('kimai2_planned_activities');
            if ($activities->hasColumn('reservation_id')) {
                $activities->removeForeignKey('FK_PLANNED_ACTIVITY_RESERVATION');
                $activities->dropIndex('IDX_PLANNED_ACTIVITY_RESERVATION_ID');
                $activities->dropColumn('reservation_id');
            }
        }

        if ($schema->hasTable('kimai2_planner_reservations')) {
            $schema->dropTable('kimai2_planner_reservations');
        }
    }
}
