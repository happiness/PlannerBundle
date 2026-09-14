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

final class Version20260914000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurrence_group column to kimai2_planned_activities table';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_planned_activities')) {
            $table = $schema->getTable('kimai2_planned_activities');
            if (!$table->hasColumn('recurrence_group')) {
                $table->addColumn('recurrence_group', 'string', ['length' => 64, 'notnull' => false, 'default' => null]);
                $table->addIndex(['recurrence_group'], 'IDX_PLANNED_ACTIVITY_RECURRENCE');
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('kimai2_planned_activities')) {
            $table = $schema->getTable('kimai2_planned_activities');
            if ($table->hasColumn('recurrence_group')) {
                if ($table->hasIndex('IDX_PLANNED_ACTIVITY_RECURRENCE')) {
                    $table->dropIndex('IDX_PLANNED_ACTIVITY_RECURRENCE');
                }
                $table->dropColumn('recurrence_group');
            }
        }
    }
}
