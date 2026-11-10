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

final class Version20261110180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendar feed tokens can be stored hashed and carry the user\'s choice on comments';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('kimai2_planner_calendar_tokens');
        if (!$table->hasColumn('hashed')) {
            // existing tokens are plain; they are hashed the next time their feed is fetched, if the setting asks for it
            $table->addColumn('hashed', 'boolean', ['notnull' => true, 'default' => false]);
        }
        if (!$table->hasColumn('include_comments')) {
            $table->addColumn('include_comments', 'boolean', ['notnull' => false, 'default' => null]);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('kimai2_planner_calendar_tokens');
        if ($table->hasColumn('include_comments')) {
            $table->dropColumn('include_comments');
        }
        if ($table->hasColumn('hashed')) {
            // without the flag a hashed token would be read as the token itself and its feed would stop working
            // anyway: remove those feeds, their owners create new ones
            $this->addSql('DELETE FROM kimai2_planner_calendar_tokens WHERE hashed = 1');
            $table->dropColumn('hashed');
        }
    }
}
