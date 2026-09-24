# PlannerBundle Agent Guide

Use this guide when working on the **PlannerBundle** plugin in `var/plugins/PlannerBundle`.

---

## Overview & Scope

- **Plugin Name**: `PlannerBundle` (`happiness/planner-bundle` or `KimaiPlugin\PlannerBundle`)
- **Description**: Lightweight weekly team capacity and workload planner for Kimai (inspired by Forecastapp). Allows team leads and users to plan, visualize, and balance workloads on a weekly timeline without mandatory linking to customers or projects.
- **Primary Capabilities**:
  - Weekly matrix grid overview showing planned activities and daily allocations (Monday through Sunday).
  - Capacity and workload calculations integrating with Kimai user working time contracts.
  - Visual status badges highlighting over-allocated, under-allocated, and balanced workdays.
  - Direct comparison of planned allocations against actual recorded timesheet hours.
  - Quick modal creation, editing, and deletion for planned time blocks.
  - Configurable recurring activities spanning multiple weeks.
  - Fast week navigation (previous, today, next, and date jumping).
  - Performance-optimized User and Team toolbar filtering with single-team pre-selection.
  - Role-based permissions supporting both self-planning and team lead management.
- **Scope Boundary**: All plugin development, entities, migrations, templates, configuration, and tests must remain strictly isolated within `var/plugins/PlannerBundle/`. **Never modify Kimai core files** in `src/`, `templates/`, `config/`, or core `migrations/`.

---

## Stack & Requirements

- **Kimai Version**: `>= 2.0.0` (Kimai 2.x)
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
├── Controller/
│   └── WeeklyPlannerController.php    # Weekly planner routes, week switching, modal CRUD actions
├── DependencyInjection/
│   └── PlannerExtension.php          # Container configuration (prepends roles, sets, views)
├── Entity/
│   └── PlannedActivity.php           # Planned time block entity (user, date range, daily hours, comments, color)
├── EventSubscriber/
│   ├── MenuSubscriber.php            # Injects "Planner" item into main navigation
│   └── PermissionSubscriber.php      # Registers planner permissions
├── Form/
│   └── PlannedActivityEditForm.php    # Modal form for creating/editing planned activities
├── Migrations/
│   ├── doctrine_migrations.yaml       # Plugin migration configuration
│   ├── Version20260909000000.php      # Initial planner schema (kimai2_planned_activities table)
│   └── Version20260914000000.php      # Schema updates (recurring activities support)
├── Planner/
│   ├── WeeklyPlannerService.php       # Domain service aggregating week data, user rows, capacity & actuals
│   ├── WeeklyPlannerData.php          # Value object representing complete week grid
│   ├── WeeklyPlannerUserRow.php       # Per-user row containing daily breakdowns & contract summary
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
4. **Isolated Database Migrations**:
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
