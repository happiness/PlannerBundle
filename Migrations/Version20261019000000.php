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

final class Version20261019000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link planned activities to customers and projects, make the legacy title optional';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        if (!$table->hasColumn('customer_id')) {
            $table->addColumn('customer_id', 'integer', ['notnull' => false, 'default' => null]);
            $table->addIndex(['customer_id'], 'IDX_PLANNED_ACTIVITY_CUSTOMER');
            $table->addForeignKeyConstraint('kimai2_customers', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_PLANNED_ACTIVITY_CUSTOMER');
        }

        if (!$table->hasColumn('project_id')) {
            $table->addColumn('project_id', 'integer', ['notnull' => false, 'default' => null]);
            $table->addIndex(['project_id'], 'IDX_PLANNED_ACTIVITY_PROJECT');
            $table->addForeignKeyConstraint('kimai2_projects', ['project_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_PLANNED_ACTIVITY_PROJECT');
        }

        $table->getColumn('title')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        foreach (['project' => 'project_id', 'customer' => 'customer_id'] as $name => $column) {
            if (!$table->hasColumn($column)) {
                continue;
            }
            $upper = strtoupper($name);
            if ($table->hasForeignKey('FK_PLANNED_ACTIVITY_' . $upper)) {
                $table->removeForeignKey('FK_PLANNED_ACTIVITY_' . $upper);
            }
            if ($table->hasIndex('IDX_PLANNED_ACTIVITY_' . $upper)) {
                $table->dropIndex('IDX_PLANNED_ACTIVITY_' . $upper);
            }
            $table->dropColumn($column);
        }

        // rows created after the upgrade have no title, restore it from the name before it becomes mandatory again
        $this->addSql("UPDATE kimai2_planned_activities SET title = '-' WHERE title IS NULL");
        $table->getColumn('title')->setNotnull(true);
    }
}
