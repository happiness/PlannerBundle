# PlannerBundle for Kimai

**PlannerBundle** is a lightweight weekly team capacity planner plugin for [Kimai](https://www.kimai.org/), inspired by tools like Forecastapp. It allows teams and team leaders to visually plan and balance workloads on a weekly timeline. Planned work is linked to a customer and, optionally, a project; time off marks a user as not available.

---

## Features

- **Weekly Matrix Grid**: Clear team overview showing planned workload across the week (Monday through Sunday).
- **Capacity & Workload Balancing**:
  - Automatically calculates expected capacity from each user's working time contracts.
  - Highlights over-allocated, under-allocated, and balanced days with clear visual badges.
- **Public Holidays**: Each user's public holidays count as days off, named in their day cell (see [Public holidays](#public-holidays)).
- **Days Off Don't Count**: A planning across days the user does not work takes no hours on them. Friday to Tuesday at 4 hours a day is 12 hours, not 20, and stays one planning. Days off are days without contract hours, such as weekends and public holidays. Planning that covers only days off, such as weekend work, was planned there on purpose and counts in full.
- **Actual vs Planned Hours**: Directly compares planned allocations against actual recorded timesheet hours.
- **Lightweight Scheduling**: Plan time blocks for a customer (and optionally a project) with daily hours, date ranges, comments, and color badges.
- **Time Off**: Mark a user as not available with the "Time off" switch in the planning dialog, or by dragging in the user's "Time off" row. Time off has no customer or project and is not counted as planned work: its hours are taken off the day's capacity, so 4 hours off on an 8 hour day leaves 4 hours to plan. Leave the hours empty to take the whole day off, whatever the user's contract expects for that day. Why someone is away stays with the absence they record in Kimai.
- **Reserved Time**: Hold hours in someone's planning for something that is not a customer or project, such as a daily buffer or tech leading, from a list admins keep under *Administration > Reserved time*. It is planned and counted like project work, but nobody can log time to it (see [Reserved time](#reserved-time)).
- **Drag to Plan**: Drag across empty day cells of a row to create a planning spanning several days. A transparent preview block is shown while dragging and stays visible while the dialog is open.
- **Move and Resize**: Drag a planned activity to another day or row, or drag its left/right handle to extend or shorten it. A move or resize that would put the same project twice on one of the user's days is refused, since that is usually a slip of the mouse. Planning it twice on purpose in the dialog or through the API still works, and such days get a small warning mark in the grid.
- **Split and Join**: Hover the border between two days of a planning and click the scissors to split it into two. Two side-by-side parts that are the same planning show a link icon on the gap between them; a click joins them again.
- **Days off Confirmation**: Planning only on days the user does not work by their contract asks for confirmation first.
- **Shortcuts**: Frequently used projects (with their customer) are offered as shortcuts when creating a planning, unless the dialog was opened from a project's row and already has it selected.
- **Multiple Weeks**: Show 1, 2, 4 (default) or 8 weeks at once; capacity is summarized per week.
- **Tentative Planning**: Mark planning that isn't confirmed yet, such as work for a deal that isn't signed. Tentative blocks are faded with a dashed border, and their hours are shown on top of the confirmed capacity ("+ 8 hrs tentative") without changing its colour. A "Planning" filter shows all, confirmed only or tentative only (the capacity always counts everything; "tentative only" also hides users without tentative work). "Confirm all for this project" (on the project row or in the dialog) confirms the ongoing and future tentative planning of a project for everyone you may edit, after showing how many plans, people and which period it changes.
- **Who's Free**: "Order by" in the search offers "Most free hours": the people with the most contract hours left unplanned across the shown weeks, from today on, come first, overbooked people last, and each name shows its free hours ("16h free" or "4h over"). Switch to one week to ask about a single week. It is kept in the URL and can be bookmarked like the other filters.
- **Calendar Feed**: Subscribe to your own planning in Google Calendar, Outlook or Apple Calendar through a private address (see [Calendar feed](#calendar-feed)).
- **REST API**: Create, read, update and delete planned activities via `/api/planner/activities` (see the Kimai API docs under the "Planner" tag).
- **Quick Modal Editing**: Create, update, or delete planned blocks seamlessly via modal dialogs.
- **Date Navigation**: Fast week switcher controls (`Previous Week`, `Today`, `Next Week`, and date picker jump).
- **Fine-Grained Permissions**: Full role-based access control supporting self-planning and team lead management.

---

## Requirements

- Kimai `>= 2.60.0`
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
| `view_other_planner` | View the members of the teams you lead in the weekly planner | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `view_all_planner` | View everyone's plans, read-only: planning for others still needs `edit_other_planner` and leading their team (or `view_all_data`) | `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `manage_reservations_planner` | Manage the reserved time list (*Administration > Reserved time*) | `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `edit_other_planner` | Create, edit, and delete activities for other users | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |
| `calendar_feed_planner` | Subscribe to your own planning as a calendar feed, together with `view_planner` (see [Calendar feed](#calendar-feed)) | `ROLE_USER`, `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` |

Permissions can be customized anytime via the Kimai UI under **System > Role permissions**.

---

## Extending

Other plugins can leave users out of the planner, for example accounts that hold time but are not people. Listen to `KimaiPlugin\PlannerBundle\Event\PlannerUsersEvent`: it carries the users the current user is about to see, already limited to what they may see, and `removeUser()` takes one out of the planner grid and the planner API.

```php
#[AsEventListener]
public function __invoke(PlannerUsersEvent $event): void
{
    foreach ($event->getUsers() as $user) {
        if ($this->isNotAPerson($user)) {
            $event->removeUser($user);
        }
    }
}
```

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

## REST API

All endpoints require an API token and the `view_planner` permission; write operations additionally respect `create_planner`, `edit_planner` and `delete_planner` as well as the `*_other_planner` permissions for other users' plans.

| Method | Path | Description |
| :--- | :--- | :--- |
| `GET` | `/api/planner/activities` | List activities. Filters: `user`, `customers[]`, `projects[]`, `reservations[]` (IDs), `begin`, `end` (`Y-m-d`), `tentative` (`1` tentative only, `0` confirmed only) |
| `GET` | `/api/planner/activities/{id}` | Fetch one activity |
| `POST` | `/api/planner/activities` | Create. Body: `customer` (required unless `timeOff`), `project`, `user` (defaults to you), `begin`, `end`, `hoursPerDay` (`null` or left out when switching to time off: the whole day), `timeOff` (time off never keeps a customer or project), `reservation` (a reservation ID instead of customer and project, see [Reserved time](#reserved-time)), `color`, `tentative`, `comment`, `recurrentWeeks` |
| `PATCH` | `/api/planner/activities/{id}` | Update any subset of fields; `edit_mode: "future"` also updates later activities of a recurring series, moving their dates by as many days as this one moved |
| `DELETE` | `/api/planner/activities/{id}` | Delete; `?mode=future` also deletes later activities of a recurring series |
| `POST` | `/api/planner/activities/confirm-tentative` | Confirm the tentative activities of a project ending today or later. Body: `project` (required). Returns the IDs of the `confirmed` activities and of those `skipped` because you may see but not edit them; activities you may not see are neither confirmed nor returned |
| `GET` | `/api/planner/holidays` | Public holidays users have off. Required: `begin`, `end` (`Y-m-d`, at most 366 days). Optional: `users[]` (default: everyone you may see). Returns `user`, `date`, `name`, `calendar`, `calendarName`, `type` per day |

## Public holidays

Public holidays are days off in the planner, just like days without contract hours: they have no capacity, and the cell under the user's week shows the holiday's name (the tooltip adds which calendar it comes from).

- **Organisation default**: pick a calendar under *System > Settings > Weekly Planner*. Nobody has public holidays until one is chosen, so installing or updating the plugin changes nothing.
- **Per user**: a user's preferences can pick another calendar, or "No public holidays", for teams spread over several countries. Only those who may edit the user's work contract (`contract` permission) can change it.
- **Which days**: official and bank holidays are always days off. Some days are not public holidays but most organisations have them off anyway, such as Christmas Eve, Midsummer Eve and New Year's Eve in Sweden. Tick those under *Also days off* in the same settings; a ticked day applies to every calendar that has it.

The holidays come from [Yasumi](https://github.com/azuyalabs/yasumi), which ships with Kimai core, so no network access or extra package is needed. It covers about 50 countries and many of their regions.

The planner relies on Kimai core keeping Yasumi: a plugin cannot declare Composer dependencies of its own, so it uses the copy Kimai's `composer.json` requires (`azuyalabs/yasumi`, `^2.6` at the time of writing). Kimai itself doesn't call it, so check that it is still required before updating Kimai; if a release ever dropped it, the planner's holidays would need it back, or another provider (below).

To take holidays from somewhere else, decorate `KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface` in your own plugin:

```yaml
services:
    App\MyHolidays:
        decorates: KimaiPlugin\PlannerBundle\Holiday\HolidayProviderInterface
```

Time off is returned with `"timeOff": true`, `customer` and `project` set to `null`, and `hoursPerDay` set to `null` when it covers the whole day, so clients must not assume either is always present.

## Reserved time

Reserved time holds hours in someone's planning for something that is not a customer or project: a daily buffer so nobody is planned for a full day of project work, time for tech leading, internal work that must not be logged as project time.

- **Managing it**: *Administration > Reserved time*, for whoever holds `manage_reservations_planner` (admins and super admins by default). Each one has a name and a colour for its bars, and the arrows set the order of its rows. Renaming is safe: plannings reference it by ID.
- **Archiving** stops offering it for new planning. Plannings it already has stay in the planner and keep counting, so nobody looks free while their time is held; delete or shorten them to free the time. Restoring offers it again.
- **Deleting** is only possible while nothing is planned on it. The foreign key refuses the rest, which is what archiving is for.
- **Planning it**: the dialog shows a "Reserved time" field above the customer when there is at least one. Choosing one hides customer, project, colour and tentative. Each reservation gets its own row under every person, below the projects; clicking or dragging in that row plans it, and dropping other planning there turns it into that reservation (dropping it on a project's row turns it back).
- **Counting**: like project work, in every gauge and in "Most free hours", and like project work it takes no hours on a day off unless all its days are days off. It is never tentative, and time off replaces it. Moving or stretching it onto the same reservation on one of the person's days is refused, as for projects.
- **API**: activities carry `reservation` (the reservation, or `null`), create and update take a reservation ID, and `GET /api/planner/activities` filters by `reservations[]`.

## Calendar feed

Everyone with both `view_planner` and `calendar_feed_planner` (every role has the latter by default; take it away under *System > Role permissions*) can subscribe to their own planned work from a calendar app. The "Calendar feed" button above the planner opens a page with a private address to add as a calendar subscription ("From URL" in Google Calendar, "Subscribe from web" in Outlook, "New Calendar Subscription" in Apple Calendar).

- Each planned activity is an all-day event over its days, titled with the project (or customer) and the hours per day; the description has customer, project and hours, and the event links back to that week in the planner. Events are marked as free, so planned work never makes you look busy for meetings. Tentative work is titled "Tentative: …" and marked tentative, and time off without hours shows as a whole day.
- The feed covers 60 days back to a year ahead. Calendar apps refresh subscriptions on their own schedule, Google can take up to a day.
- Run the plugin's migrations after updating: the addresses are kept in a new table, `kimai2_planner_calendar_tokens`.
- The address carries a random 64-character token and needs no login, which is how calendar apps can fetch it. Renewing the address on the same page makes the old one stop working; "Switch off" removes it. The feed also stops for users who are deactivated or lose either permission.

Three settings under *System > Settings > Weekly Planner* decide what leaves Kimai once someone subscribes:

- **Notes in calendar feeds** (default: leave out). Comments often hold details meant for colleagues only, and the feed hands everything in it to whichever calendar service each person subscribes from. Set it to "Include" to add them to the event description.
- **Let people choose for their own feed** (default: no). With it on, the feed page gets an "Include notes in my feed" switch that starts from the setting above. Only a choice that differs from the organisation's is kept, so everyone else follows it if it changes.
- **Store feed addresses hashed** (default: yes). Only a SHA-256 hash of the token is kept, so a leaked database backup reveals no working address. The address is then shown once, right after it is created; whoever loses it renews it and subscribes again. Addresses created while the setting was off are hashed as soon as it is saved as on (and, on update, by the migration that brings the setting), and keep working.

## Upgrading

### Reserved time

The migration `Version20261111000000` adds the table `kimai2_planner_reservations` and the nullable column `reservation_id` to `kimai2_planned_activities`, so existing planning is unchanged. Run it after updating (see [Installation](#installation)).

### Tentative planning

The migration `Version20261102120000` adds the column `tentative` (default false) to `kimai2_planned_activities`, so all existing planning stays confirmed. Run it after updating (see [Installation](#installation)).

### From 1.x to 2.0.0

Version 2.0.0 contains a breaking change: planned activities are now linked to a customer and optionally a project instead of carrying a free-text title.

What changed:

- **Customers and projects**: Every planned activity belongs to a customer and optionally a project. The free-text title is legacy and optional.
- **New migration**: `Version20261019000000` adds `customer_id` and `project_id` to `kimai2_planned_activities` and makes `title` nullable. Run it after updating (see [Installation](#installation)).
- **REST API**: New endpoints under `/api/planner/activities` (see below). `customer` is required when creating an activity.
- **Multi-week grid**: The planner shows 1, 2, 4 (default) or 8 weeks, with capacity summarized per week. Activities of the same customer/project are grouped in sub-rows per user, and consecutive days are merged into one block.
- **Always an empty row per user**, so new plannings can be dragged out even when a user has no activities.
- **Drag to plan**: Dragging over empty days opens the create dialog with the whole date range, showing a transparent preview of the planning.
- **Confirm days off**: Clicking or dragging over a day the user's work contract has no hours for asks "<name> doesn't usually work on this day. Plan work anyway?" before the create dialog opens. A drag that also covers working days is not asked about, and neither is time off. Users without any working day in the displayed period (no work contract) are never asked.
- **Move and resize**: Drag a planned activity to another day or row (other user, customer/project). Drag the handle on its left or right edge to extend or shorten it. Whole days only, the hours per day stay the same. Requires the edit permission for the activity.
- **Shortcuts** for frequently used customers and projects when creating a planning; they no longer depend on HappinessBundle.
- **Recurring activities**: Edit or delete only one instance or all upcoming activities of a series.
- **Compact header** with the current day highlighted.

Existing data is kept. Activities created before customers were introduced keep their old free-text title and have no customer. They are still shown (using the old title) but must be given a customer the next time they are edited.
