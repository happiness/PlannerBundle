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

final class Version20261030000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add time off to planned activities, which may leave the hours per day empty for the whole day';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        if (!$table->hasColumn('time_off')) {
            $table->addColumn('time_off', 'boolean', ['notnull' => true, 'default' => false]);
        }

        $table->getColumn('hours_per_day')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        // time off has no customer, so it would turn into an activity nobody can edit: remove it
        if ($table->hasColumn('time_off')) {
            $this->addSql('DELETE FROM kimai2_planned_activities WHERE time_off = 1');
            $table->dropColumn('time_off');
        }

        $table->getColumn('hours_per_day')->setNotnull(true);
    }
}
