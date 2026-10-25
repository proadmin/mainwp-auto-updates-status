# MainWP Auto Updates Status Extension

By [ProAdmin](https://proadminwp.net/)

A MainWP Dashboard extension that adds an **Auto Updates** widget to the Operations page. It shows the status, schedule, live progress and run history of MainWP's built-in Advanced Automatic Updates.

![Widget views](docs/widget-views.png)

## Features

- **Status:** scheduled, running (with live progress), overdue or disabled. Also shows the next run, the last run's result, the schedule, update delay, enabled update types and trusted item counts.
- **Latest Run:** every update in the most recent run, with old and new versions and a result of updated, failed or rolled back (hover to see the error).
- **Run History:** the last 10 runs, plus a full, paginated history page with per-run details.
- **Recent Failures:** failed and rolled-back updates from all runs.
- History retention setting, 90 days by default.

## Requirements

- MainWP Dashboard (tested with 6.3)
- WordPress 6.2+
- PHP 7.4+

## Installation

1. Download a release zip, or zip the `mainwp-auto-updates-status` folder.
2. In the Dashboard site's WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**, upload the zip and activate it.
3. Open **MainWP → Operations**. If the widget is hidden, turn on **Auto Updates Status** in Page Settings (the cog icon).

See [docs/user-guide.pdf](docs/user-guide.pdf) for the full user guide.

## How it works

MainWP keeps only the latest run's results (in the `mainwp_updates_report_storage` option) and clears them when the next run starts. The extension watches that option, and `mainwp_automatic_updates_is_running`, and copies each result into its own tables (`{prefix}mainwp_aus_runs` and `{prefix}mainwp_aus_items`) as soon as MainWP saves it. MainWP itself isn't modified. History builds up from the day the extension is activated.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `mainwp_aus_can_view` | filter (bool) | Who can see the widget and history |
| `mainwp_aus_refresh_interval` | filter (int seconds, 0 disables) | Live refresh while a run is in progress |
| `mainwp_aus_widget_title` | filter (string) | Widget title |
| `mainwp_aus_run_finished` | action (`$run_start`, `$status`) | Fires when a run is recorded as finished |

## License

Copyright (c) 2026 [ProAdmin](https://proadminwp.net/).

Released under the GNU General Public License v2 or later.
