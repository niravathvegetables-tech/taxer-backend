=== Taxer ===
Contributors: rajmohan
Tags: tax, vat, trn, invoice, company
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple Tax Manager plugin to manage your company details and tax registration number from the WordPress admin.

== Description ==

Taxer is a lightweight Tax Manager plugin that allows business owners to store and manage their company details — including company name, address, and Tax Registration Number (TRN) — directly from the WordPress admin dashboard.

**Features:**

* First-time company setup wizard
* Edit company details via a clean modal dialog
* Secure AJAX-powered updates with nonce verification
* Capability-checked — only administrators can access the plugin
* Clean, responsive admin UI using native WordPress styles
* Translation-ready (i18n)

This plugin is ideal for businesses that need to store their company and VAT/TRN details in one place, ready to use across invoices and tax documents.

== Installation ==

1. Download the plugin zip file.
2. Log in to your WordPress admin panel and go to **Plugins → Add New**.
3. Click **Upload Plugin** and select the downloaded zip file.
4. Click **Install Now**, then **Activate Plugin**.
5. Navigate to **Tax Manager** in the left admin menu.
6. Fill in your company details on the setup screen and click **Save & Continue**.

**Manual installation:**

1. Upload the `taxer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.

== Frequently Asked Questions ==

= Who can access the Tax Manager? =

Only users with the `manage_options` capability (Administrators) can view or edit data in this plugin.

= Where is my company data stored? =

All data is stored in your WordPress database in a custom table (`wp_taxer_company`). No data is sent to any external server.

= Is this plugin translation-ready? =

Yes. All user-facing strings are wrapped in WordPress i18n functions and the text domain is `taxer`.

= Does this plugin work with multisite? =

The plugin has not been tested on WordPress Multisite. Single-site installations are fully supported.

= What happens to my data if I deactivate the plugin? =

Deactivating the plugin does not remove your data. The database table is preserved. If you wish to remove all data, delete the plugin entirely (a future version will include an optional uninstall cleanup).

== Screenshots ==

1. Company setup screen — shown on first activation.
2. Main dashboard — displays stored company details.
3. Edit modal — update company details without leaving the page.

== Changelog ==

= 1.0.0 =
* Initial release.
* Company setup form with nonce-protected POST submission.
* Dashboard view with AJAX-powered edit modal.
* Secure input sanitisation and output escaping throughout.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.

== Privacy Policy ==

Taxer does not collect, store, or transmit any personal data to external services. All data entered (company name, address, TRN) is stored locally in your WordPress database and is accessible only to site administrators.
