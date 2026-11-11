# PlannerBundle Agent Guide

Use this guide when working on the **PlannerBundle** plugin in `var/plugins/PlannerBundle`.

---

## Overview & Scope

- **Plugin Name**: `PlannerBundle` (`happiness/planner-bundle` or `KimaiPlugin\PlannerBundle`)
- **Description**: Lightweight weekly team capacity and workload planner for Kimai (inspired by Forecastapp). Allows team leads and users to plan, visualize, and balance workloads on a weekly timeline. Planned work belongs to a customer and optionally a project; time off belongs to neither and reduces capacity.
- **Primary Capabilities**:
  - Weekly matrix grid overview showing planned activities and daily allocations (Monday through Sunday).
  - Capacity and workload calculations integrating with Kimai user working time contracts.
  - Visual status badges highlighting over-allocated, under-allocated, and balanced workdays.
  - Direct comparison of planned allocations against actual recorded timesheet hours.
  - Quick modal creation, editing, and deletion for planned time blocks.
  - REST API (`/api/planner/activities`) with full CRUD, filtering and recurrence support.
  - Configurable recurring activities spanning multiple weeks.
  - Fast week navigation (previous, today, next, and date jumping).
  - Performance-optimized User and Team toolbar filtering with single-team pre-selection.
  - Role-based permissions supporting both self-planning and team lead management.
- **Scope Boundary**: All plugin development, entities, migrations, templates, configuration, and tests must remain strictly isolated within `var/plugins/PlannerBundle/`. **Never modify Kimai core files** in `src/`, `templates/`, `config/`, or core `migrations/`.

---

## Stack & Requirements

- **Kimai Version**: `>= 2.60.0` (Kimai 2.x)
- **PHP Version**: `8.2` - `8.4` (DDEV default: `8.4`, PHP 8.4 compatible)
- **Framework**: Symfony 6.4 LTS, Twig, Doctrine ORM
- **Database Migrations**: Plugin-isolated Doctrine migrations via `Migrations/doctrine_migrations.yaml`
- **Frontend**: Bootstrap 5 with Tabler UI framework

---

## Repository Map

```text
var/plugins/PlannerBundle/
├── AGENTS.md                          # This agent guide
├── composer.json                      # Plugin metadata and dependencies
├── PlannerBundle.php                  # Bundle entry point (implements App\Plugin\PluginInterface)
├── README.md                          # Feature overview, setup, and permissions
├── phpstan.neon                       # Plugin-specific PHPStan configuration
├── .php-cs-fixer.dist.php             # Plugin-specific code style rules
├── API/
│   ├── PlannedActivityController.php # REST API (GET/POST/PATCH/DELETE /api/planner/activities)
│   └── PublicHolidayController.php   # REST API (GET /api/planner/holidays)
├── Calendar/
│   ├── CalendarFeedBuilder.php        # iCalendar (RFC 5545) output: all-day events, escaping, line folding
│   ├── CalendarFeedSettings.php       # The organisation's feed settings: comments, personal choice, hashing
│   └── CalendarFeedTokens.php         # Create, renew, remove and look up feed tokens (Entity/CalendarFeedToken), hashed or plain
├── Controller/
│   ├── CalendarFeedSettingsController.php # /{_locale}/planner/calendar: show, renew, switch off the feed address
│   ├── ReservationController.php      # Administration > Reserved time: list, create, edit, archive, delete, order
│   └── WeeklyPlannerController.php    # Weekly planner routes, week switching, modal CRUD actions
├── DependencyInjection/
│   └── PlannerExtension.php          # Container configuration (prepends roles, sets, views)
├── Feed/
│   └── CalendarFeedController.php     # Public GET /planner-calendar/{token}.ics (no login, token is the credential)
├── Entity/
│   ├── CalendarFeedToken.php          # One calendar feed token per user (own table, kept out of audited preferences)
│   ├── PlannedActivity.php           # Planned time block entity (user, customer, project, date range, daily hours, comments, color)
│   └── Reservation.php               # Reserved time: name, colour, archived, position (kimai2_planner_reservations)
├── EventSubscriber/
│   ├── CalendarFeedHashingListener.php # Hashes plain feed tokens when "Store feed addresses hashed" is saved as on
│   ├── CalendarFeedSettingsSubscriber.php # System settings: comments in feeds, personal choice, hashed tokens
│   ├── HolidayPreferenceSubscriber.php # Per-user holiday calendar preference (contract permission)
│   ├── HolidaySettingsSubscriber.php # System settings: default holiday calendar, observance days off
│   ├── MenuSubscriber.php            # Injects "Planner" item into main navigation
│   └── PermissionSubscriber.php      # Registers planner permissions
├── Form/
│   ├── PlannedActivityEditForm.php    # Modal form for creating/editing planned activities
│   ├── PlannedActivityApiForm.php     # API variant: ISO dates, no CSRF
│   ├── ReservationForm.php            # Name and colour of a reservation
│   └── Type/                          # HolidayCalendarType (+Preference), HolidayObservancesType (settings and preference fields)
├── Holiday/
│   ├── HolidayProviderInterface.php   # Which public holidays a user has off; decorate it to replace the source
│   ├── YasumiHolidayProvider.php      # Default provider: user preference, else system setting; Yasumi from Kimai core
│   ├── HolidayCalendars.php           # Calendar list and names, observance days, setting/preference keys
│   └── PublicHoliday.php              # One holiday: date, name, calendar, type
├── Migrations/
│   ├── doctrine_migrations.yaml       # Plugin migration configuration
│   ├── Version20260909000000.php      # Initial planner schema (kimai2_planned_activities table)
│   ├── Version20260914000000.php      # Schema updates (recurring activities support)
│   ├── Version20261019000000.php      # customer_id/project_id columns, title becomes nullable
│   ├── Version20261102000000.php      # kimai2_planner_calendar_tokens (calendar feed tokens)
│   ├── Version20261102120000.php      # tentative flag
│   ├── Version20261110180000.php      # calendar feed tokens: hashed flag and the user's choice on comments
│   └── Version20261111000000.php      # kimai2_planner_reservations and the reservation_id foreign key
├── Planner/
│   ├── PlannerUserProvider.php        # Resolves which users' plans the current user may see (web + API)
│   ├── TentativeConfirmer.php         # "Confirm all for this project": ongoing/future tentative plans the user may edit (web + API)
│   ├── TentativeConfirmation.php      # What a confirmation changes: activities, skipped ones, people and period
│   ├── WeeklyPlannerService.php       # Domain service aggregating week data, user rows, capacity & actuals
│   ├── WeeklyPlannerData.php          # Value object representing complete week grid
│   ├── WeeklyPlannerUserRow.php       # Per-user row containing daily breakdowns & contract summary
│   ├── WeeklyPlannerWeek.php          # One user's capacity figures for one calendar week of the displayed range
│   ├── WeeklyPlannerGroup.php         # One user's activities for one customer/project; builds merged day segments (one sub-row in the grid)
│   └── WeeklyPlannerDay.php           # Daily metrics (planned hours, actual hours, contract hours, status)
├── Repository/
│   ├── PlannedActivityRepository.php  # PlannedActivity queries (date ranges, user filtering)
│   └── ReservationRepository.php      # Reservations in order, the selectable ones, planning counts, reordering
├── Resources/
│   ├── config/
│   │   ├── routes.yaml                # Plugin route definitions (/planner, /planner/week/{date}, etc.)
│   │   └── services.yaml              # Dependency injection service wiring
│   ├── translations/
│   │   ├── messages.en.xlf            # English translation catalogue
│   │   └── messages.sv.xlf            # Swedish translation catalogue
│   └── views/
│       ├── index.html.twig            # Main weekly planner matrix view & toolbar
│       ├── form.html.twig             # Modal form template for planned activities
│       ├── confirm_tentative.html.twig # Modal question before confirming a project's tentative planning
│       └── delete.html.twig           # Modal deletion confirmation template
├── tests/                             # Comprehensive PHPUnit test suite mirroring src/
│   ├── Controller/
│   ├── DependencyInjection/
│   ├── Entity/
│   ├── EventSubscriber/
│   ├── Form/
│   ├── Planner/
│   ├── Repository/
│   └── Voter/
└── Voter/
    └── PlannedActivityVoter.php       # Authorization voter for view/edit/delete activity actions
```

---

## Key Technical Decisions & Pitfalls

1. **Permission Registration via `permissions.roles`**:
   - In `PlannerExtension::prepend()`, default role permissions **must be registered directly under `permissions.roles`**, NOT under `permissions.maps`.
   - *Why*: Symfony's configuration merger overrides array keys for `permissions.maps` due to `useAttributeAsKey('key')` in Kimai core's `Configuration`, which strips plugin map definitions during container compilation. Registering under `permissions.roles` preserves assignments across all core and plugin roles.
   - Retain `permissions.sets` (`PLANNER`, `PLANNER_OTHER`) for custom role administration in the UI.
2. **Weekly Navigator Header & Date Calculations**:
   - In `Resources/views/index.html.twig`, the central week number indicator in `.week-picker-btn-group` must format the active week dynamically using `currentWeek|date('W')` (interpolated into `stats.workingTimeWeekShort`).
   - Month and year labels use `{{ currentWeek|month_name(true) }}`.
   - The dedicated "This week" button (`planner.this_week`) links directly to `todayWeek`.
3. **Optimized Contract & Workload Computations ($O(N_{\text{filtered}})$)**:
   - Calculating working time contracts and daily work hours (`getWorkHoursForDay()`) for all 7 days of the week is computationally heavy.
   - Filter users at the database query level (`UserRepository::getUsersForQuery`) using toolbar team and user filters before instantiating contract calculators.
   - If the logged-in user belongs to exactly one team (`count($currentUser->getTeams()) === 1`), pre-select that team by default in the toolbar query to minimize initial page load latency.
   - Days off don't count: `WeeklyPlannerDay::counts()` gives work 0 hours on a day without expected hours (a weekend, a public holiday), so a planning stays one row however many days off it crosses. The exception is work whose every day is a day off (weekend work), which `WeeklyPlannerService::countWorkPlannedOnDaysOff()` marks with `countOnDayOff()`; it only asks the contract about days outside the shown range for activities with no working day on screen. `getHoursOf()` applies the rule, so the day, the group rows and the free hours all agree. Time off is not affected: it is already capped at the expected hours.
4. **Customer/Project Link & Legacy Rows**:
   - `customer_id` is nullable **in the database only** because rows created before the link existed have none; `PlannedActivity` validates `customer` as `NotNull`, so every save through the form or API requires one. `project` is optional and must belong to the customer.
   - `title` is a legacy column (nullable, no longer in the form). Use `getName()`: project name, else customer name, else legacy title.
   - Time off (`time_off`) is the exception: `validateRequiredFields()` asks for neither customer nor hours, and `setTimeOff(true)` drops customer, project, title and tentative (the switch hides with the other work fields). `hours_per_day` is nullable for it: `null` means the whole contract day (`WeeklyPlannerDay::getHoursOf()`). Time off never counts as planned; `WeeklyPlannerDay::getCapacityHours()` is the contract hours minus time off, and every percentage and allocation state (day, week, user row) is measured against capacity, not contract hours. Time off is one group (`WeeklyPlannerGroup::TIME_OFF_KEY`), listed first, whose row carries `data-planner-off` so dragging in it creates time off (`?off=1`); moving time off ignores the target row's customer/project.
   - Plugins are not loaded in Kimai's `test` environment, so plugin tests are unit tests (mocks / `TypeTestCase`); there is no HTTP-level API test. Core files must not be touched, which rules out registering the plugin for tests.
   - The API doc bundle cannot dump the plugin's form models from the CLI (core forms using `UserType` fail the same way); review the generated docs in the logged-in `/api/doc` UI after changing API attributes.
5. **Grid Layout (Forecast style)**:
   - Per user there is one collapsible summary row (avatar, name, capacity bar) followed by one sub-row per customer/project (`WeeklyPlannerUserRow::getGroups()`). Day cells only show hours; consecutive days of the same activity are merged via `colspan` (`WeeklyPlannerGroup::getSegments()`). Directly under the summary row a `tr.planner-row-days` (no `planner-row` class or `data-user-id`, so the toggle, drag and move JS ignore it) shows each day's planned hours against capacity (contract minus time off) and percentage from `WeeklyPlannerDay`, hatched when the whole day is off; with more than one week only the percentage, the hours are in the cell's `title`.
   - All of a user's rows share `data-user-id`; the toggle JS relies on that. Empty days in a sub-row open the create modal for that user/date with the row's customer/project pre-filled. Every user always gets one extra empty sub-row after the customer/project rows (also when there are none), so new planning can be dragged out; it has no `data-customer-id`/`data-project-id`. Its label cell holds an "Add project" / "Add another project" button that opens the same create dialog for today, or the first shown day when today is not in range.
6. **Multiple Weeks**:
   - The grid shows 1, 2, 4 (default) or 8 consecutive weeks (`WeeklyPlannerController::WEEK_OPTIONS`). The count comes from the `weeks` query parameter and is remembered in the session (`planner_weeks`), so it survives navigation and redirects after saving. The service clamps it to 1-8.
   - The range always starts on the Monday of the selected week; previous/next move by one week. Capacity bars are per calendar week (`WeeklyPlannerUserRow::getWeeks()`), while sub-row bars run continuously across week borders. With more than one week day columns shrink to 44px and headers show weekday + day number.
7. **Drag to Create**:
   - Empty day cells carry `data-planner-date`; dragging across empty cells of one row opens the create dialog with `date`, `end`, `user` and, for sub-rows, `customer`/`project` (read from the row's `data-customer-id`/`data-project-id`). The JS lives in `index.html.twig`, reuses Kimai's delegated `.modal-ajax-form` click handler by clicking a temporary link, and suppresses the click that follows `mouseup`. A plain click still opens the single-day dialog. Mouse only, no touch support.
   - Empty cells on a day without contract hours (`WeeklyPlannerDay::isWorkingDay()`) also carry `data-planner-non-working`, unless the user has no working day in the whole period. Clicking such a cell, or dragging over cells that are all such cells, first asks through Kimai's `alert.question()` (`window.confirm` without Kimai's JS); the questions come from the table's `data-planner-day-off-question`/`data-planner-days-off-question` and the summary row's `data-planner-user-name`. A capture-phase click listener on `document` intercepts the cell link before Kimai's `body` handler.
   - `createActivity()` ignores an `end` before `date` and only pre-fills customer/project the user may `access`.
8. **Move and Resize**:
   - Activities with the edit permission carry `data-planner-move-url`/`-begin`/`-end`; a left/right handle (`data-planner-handle`) is rendered only where the activity really begins/ends. The JS maps the pointer to a day through the `th[data-planner-day]` headers (merged cells do not map to one day) and to a row through `tr.planner-row-details`.
   - `POST /planner/activity/{id}/move` (CSRF token `planner_move`) takes `begin`, `end` and optionally `user`, `customer`, `project`. The voter is checked before and after the changes, so planning cannot be moved to someone the user may not plan for. Hours per day are never changed. Only the single activity is moved, also when it is recurring.
   - Overlaps are only refused where they are a slip: the move action (move and resize) returns `planner.overlapping` (validators domain) with a 422 when `PlannedActivityRepository::countOverlapping()` finds another activity of the same user and customer/project, with the same tentative status, on a shared day. The dialog and the API have no such rule: planning twice is typed there on purpose, such as 2 extra hours on one day of a week already planned. Existing overlaps are visible instead: `WeeklyPlannerGroup::isPlannedTwiceOn()` marks such days in the grid (`.planner-twice`, confirmed and tentative counted apart). Time off and legacy activities without a customer are never compared. `isJoinableWith()` refuses two instances of a seven-day weekly series, which also sit side by side.
   - Split and join (same token): `POST /planner/activity/{id}/split` with `date` (the first day of the second part) copies everything but the dates into a new activity, same recurrence group included, and saves both in one flush. `POST /planner/activity/{id}/join/{other}` merges `other` back when `PlannedActivity::isJoinableWith()` holds (it starts the day after, and user, customer, project, title, hours, time off, tentative, colour, comment and recurrence group are equal); it needs edit on the first and delete on the second. Bars carry `data-planner-split-url` (more than one day) and `data-planner-join-url`/`-day` (from `WeeklyPlannerGroup::getJoinableAfter()`, on the segment where the activity ends). The JS shows a fixed-position marker (scissors or link) when the pointer is within a few pixels of such a border; split and join undo each other, so neither asks for confirmation.
9. **Public Holidays**:
   - `WeeklyPlannerService` asks the optional `HolidayProviderInterface` for each user's holidays in the range; a holiday sets that day's expected hours to 0 and is attached to the `WeeklyPlannerDay` (`getHoliday()`), so capacity, `isWorkingDay()` and everything built on them treat it as a day off.
   - Calendars are Yasumi's ISO 3166 codes (`SE`, `GB-ENG`). The user preference `planner_holiday_calendar` (empty: organisation default, `none`: no holidays) beats the system setting `planner.holiday_calendar`. Types `official` and `bank` are days off; `observance` days only when their Yasumi key is in `planner.holiday_observances` (comma separated, system settings hold one value). Do not use core's `public_holiday_group` preference: it belongs to Kimai's paid work contract plugin.
   - The preference is only enabled for those granted `contract` on the user. Core renders a disabled preference as a `HiddenType` with the same options, so a preference must not pass options only its own form type knows: that is why `HolidayCalendarPreferenceType` exists instead of an option on `HolidayCalendarType`.
   - Names come in the current request language, preferring the calendar's country variant (`en_GB` for an English reader of `GB-ENG`, so "Boxing Day"). Where Yasumi has no translation it would return the key, so `HolidayCalendars::getHolidayName()` falls back to any translation it has.
   - The template marks holiday cells with `planner-holiday` (striped tint) on every row of that user; the day summary cell shows the name truncated, with name and calendar in the tooltip.
10. **Calendar Feed**:
   - `Feed/` is imported in `routes.yaml` without the `/{_locale}` prefix, so the feed needs no login; the 64-hex token is the only credential. Anything else added to `Feed/` is public too. A plain controller class there needs `#[AsController]`, or Symfony cannot call it (only `AbstractController` subclasses are registered automatically).
   - The feed lives outside the `/{_locale}/` prefix, so Kimai's `access_control` rule that requires `ROLE_USER` there (a real pattern since Kimai 2.72, kimai/kimai#6316; before that a literal regex that matched nothing) does not cover it. Every controller under `/{_locale}/` in `Controller/` must still carry its own `#[IsGranted]`, as `CalendarFeedSettingsController` does (`view_planner` and `calendar_feed_planner`).
   - Tokens live in their own table (`Entity\CalendarFeedToken`, `kimai2_planner_calendar_tokens`, migrations `Version20261102000000` and `Version20261110180000`), never in a user preference: audit plugins version every preference value (renewed tokens would stay readable in the audit log) and users can write any existing preference through `PATCH /api/users/{id}/preferences`.
   - `CalendarFeedSettings` reads the three system settings (`planner.calendar_comments`, `planner.calendar_comments_user`, `planner.calendar_hash_tokens`); unset means comments out, no personal choice, hashing on. `CalendarFeedSettingsSubscriber` adds them to the `planner` section after `HolidaySettingsSubscriber` creates it.
   - With hashing on, `token` holds the SHA-256 of the token and `hashed` is set; `findFeed()` looks up both forms and hashes a plain token it finds. Plain tokens are also hashed in one statement (`CalendarFeedTokens::HASH_PLAIN_TOKENS_SQL`, MySQL `SHA2()`) by `Version20261110180000::postUp()` unless hashing is switched off, and by `CalendarFeedHashingListener` (a Doctrine entity listener on core's `Configuration`, since Kimai dispatches no event when settings are saved) whenever the setting is saved as on. A token stored hashed is only known in the request that creates it: the settings controller passes it to the next page view once through the session. `include_comments` is the user's own choice (`null` follows the organisation), kept only when it differs from the organisation's.
   - The feed answers 404 for an unknown token, a disabled user and a user whose roles lack `view_planner` or `calendar_feed_planner` (`RolePermissionManager::hasRolePermission()`; there is no security token on this request, so voters cannot be used).
   - Events are all-day (`DTEND` is the exclusive day after `end`), `TRANSP:TRANSPARENT`, UID `planner-activity-{id}@{host}`. Labels use the feed owner's language (`User::getLanguage()`), not the request's. A feed with no planning has no events, which Google and Outlook accept.
11. **Reserved Time**:
   - `PlannedActivity::$reservation` is a ManyToOne to `Reservation` (`reservation_id`, `onDelete: RESTRICT`): one with planning can only be archived. `isProjectWork()` (neither time off nor reserved time) is the one check for "has a customer, project, title and tentative state": validation asks for a customer only then, tentative only exists then, and `clearProjectWork()` drops those fields for both time off and reservations. `setReservation()` also drops colour and time off; `setTimeOff(true)` drops the reservation. `getName()` and `getColorSafe()` fall back to the reservation, so templates, the delete dialog and feeds need no special case.
   - `copyPlanningTo()` copies every field but dates and recurrence group; removing a day, splitting, repeating and "all future" edits use it instead of their own setter lists. `isJoinableWith()` also requires the same reservation, and the move action's overlap check (`countOverlapping()`) compares the reservation instead of customer and project (`planner.overlapping_reservation`).
   - The form adds a `reservation` ChoiceType (Reservation objects, value = ID) when there are selectable reservations or the planning already has one (kept even when archived). It is mapped after customer and project and before `timeOff`, and the POST_SUBMIT listener re-applies it, like time off. JS hides `.planner-project-field` (customer, project, colour, tentative, suggestions) while one is chosen.
   - `WeeklyPlannerGroup` keys reservations as `r<id>`; `WeeklyPlannerUserRow::getGroups()` sorts time off, then customers and projects, then reservations by position. Rows of reservations that are not archived carry `data-reservation-id`, which click/drag-create (`reservation` query parameter) and move (`reservation` POST field, 400 unless selectable) use; a drop on a project row clears the reservation.
12. **Isolated Database Migrations**:
   - Always run and test migrations using the bundle's dedicated configuration file:
     `bin/console doctrine:migrations:migrate --configuration=var/plugins/PlannerBundle/Migrations/doctrine_migrations.yaml --no-interaction`

---

## Permissions & Access Control

| Permission | Description | Default Roles |
| :--- | :--- | :--- |
| `view_planner` | View the planner menu item and weekly page | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `create_planner` | Create planned activities for self | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `edit_planner` | Edit own planned activities | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `delete_planner` | Delete own planned activities | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `view_other_planner` | View the members of the teams you lead in the planner grid | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `view_all_planner` | View everyone's plans, read-only (provider and `VIEW` vote only; rows you cannot plan for offer no new planning) | `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `edit_other_planner` | Create, edit, and delete activities for others | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |

The API additionally requires Kimai's core `API` permission. It reuses the same planner permissions and `PlannedActivityVoter`.

---

## Validation & Quality Assurance

Always validate changes using containerized DDEV or host CLI commands:

```bash
# Run full PlannerBundle PHPUnit test suite
ddev exec vendor/bin/phpunit var/plugins/PlannerBundle/tests/
# (or on host): vendor/bin/phpunit var/plugins/PlannerBundle/tests/

# Run static analysis (PHPStan) for Planner
ddev exec ./phpstan.sh Planner
# (or on host): ./phpstan.sh Planner

# Run code style fixer (PHP-CS-Fixer) for Planner
ddev exec ./php-cs-fixer.sh Planner
# (or on host): ./php-cs-fixer.sh Planner

# Run database migrations
ddev exec bin/console doctrine:migrations:migrate --configuration=var/plugins/PlannerBundle/Migrations/doctrine_migrations.yaml --no-interaction

# Clear & rebuild cache
ddev exec bin/console cache:clear
```

---

## Coding Conventions

- Add `declare(strict_types=1);` at the top of every PHP file.
- Use native PHP 8.4 features (constructor property promotion, attributes, match expressions).
- Enforce strict comparisons (`===`, `!==`).
- Every new or modified PHP class in `var/plugins/PlannerBundle/` must have a corresponding test in `var/plugins/PlannerBundle/tests/`.
- Keep English (`messages.en.xlf`) and Swedish (`messages.sv.xlf`) translations synchronized whenever translation keys change.
- Follow Bootstrap 5 and Tabler UI design patterns.
