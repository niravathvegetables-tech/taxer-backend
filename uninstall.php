<?php
/**
 * Taxer Uninstall
 *
 * Runs when the plugin is deleted from WordPress.
 * Only drops tables when company_data is set to 'yes'.
 *
 * @package Taxer
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Only remove data if the user explicitly opted in via company_data = 'yes'.
$company_table = $wpdb->prefix . 'taxer_company';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$company = $wpdb->get_row( "SELECT company_data FROM `{$company_table}` LIMIT 1" );

if ( $company && 'yes' === $company->company_data ) {
	$tables = array(
		$wpdb->prefix . 'taxer_stocks',
		$wpdb->prefix . 'taxer_purchase',
		$wpdb->prefix . 'taxer_sales',
		$wpdb->prefix . 'taxer_transaction',
		$wpdb->prefix . 'taxer_taxes',
		$wpdb->prefix . 'taxer_receipt',
		$wpdb->prefix . 'taxer_payment',
		$wpdb->prefix . 'taxer_contra',
		$wpdb->prefix . 'taxer_company',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}

delete_option( 'taxer_db_version' );
