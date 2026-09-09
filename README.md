# PlannerBundle for Kimai

**PlannerBundle** is a lightweight weekly team capacity planner plugin for [Kimai](https://www.kimai.org/), inspired by tools like Forecastapp. It allows teams and team leaders to visually plan and balance workloads on a weekly timeline without the administrative friction of linking planned activities to customers or projects.

---

## Features

- **Weekly Matrix Grid**: Clear team overview showing planned workload across the week (Monday through Sunday).
- **Capacity & Workload Balancing**:
  - Automatically calculates expected capacity from each user's working time contracts.
  - Highlights over-allocated, under-allocated, and balanced days with clear visual badges.
- **Actual vs Planned Hours**: Directly compares planned allocations against actual recorded timesheet hours.
- **Lightweight Scheduling**: Plan time blocks with custom titles, daily hours, date ranges, comments, and color badges.
- **Quick Modal Editing**: Create, update, or delete planned blocks seamlessly via modal dialogs.
- **Date Navigation**: Fast week switcher controls (`Previous Week`, `Today`, `Next Week`, and date picker jump).
- **Fine-Grained Permissions**: Full role-based access control supporting self-planning and team lead management.

---

## Requirements

- Kimai `>= 2.0.0`
- PHP `>= 8.2`

---

## Installation

### Standard Installation

1. **Clone or copy** the plugin into Kimai's plugin directory:
   ```bash
   # Destination folder: var/plugins/PlannerBundle
   cd /path/to/kimai/var/plugins
   git clone <repository-url> PlannerBundle
   ```

2. **Run database migrations**:
   ```bash
   bin/console kimai:plugins --install
   ```
   *Alternative direct migration command:*
   ```bash
   bin/console doctrine:migrations:migrate --configuration=var/plugins/PlannerBundle/Migrations/doctrine_migrations.yaml --no-interaction
   ```

3. **Clear the cache**:
   ```bash
   bin/console cache:clear
   ```

---

### Docker & DDEV Installation

If you are running Kimai inside a Docker / DDEV environment:

1. **Copy the plugin files** into `var/plugins/PlannerBundle`.

2. **Execute migrations inside the container**:
   ```bash
   ddev exec bin/console kimai:plugins --install
   ```

3. **Clear container cache**:
   ```bash
   ddev exec bin/console cache:clear
   ```

---

## Permissions & Roles

The plugin registers dedicated permissions that integrate seamlessly into Kimai's role and permission management:

| Permission | Description | Default Roles |
| :--- | :--- | :--- |
| `view_planner` | View the weekly planner menu and page | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `create_planner` | Create planned activities for self | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `edit_planner` | Edit own planned activities | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `delete_planner` | Delete own planned activities | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `view_other_planner` | View other team members in the weekly planner | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `edit_other_planner` | Create, edit, and delete activities for other users | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |

Permissions can be customized anytime via the Kimai UI under **System > Role permissions**.

---

## Development & Testing

Run tests and code checks using the provided scripts:

```bash
# Run PHPUnit test suite
vendor/bin/phpunit var/plugins/PlannerBundle/tests/

# Run static analysis (PHPStan)
./phpstan.sh Planner

# Run code style fixer (PHP-CS-Fixer)
./php-cs-fixer.sh Planner
```

*(Prepend `ddev exec` if executing within DDEV)*

---

## License

This bundle is licensed under the [AGPL-3.0-or-later License](https://www.gnu.org/licenses/agpl-3.0.html).
