=== Taxer ===
Contributors: rajmohan
Tags: tax, vat, accounting, invoice, stock management
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete Tax Manager plugin to manage company details, stock, purchases, sales, receipts, payments, and VAT/tax records from the WordPress admin.

== Description ==

Taxer is a full-featured Tax Manager plugin that allows business owners to manage their accounting records directly from the WordPress admin dashboard.

**Features:**

* Company setup wizard with TRN and VAT/tax configuration
* Stock management — add, edit, and delete stock items
* Purchase recording with automatic stock level adjustments
* Sales recording with tax calculation
* Receipt and Payment voucher management
* Bank/Contra entry management
* Full transaction report with input/output tax summary
* Secure REST API with nonce-protected write operations
* Capability-checked — only administrators can modify data
* React-powered frontend via shortcode `[taxer_app]`
* Clean, responsive UI
* Translation-ready (i18n)

All data is stored locally in your WordPress database. No data is sent to any external server.

== Installation ==

1. Download the plugin zip file.
2. Log in to your WordPress admin panel and go to **Plugins → Add New**.
3. Click **Upload Plugin** and select the downloaded zip file.
4. Click **Install Now**, then **Activate Plugin**.
5. Navigate to **Tax Manager** in the left admin menu.
6. Fill in your company details on the setup screen and click **Save & Continue**.
7. To use the React frontend, add the shortcode `[taxer_app]` to any page.

**Manual installation:**

1. Upload the `taxer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.

== Frequently Asked Questions ==

= Who can access the Tax Manager? =

Only users with the `manage_options` capability (Administrators) can view or modify data.

= Where is my data stored? =

All data is stored in your WordPress database in custom tables prefixed with `wp_taxer_`. No data is sent to any external server.

= Is this plugin translation-ready? =

Yes. All user-facing strings are wrapped in WordPress i18n functions with the text domain `taxer`.

= Does this plugin work with multisite? =

The plugin has not been tested on WordPress Multisite. Single-site installations are fully supported.

= What happens to my data if I deactivate the plugin? =

Deactivating the plugin does not remove your data. Tables are preserved on deactivation. Data is only removed when the plugin is fully deleted AND the company record has `company_data` set to `yes`.

= How do I display the frontend? =

Add the shortcode `[taxer_app]` to any WordPress page. The React-based frontend will be loaded automatically.

== Screenshots ==

1. Company setup screen — shown on first activation.
2. Main dashboard — displays stored company and tax details.
3. Stock management — add and manage your inventory.
4. Purchase and sales entry with tax calculation.
5. Full transaction report with input/output tax summary.

== Changelog ==

= 1.0.0 =
* Initial release.
* Company setup with nonce-protected form submission.
* Stock, Purchase, Sales, Receipt, Payment, and Contra management.
* REST API with proper permission callbacks.
* React frontend via shortcode.
* Full transaction report with VAT input/output summary.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.

== Privacy Policy ==

Taxer does not collect, store, or transmit any personal data to external services. All data entered (company name, address, TRN, transactions) is stored locally in your WordPress database and is accessible only to site administrators.
