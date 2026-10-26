=== MainWP Auto Updates Status Extension ===
Contributors: proadmin
Donate link: https://proadmin.com
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an Auto Updates widget to the MainWP Operations dashboard with the status, schedule, live progress and history of MainWP's Advanced Automatic Updates.

== Description ==

Widget (MainWP > Operations), four views from the widget menu:

* Status: running / scheduled / overdue / disabled, next run, last run result, schedule, update delay, enabled update types, trusted plugin and theme counts, and a live progress bar while a run is in progress (auto-refreshes every 60 seconds).
* Latest Run: every update in the most recent run with old and new version and result (updated, failed, rolled back, with the error on hover).
* Run History: the last 10 runs with updated and failed counts.
* Recent Failures: failed updates across all runs.

The extension page (Add-ons > MainWP Auto Updates Status Extension) shows the full paginated run history, per-run details, and a history retention setting (default 90 days).

== Documentation ==

* docs/installation-guide.pdf - installing, enabling the widget, updating and removing.
* docs/user-guide.pdf - using the widget and history page.
* LICENSE.txt - the GNU General Public License, version 2.

== How it works ==

MainWP keeps only the latest run's results (option mainwp_updates_report_storage) and clears them when the next run starts. This extension copies each result into its own tables (wp_mainwp_aus_runs, wp_mainwp_aus_items) the moment MainWP saves it, so history builds up from activation onward. Nothing in MainWP is modified. On activation it imports the latest run's results and up to ~20 previous run start times (start times only, MainWP does not keep their details).

Tested with MainWP Dashboard 6.3.

== Known limitations ==

* Live progress and each run's site count use the same per-site start time that MainWP's batch updates use. If a batch update is started while an automatic run is in progress, the progress bar drops and that run's site count is too low. A batch update also holds MainWP's cron, so the widget can show Overdue until it finishes.
* On WordPress multisite, activation and uninstall only act on the current site.
* If the extension is not loaded when MainWP finishes a run (for example, it was deactivated during the run), the run is closed the next time the widget or history page is viewed, without an end time or duration.

== Hooks ==

* mainwp_aus_capability (filter, string) - WordPress capability required to see the widget and history. Default manage_options.
* mainwp_aus_can_view (filter, bool) - final yes/no on who can see the widget and history.
* mainwp_aus_refresh_interval (filter, int seconds, 0 disables) - live refresh while running.
* mainwp_aus_widget_title (filter, string).
* mainwp_aus_run_finished (action, $run_start, $status) - fires when a run is recorded as finished.

== Changelog ==

= 1.0.3 =
* Fix: a run could stay "Running" forever if the extension missed the end of the run. Such runs are now closed automatically.
* Fix: returning 0 from mainwp_aus_refresh_interval now turns off live refresh.
* Fix: the import of MainWP's existing results now also runs on reactivation, and when MainWP is activated after this extension. Runs already recorded are not duplicated.
* Fix: very long error messages could stop an update result from being saved. Errors are now capped at 2,000 characters, and names are shortened without breaking accented or non-Latin characters.
* Fix: imported failures now show the run's time instead of the activation time.
* Fix: the retention setting is capped before old runs are removed, and saving settings now uses the same access check as viewing the page.
* Performance: the extension's options are now autoloaded, saving one database query per page load. The widget also runs fewer queries.
* "Sites Changed" is renamed "Sites with Updates".

= 1.0.2 =
* Plugin site link now opens the GitHub repository; author link now opens proadmin.com.

= 1.0.1 =
* Security: all database queries now pass table names through $wpdb->prepare with %i placeholders.
* Security: the widget and history page now require the manage_options capability as well as MainWP access.
* New: mainwp_aus_capability filter to change the required capability.
* Plugin zip now includes the GPL licence and the user and installation guides.

= 1.0.0 =
* Initial release.

== Copyright ==

MainWP Auto Updates Status Extension is Copyright (c) 2026 ProAdmin (https://proadmin.com).
Released under the GNU General Public License v2 or later.
