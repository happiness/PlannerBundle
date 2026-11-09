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

final class Version20261110000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plan reservations: time held for something that is not a customer or project';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        if (!$table->hasColumn('reservation')) {
            $table->addColumn('reservation', 'string', ['length' => 64, 'notnull' => false]);
            $table->addIndex(['reservation'], 'IDX_PLANNED_ACTIVITY_RESERVATION');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        if ($table->hasColumn('reservation')) {
            if ($table->hasIndex('IDX_PLANNED_ACTIVITY_RESERVATION')) {
                $table->dropIndex('IDX_PLANNED_ACTIVITY_RESERVATION');
            }
            $table->dropColumn('reservation');
        }
    }
}
