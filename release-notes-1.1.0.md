# Auto Updates Status for MainWP 1.1.0

This release renames the plugin to **Auto Updates Status for MainWP** so that it meets the WordPress.org plugin naming guidelines. Features and stored data are unchanged.

## ⚠️ Upgrading from 1.0.x

The plugin folder has a new name, so WordPress treats 1.1.0 as a separate plugin rather than an update. To keep your run history:

1. Deactivate the old **MainWP Auto Updates Status Extension** on the Plugins page.
2. Remove its folder, `wp-content/plugins/mainwp-auto-updates-status`, by FTP or your host's file manager.
   **Do not use Delete on the Plugins page.** That runs the uninstaller and erases your history.
3. Upload `auto-updates-status-for-mainwp.zip` (Plugins > Add New > Upload Plugin) and activate it.

The database tables, options and PHP namespace are the same as in 1.0.x, so your history and retention setting carry over.

## What's changed

### Renamed
- New name: **Auto Updates Status for MainWP** (previously "MainWP Auto Updates Status Extension").
- The plugin slug, main file and text domain are now `auto-updates-status-for-mainwp`.
- The extension page is now at **Add-ons > Auto Updates Status for MainWP**.

### Improvements
- MainWP Dashboard is now declared as a required plugin (`Requires Plugins: mainwp`, honoured by WordPress 6.5+).
- Removed the `load_plugin_textdomain()` call. WordPress now loads translations automatically.
- Coding-standards cleanup: aligned arrays and added `@package` tags to the `index.php` guard files.

### Readme and branding
- Tested up to WordPress 7.1.
- Added readme tags, shortened the summary, added an upgrade notice and removed the donate link.
- All author, copyright and documentation links now point to [proadmin.com](https://proadmin.com), and the Author URI uses HTTPS.
- The Installation Guide and User Guide PDFs (included in the zip under `docs/`) are updated for the new name and links.

## Requirements
- WordPress 6.2 or later (6.5+ to enforce the MainWP dependency)
- PHP 7.4 or later
- MainWP Dashboard

## Download
Attach **auto-updates-status-for-mainwp.zip** to this release. Install it with Plugins > Add New > Upload Plugin.

**Full changelog:** [`1.0.3...1.1.0`](https://github.com/proadmin/mainwp-auto-updates-status/compare/50d5260...c7df2fa)

---
© 2026 [ProAdmin](https://proadmin.com). Released under the GNU General Public License v2 or later.
