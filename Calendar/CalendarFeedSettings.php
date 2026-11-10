<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PlannerBundle\Calendar;

use App\Configuration\SystemConfiguration;

/**
 * What the organisation allows in calendar feeds, set under System > Settings > Weekly Planner.
 *
 * Unset values mean the safe choice: comments stay out of the feed, people may not change that
 * for their own feed, and tokens are stored hashed.
 *
 * @final not final for mocking in tests
 */
class CalendarFeedSettings
{
    public const SETTING_COMMENTS = 'planner.calendar_comments';
    public const SETTING_COMMENTS_USER_CHOICE = 'planner.calendar_comments_user';
    public const SETTING_HASH_TOKENS = 'planner.calendar_hash_tokens';

    public const COMMENTS_EXCLUDE = 'exclude';
    public const COMMENTS_INCLUDE = 'include';

    public function __construct(private readonly SystemConfiguration $configuration)
    {
    }

    /**
     * Whether the organisation puts comments in feeds, unless a person may choose for their own.
     */
    public function includeCommentsByDefault(): bool
    {
        return $this->configuration->find(self::SETTING_COMMENTS) === self::COMMENTS_INCLUDE;
    }

    public function mayUsersChooseComments(): bool
    {
        return self::isOn($this->configuration->find(self::SETTING_COMMENTS_USER_CHOICE), false);
    }

    /**
     * Whether a feed carries comments: the person's own choice where they may make one, else the organisation's.
     */
    public function includeComments(?bool $userChoice): bool
    {
        if ($userChoice !== null && $this->mayUsersChooseComments()) {
            return $userChoice;
        }

        return $this->includeCommentsByDefault();
    }

    public function hashTokens(): bool
    {
        return self::isOn($this->configuration->find(self::SETTING_HASH_TOKENS), true);
    }

    /**
     * Settings come back from the database as strings ("1", "0"), from the form as booleans.
     */
    private static function isOn(string|int|bool|float|null $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return \in_array($value, [true, 1, '1', 'true'], true);
    }
}
