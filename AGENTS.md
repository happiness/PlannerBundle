# PlannerBundle Agent Guide

Use this guide when working on the **PlannerBundle** plugin in `var/plugins/PlannerBundle`.

---

## Overview & Scope

- **Plugin Name**: `PlannerBundle` (`happiness/planner-bundle` or `KimaiPlugin\PlannerBundle`)
- **Description**: Lightweight weekly team capacity and workload planner for Kimai (inspired by Forecastapp). Allows team leads and users to plan, visualize, and balance workloads on a weekly timeline. Every planned activity belongs to a customer and optionally a project.
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
│   └── PlannedActivityController.php # REST API (GET/POST/PATCH/DELETE /api/planner/activities)
├── Controller/
│   └── WeeklyPlannerController.php    # Weekly planner routes, week switching, modal CRUD actions
├── DependencyInjection/
│   └── PlannerExtension.php          # Container configuration (prepends roles, sets, views)
├── Entity/
│   └── PlannedActivity.php           # Planned time block entity (user, customer, project, date range, daily hours, comments, color)
├── EventSubscriber/
│   ├── MenuSubscriber.php            # Injects "Planner" item into main navigation
│   └── PermissionSubscriber.php      # Registers planner permissions
├── Form/
│   ├── PlannedActivityEditForm.php    # Modal form for creating/editing planned activities
│   └── PlannedActivityApiForm.php     # API variant: ISO dates, no CSRF
├── Migrations/
│   ├── doctrine_migrations.yaml       # Plugin migration configuration
│   ├── Version20260909000000.php      # Initial planner schema (kimai2_planned_activities table)
│   ├── Version20260914000000.php      # Schema updates (recurring activities support)
│   └── Version20261019000000.php      # customer_id/project_id columns, title becomes nullable
├── Planner/
│   ├── PlannerUserProvider.php        # Resolves which users' plans the current user may see (web + API)
│   ├── WeeklyPlannerService.php       # Domain service aggregating week data, user rows, capacity & actuals
│   ├── WeeklyPlannerData.php          # Value object representing complete week grid
│   ├── WeeklyPlannerUserRow.php       # Per-user row containing daily breakdowns & contract summary
│   ├── WeeklyPlannerWeek.php          # One user's capacity figures for one calendar week of the displayed range
│   ├── WeeklyPlannerGroup.php         # One user's activities for one customer/project; builds merged day segments (one sub-row in the grid)
│   └── WeeklyPlannerDay.php           # Daily metrics (planned hours, actual hours, contract hours, status)
├── Repository/
│   └── PlannedActivityRepository.php  # PlannedActivity queries (date ranges, user filtering)
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
4. **Customer/Project Link & Legacy Rows**:
   - `customer_id` is nullable **in the database only** because rows created before the link existed have none; `PlannedActivity` validates `customer` as `NotNull`, so every save through the form or API requires one. `project` is optional and must belong to the customer.
   - `title` is a legacy column (nullable, no longer in the form). Use `getName()`: project name, else customer name, else legacy title.
   - Plugins are not loaded in Kimai's `test` environment, so plugin tests are unit tests (mocks / `TypeTestCase`); there is no HTTP-level API test. Core files must not be touched, which rules out registering the plugin for tests.
   - The API doc bundle cannot dump the plugin's form models from the CLI (core forms using `UserType` fail the same way); review the generated docs in the logged-in `/api/doc` UI after changing API attributes.
5. **Grid Layout (Forecast style)**:
   - Per user there is one collapsible summary row (avatar, name, capacity bar) followed by one sub-row per customer/project (`WeeklyPlannerUserRow::getGroups()`). Day cells only show hours; consecutive days of the same activity are merged via `colspan` (`WeeklyPlannerGroup::getSegments()`).
   - All of a user's rows share `data-user-id`; the toggle JS relies on that. Empty days in a sub-row open the create modal for that user/date with the row's customer/project pre-selected, the same parameters drag to create passes. Every user always gets one extra empty sub-row after the customer/project rows (also when there are none), so new planning can be dragged out; it has no `data-customer-id`/`data-project-id`.
6. **Multiple Weeks**:
   - The grid shows 1, 2, 4 (default) or 8 consecutive weeks (`WeeklyPlannerController::WEEK_OPTIONS`). The count comes from the `weeks` query parameter and is remembered in the session (`planner_weeks`), so it survives navigation and redirects after saving. The service clamps it to 1-8.
   - The range always starts on the Monday of the selected week; previous/next move by one week. Capacity bars are per calendar week (`WeeklyPlannerUserRow::getWeeks()`), while sub-row bars run continuously across week borders. With more than one week day columns shrink to 44px and headers show weekday + day number.
7. **Drag to Create**:
   - Empty day cells carry `data-planner-date`; dragging across empty cells of one row opens the create dialog with `date`, `end`, `user` and, for sub-rows, `customer`/`project` (read from the row's `data-customer-id`/`data-project-id`). The JS lives in `index.html.twig`, reuses Kimai's delegated `.modal-ajax-form` click handler by clicking a temporary link, and suppresses the click that follows `mouseup`. A plain click still opens the single-day dialog. Mouse only, no touch support.
   - `createActivity()` ignores an `end` before `date` and only pre-fills customer/project the user may `access`.
8. **Move and Resize**:
   - Activities with the edit permission carry `data-planner-move-url`/`-begin`/`-end`; a left/right handle (`data-planner-handle`) is rendered only where the activity really begins/ends. The JS maps the pointer to a day through the `th[data-planner-day]` headers (merged cells do not map to one day) and to a row through `tr.planner-row-details`.
   - `POST /planner/activity/{id}/move` (CSRF token `planner_move`) takes `begin`, `end` and optionally `user`, `customer`, `project`. The voter is checked before and after the changes, so planning cannot be moved to someone the user may not plan for. Hours per day are never changed. Only the single activity is moved, also when it is recurring.
9. **Isolated Database Migrations**:
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
| `view_other_planner` | View other team members in the planner grid | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
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
