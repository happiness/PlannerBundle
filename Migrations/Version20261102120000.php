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

final class Version20261102120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark planned activities as tentative';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        if (!$table->hasColumn('tentative')) {
            $table->addColumn('tentative', 'boolean', ['notnull' => true, 'default' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('kimai2_planned_activities')) {
            return;
        }

        $table = $schema->getTable('kimai2_planned_activities');

        if ($table->hasColumn('tentative')) {
            $table->dropColumn('tentative');
        }
    }
}
