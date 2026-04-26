<?php
/**
 * Plugin Name:       Taxer
 * Plugin URI:        https://wordpress.org/plugins/taxer/
 * Description:       Tax Manager Plugin — manage company details, stock, purchases, sales, receipts, payments, and tax records from your WordPress admin.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Rajmohan N R
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       taxer
 * Domain Path:       /languages
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────
define( 'TAXER_VERSION',     '1.0.0' );
define( 'TAXER_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'TAXER_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'TAXER_PLUGIN_FILE', __FILE__ );

// ── Autoload MVC files ────────────────────────────────────────────────────────
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-model.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-view.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-controller.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-sales.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-stock.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-tax.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-purchase.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-receipt.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-payment.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-contra.php';

// ── Activation hook ───────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'taxer_activate' );

/**
 * Runs once on plugin activation — creates the required DB tables.
 */
function taxer_activate() {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->activate_db();
	update_option( 'taxer_db_version', TAXER_VERSION );
}

// ── Deactivation hook ─────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'taxer_deactivate' );

/**
 * Runs on plugin deactivation — flush rewrite rules.
 * Note: data is preserved on deactivation; only removed if user deletes plugin
 * and company_data is set to 'yes'.
 */
function taxer_deactivate() {
	flush_rewrite_rules();
}

// ── Uninstall hook ────────────────────────────────────────────────────────────
register_uninstall_hook( __FILE__, 'taxer_uninstall' );

/**
 * Runs on plugin deletion — delegates table cleanup to the model.
 */
function taxer_uninstall() {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->deactivate_db();
}

// ── Load text domain ───────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'taxer_load_textdomain' );

/**
 * Load plugin text domain for translations.
 */
function taxer_load_textdomain() {
	load_plugin_textdomain(
		'taxer',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

// ── Admin menu ─────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'taxer_register_menu' );

/**
 * Registers the top-level admin menu page.
 */
function taxer_register_menu() {
	add_menu_page(
		esc_html__( 'Tax Manager', 'taxer' ),
		esc_html__( 'Tax Manager', 'taxer' ),
		'manage_options',
		'tax-manager',
		'taxer_render_page',
		'dashicons-calculator',
		20
	);
}

/**
 * Page callback — capability check then delegate to controller.
 */
function taxer_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'taxer' ) );
	}

	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->starter();
}

// ── Enqueue assets (admin only) ────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'taxer_enqueue_assets' );

/**
 * Enqueues CSS + JS only on the Taxer admin page.
 *
 * @param string $hook Current admin page hook.
 */
function taxer_enqueue_assets( $hook ) {
	if ( 'toplevel_page_tax-manager' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'taxer-admin',
		TAXER_PLUGIN_URL . 'assets/css/tax-manager.css',
		array(),
		TAXER_VERSION
	);

	wp_enqueue_script(
		'taxer-admin',
		TAXER_PLUGIN_URL . 'assets/js/tax-manager.js',
		array( 'jquery' ),
		TAXER_VERSION,
		true
	);

	wp_localize_script(
		'taxer-admin',
		'TaxerAjax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'taxer_ajax_nonce' ),
		)
	);
}

// ── Shortcode ──────────────────────────────────────────────────────────────────
add_shortcode( 'taxer_app', 'taxer_render_react' );

/**
 * Shortcode callback — enqueues the React frontend and returns the mount div.
 *
 * @return string HTML mount point.
 */
function taxer_render_react() {
	wp_enqueue_style(
		'taxer-react',
		TAXER_PLUGIN_URL . 'react/css/main.css',
		array(),
		TAXER_VERSION
	);

	wp_enqueue_script(
		'taxer-react',
		TAXER_PLUGIN_URL . 'react/js/main.js',
		array(),
		TAXER_VERSION,
		true
	);

	wp_localize_script(
		'taxer-react',
		'TaxerData',
		array(
			'rest_url' => esc_url_raw( rest_url( 'taxer/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		)
	);

	return '<div id="root"></div>';
}

// ── AJAX handlers ──────────────────────────────────────────────────────────────
add_action( 'wp_ajax_taxer_get_company',    'taxer_ajax_get_company' );
add_action( 'wp_ajax_taxer_update_company', 'taxer_ajax_update_company' );

/**
 * AJAX: return company record as JSON.
 */
function taxer_ajax_get_company() {
	check_ajax_referer( 'taxer_ajax_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Permission denied.', 'taxer' ), 403 );
		return;
	}

	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->ajax_get_company();
}

/**
 * AJAX: update company record.
 */
function taxer_ajax_update_company() {
	check_ajax_referer( 'taxer_ajax_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Permission denied.', 'taxer' ), 403 );
		return;
	}

	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->ajax_update_company();
}

// ── REST API permission callback ───────────────────────────────────────────────

/**
 * REST API permission callback — requires a valid nonce for write operations.
 * Read-only GET endpoints use __return_true since they return non-sensitive data.
 *
 * @param WP_REST_Request $request Incoming REST request.
 * @return bool|WP_Error
 */
function taxer_rest_permissions( WP_REST_Request $request ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'You do not have permission to perform this action.', 'taxer' ),
			array( 'status' => 403 )
		);
	}
	return true;
}

// ── REST API routes ────────────────────────────────────────────────────────────
add_action( 'rest_api_init', 'taxer_register_rest_routes' );

/**
 * Register all REST API routes in one place.
 */
function taxer_register_rest_routes() {
	$namespace = 'taxer/v1';

	// ── Company ──────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/data',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_get_data',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$namespace,
		'/update',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_update_company',
			'permission_callback' => 'taxer_rest_permissions',
			'args'                => array(
				'id'      => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'name'    => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'trn'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
				'address' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				'amount'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
				'tax_id'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		)
	);

	// ── Stock ────────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/getstock',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_get_data_stock',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$namespace,
		'/insertstock',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_insert_stock',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/updatestock',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_update_stock',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/deletestock',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_delete_stock',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Tax ──────────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/gettax',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_get_tax',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$namespace,
		'/inserttax',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_insert_tax',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/updatetax',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_update_tax',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/deletetax',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_delete_tax',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Purchase ─────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/purchase',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_purchase',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/getreportpurchase',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_get_report_purchase',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Sales ────────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/sales',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_sales',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/getreportsales',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_get_report_sales',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Receipt ──────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/insertreceipt',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_insert_receipt',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/getreceipt',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_get_receipt',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/updatereceipt',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_update_receipt',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/deletereceipt',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_delete_receipt',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Payment ──────────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/addpayment',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_add_payment',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/getpayment',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_get_payment',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/updatepayment',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_update_payment',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/deletepayment',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_delete_payment',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Contra (Bank) ────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/addcontra',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_add_contra',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/getcontra',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_get_contra',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/updatecontra',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_update_contra',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	register_rest_route(
		$namespace,
		'/deletecontra',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'taxer_rest_delete_contra',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);

	// ── Full report ──────────────────────────────────────────────────────────
	register_rest_route(
		$namespace,
		'/fullreport',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'taxer_rest_full_report',
			'permission_callback' => 'taxer_rest_permissions',
		)
	);
}

// ── REST callback functions ────────────────────────────────────────────────────

/**
 * GET /taxer/v1/data
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_get_data( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->toreactfrontend();
}

/**
 * POST /taxer/v1/update
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_update_company( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactUpdate( $request );
}

/**
 * GET /taxer/v1/getstock
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_get_data_stock( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->toreactfrontendstock();
}

/**
 * POST /taxer/v1/insertstock
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_insert_stock( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactStockInsert( $request );
}

/**
 * POST /taxer/v1/updatestock
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_update_stock( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactStockUpdate( $request );
}

/**
 * POST /taxer/v1/deletestock
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_delete_stock( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactStockDelete( $request );
}

/**
 * GET /taxer/v1/gettax
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_get_tax( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactTaxGet();
}

/**
 * POST /taxer/v1/inserttax
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_insert_tax( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactTaxInsert( $request );
}

/**
 * POST /taxer/v1/updatetax
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_update_tax( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactTaxUpdate( $request );
}

/**
 * POST /taxer/v1/deletetax
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_delete_tax( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->reactTaxDelete( $request );
}

/**
 * POST /taxer/v1/purchase
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_purchase( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Purchase( $wpdb );
	return $controller->reactTaxPurchase( $request );
}

/**
 * GET /taxer/v1/getreportpurchase
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_get_report_purchase( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Purchase( $wpdb );
	return $controller->GetPurchaseDetails( $request );
}

/**
 * POST /taxer/v1/sales
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_sales( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Sales( $wpdb );
	return $controller->reactTaxSales( $request );
}

/**
 * GET /taxer/v1/getreportsales
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_get_report_sales( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Sales( $wpdb );
	return $controller->GetSalesDetails( $request );
}

/**
 * POST /taxer/v1/insertreceipt
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_insert_receipt( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Receipt( $wpdb );
	return $controller->reactTaxReceipt( $request );
}

/**
 * GET /taxer/v1/getreceipt
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_get_receipt( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Receipt( $wpdb );
	return $controller->reactReceiptGet();
}

/**
 * POST /taxer/v1/updatereceipt
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_update_receipt( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Receipt( $wpdb );
	return $controller->reactTaxReceiptUpdate( $request );
}

/**
 * POST /taxer/v1/deletereceipt
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_delete_receipt( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Receipt( $wpdb );
	return $controller->reactReceiptDelete( $request );
}

/**
 * POST /taxer/v1/addpayment
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_add_payment( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Payment( $wpdb );
	return $controller->reactTaxPayment( $request );
}

/**
 * GET /taxer/v1/getpayment
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_get_payment( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Payment( $wpdb );
	return $controller->reactPaymentGet();
}

/**
 * POST /taxer/v1/updatepayment
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_update_payment( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Payment( $wpdb );
	return $controller->reactTaxPaymentUpdate( $request );
}

/**
 * POST /taxer/v1/deletepayment
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_delete_payment( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Payment( $wpdb );
	return $controller->reactPaymentDelete( $request );
}

/**
 * POST /taxer/v1/addcontra
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_add_contra( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Contra( $wpdb );
	return $controller->reactContraPayment( $request );
}

/**
 * GET /taxer/v1/getcontra
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_get_contra( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Contra( $wpdb );
	return $controller->reactContraGet();
}

/**
 * POST /taxer/v1/updatecontra
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function taxer_rest_update_contra( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Contra( $wpdb );
	return $controller->reactContraPaymentUpdate( $request );
}

/**
 * POST /taxer/v1/deletecontra
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_delete_contra( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Contra( $wpdb );
	return $controller->reactContraDelete( $request );
}

/**
 * GET /taxer/v1/fullreport
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function taxer_rest_full_report( WP_REST_Request $request ) {
	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	return $controller->FullReport( $request );
}
