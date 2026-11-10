<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Tests\Calendar;

use App\Configuration\ConfigLoaderInterface;
use App\Configuration\SystemConfiguration;
use KimaiPlugin\PlannerBundle\Calendar\CalendarFeedSettings;
use PHPUnit\Framework\TestCase;

class CalendarFeedSettingsTest extends TestCase
{
    /**
     * @param array<string, string|bool|null> $stored as the database hands them over
     */
    private function createSettings(array $stored): CalendarFeedSettings
    {
        $loader = $this->createStub(ConfigLoaderInterface::class);
        $loader->method('getConfigurations')->willReturn($stored);

        return new CalendarFeedSettings(new SystemConfiguration($loader, []));
    }

    public function testUnsetMeansTheSafeChoices(): void
    {
        $settings = $this->createSettings([]);

        $this->assertFalse($settings->includeCommentsByDefault());
        $this->assertFalse($settings->mayUsersChooseComments());
        $this->assertTrue($settings->hashTokens());
        // a choice someone made while they still could is ignored
        $this->assertFalse($settings->includeComments(true));
    }

    public function testTheOrganisationCanIncludeCommentsAndStopHashing(): void
    {
        $settings = $this->createSettings([
            CalendarFeedSettings::SETTING_COMMENTS => CalendarFeedSettings::COMMENTS_INCLUDE,
            CalendarFeedSettings::SETTING_HASH_TOKENS => '0',
        ]);

        $this->assertTrue($settings->includeCommentsByDefault());
        $this->assertTrue($settings->includeComments(null));
        // people may not choose here, so an old choice of their own does not count
        $this->assertTrue($settings->includeComments(false));
        $this->assertFalse($settings->hashTokens());
    }

    public function testPeopleMayChooseWhenAllowed(): void
    {
        $settings = $this->createSettings([
            CalendarFeedSettings::SETTING_COMMENTS => CalendarFeedSettings::COMMENTS_EXCLUDE,
            CalendarFeedSettings::SETTING_COMMENTS_USER_CHOICE => '1',
        ]);

        $this->assertTrue($settings->mayUsersChooseComments());
        $this->assertTrue($settings->includeComments(true));
        $this->assertFalse($settings->includeComments(false));
        // no choice of their own: the organisation's
        $this->assertFalse($settings->includeComments(null));
    }
}
