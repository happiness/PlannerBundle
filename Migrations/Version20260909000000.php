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

final class Version20260909000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create kimai2_planned_activities table for weekly planner';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            $table = $schema->createTable('kimai2_planned_activities');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('date_begin', 'date', ['notnull' => true]);
            $table->addColumn('date_end', 'date', ['notnull' => true]);
            $table->addColumn('title', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('hours_per_day', 'float', ['notnull' => true]);
            $table->addColumn('color', 'string', ['length' => 7, 'notnull' => false, 'default' => null]);
            $table->addColumn('comment', 'text', ['notnull' => false, 'default' => null]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'IDX_PLANNED_ACTIVITY_USER');
            $table->addIndex(['date_begin', 'date_end'], 'IDX_PLANNED_ACTIVITY_DATES');
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_PLANNED_ACTIVITY_USER');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_planned_activities')) {
            $schema->dropTable('kimai2_planned_activities');
        }
    }
}
