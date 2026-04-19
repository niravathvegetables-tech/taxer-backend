<?php
/**
 * Plugin Name:       Taxer
 * Plugin URI:        https://example.com/taxer
 * Description:       Tax Manager Plugin — manage company details and tax records from your WordPress admin.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Rajmohan N R
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       taxer
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ob_start();

// ── Constants ─────────────────────────────────────────────────────────────────
define( 'TAXER_VERSION',     '1.0.0' );
define( 'TAXER_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'TAXER_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'TAXER_PLUGIN_FILE', __FILE__ );

// ── Autoload MVC files ────────────────────────────────────────────────────────
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-model.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-view.php';
require_once TAXER_PLUGIN_DIR . 'includes/class-taxer-controller.php';
require_once TAXER_PLUGIN_DIR . 'includes/sales.php';
require_once TAXER_PLUGIN_DIR . 'includes/stock.php';
require_once TAXER_PLUGIN_DIR . 'includes/tax.php';
require_once TAXER_PLUGIN_DIR . 'includes/purchase.php';
require_once TAXER_PLUGIN_DIR . 'includes/receipt.php';
require_once TAXER_PLUGIN_DIR . 'includes/payment.php';
require_once TAXER_PLUGIN_DIR . 'includes/contra.php';

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
 * Runs on plugin deactivation — flush rules and optionally drop tables.
 */
function taxer_deactivate() {
	flush_rewrite_rules();

	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->deactivate_db();
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

	// Pass PHP data to JS — never inline PHP inside JS files.
	wp_localize_script(
		'taxer-admin',
		'TaxerAjax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'taxer_ajax_nonce' ),
		)
	);
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
	}

	global $wpdb;
	$controller = new Taxer_Controller( $wpdb );
	$controller->ajax_update_company();
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

	return '<div id="root"></div>';
}

// ── REST API routes ────────────────────────────────────────────────────────────

/**
 * GET /taxer/v1/data — return company data.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/data',
			array(
				'methods'             => 'GET',
				'callback'            => 'taxer_get_data',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * REST callback: return full company data.
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
 * POST /taxer/v1/update — update company record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/update',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactUpdate( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/insertstock — insert a stock record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/insertstock',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactStockInsert( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * GET /taxer/v1/getstock — return all stock records.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/getstock',
			array(
				'methods'             => 'GET',
				'callback'            => 'taxer_get_data_stock',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * REST callback: return all stock data.
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
 * POST /taxer/v1/updatestock — update a stock record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/updatestock',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactStockUpdate( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/deletestock — delete a stock record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/deletestock',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactStockDelete( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/inserttax — insert a tax record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/inserttax',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactTaxInsert( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * GET /taxer/v1/gettax — return all tax records.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/gettax',
			array(
				'methods'             => 'GET',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactTaxGet();
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/updatetax — update a tax record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/updatetax',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactTaxUpdate( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/deletetax — delete a tax record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/deletetax',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Taxer_Controller( $wpdb );
					return $controller->reactTaxDelete( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/purchase — record a purchase transaction.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/purchase',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Purchase( $wpdb );
					return $controller->reactTaxPurchase( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/sales — record a sales transaction.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/sales',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Sales( $wpdb );
					return $controller->reactTaxSales( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * POST /taxer/v1/insertreceipt — record a Recipt transaction.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/insertreceipt',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Receipt( $wpdb );
					return $controller->reactTaxReceipt( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);




/**
 * GET /taxer/v1/getreceipt — return all tax records.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/getreceipt',
			array(
				'methods'             => 'GET',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Receipt( $wpdb );
					return $controller->reactReceiptGet();
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);



/**
 * POST /taxer/v1/updatereceipt — record a Recipt transaction.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/updatereceipt',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Receipt( $wpdb );
					return $controller->reactTaxReceiptUpdate( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);


/**
 * POST /taxer/v1/deletereceipt — delete a receipt record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/deletereceipt',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Receipt( $wpdb );
					return $controller->reactReceiptDelete( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);




add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/addpayment',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Payment( $wpdb );
					return $controller->reactTaxPayment( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);





/**
 * GET /taxer/v1/getpayment — return all tax records.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/getpayment',
			array(
				'methods'             => 'GET',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Payment( $wpdb );
					return $controller->reactPaymentGet();
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);


add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/updatepayment',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Payment( $wpdb );
					return $controller->reactTaxPaymentUpdate( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);


/**
 * POST /taxer/v1/deletepayment — delete a receipt record.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/deletepayment',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Payment( $wpdb );
					return $controller->reactPaymentDelete( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);


/**Bank transactions
 * POST and GET /taxer/v1/deletepayment — delete a receipt record.
 */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/addcontra',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Contra( $wpdb );
					return $controller->reactContraPayment( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);


add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/updatecontra',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Contra( $wpdb );
					return $controller->reactContraPaymentUpdate( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);


add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/getcontra',
			array(
				'methods'             => 'GET',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Contra( $wpdb );
					return $controller->reactContraGet();
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/deletecontra',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Contra( $wpdb );
					return $controller->reactContraDelete( $request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);



////////rreports generation///////////////////

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'taxer/v1',
			'/getreportpurchase',
			array(
				'methods'             => 'GET',
				'callback'            => function ( WP_REST_Request $request ) {
					global $wpdb;
					$controller = new Purchase( $wpdb );
					return $controller->GetPurchaseDetails($request );
				},
				'permission_callback' => '__return_true',
			)
		);
	}
);