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

final class Version20261102000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendar feed tokens, one per user';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_planner_calendar_tokens')) {
            return;
        }

        $table = $schema->createTable('kimai2_planner_calendar_tokens');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('token', 'string', ['length' => 64, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id'], 'UNIQ_PLANNER_CALENDAR_USER');
        $table->addUniqueIndex(['token'], 'UNIQ_PLANNER_CALENDAR_TOKEN');
        $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_PLANNER_CALENDAR_USER');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_planner_calendar_tokens')) {
            $schema->dropTable('kimai2_planner_calendar_tokens');
        }
    }
}
