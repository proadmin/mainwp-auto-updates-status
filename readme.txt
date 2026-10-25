=== MainWP Auto Updates Status Extension ===
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Adds an Auto Updates widget to the MainWP Operations dashboard with the status, schedule, live progress and history of MainWP's Advanced Automatic Updates.

== Description ==

Widget (MainWP > Operations), four views from the widget menu:

* Status: running / scheduled / overdue / disabled, next run, last run result, schedule, update delay, enabled update types, trusted plugin and theme counts, and a live progress bar while a run is in progress (auto-refreshes every 60 seconds).
* Latest Run: every update in the most recent run with old and new version and result (updated, failed, rolled back, with the error on hover).
* Run History: the last 10 runs with updated and failed counts.
* Recent Failures: failed updates across all runs.

The extension page (Add-ons > MainWP Auto Updates Status Extension) shows the full paginated run history, per-run details, and a history retention setting (default 90 days).

== How it works ==

MainWP keeps only the latest run's results (option mainwp_updates_report_storage) and clears them when the next run starts. This extension copies each result into its own tables (wp_mainwp_aus_runs, wp_mainwp_aus_items) the moment MainWP saves it, so history builds up from activation onward. Nothing in MainWP is modified. On activation it imports the latest run's results and up to ~20 previous run start times (start times only, MainWP does not keep their details).

Tested with MainWP Dashboard 6.3.

== Hooks ==

* mainwp_aus_can_view (filter, bool) - who can see the widget and history.
* mainwp_aus_refresh_interval (filter, int seconds, 0 disables) - live refresh while running.
* mainwp_aus_widget_title (filter, string).
* mainwp_aus_run_finished (action, $run_start, $status) - fires when a run is recorded as finished.

== Changelog ==

= 1.0.0 =
* Initial release.
