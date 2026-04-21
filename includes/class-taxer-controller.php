<?php
/**
 * Taxer Controller
 *
 * Bridges HTTP requests (admin page loads and AJAX calls) with the model
 * and view. Owns nonce verification and capability checks for all actions.
 *
 * @package Taxer
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxer_Controller
 */
class Taxer_Controller {

	/** @var Taxer_Model */
	private $model;

	/** @var Taxer_View */
	private $view;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->model = new Taxer_Model( $wpdb );
		$this->view  = new Taxer_View();
	}

	// ── Admin page entry point ─────────────────────────────────────────────────

	/**
	 * Main entry point called by the admin page callback.
	 *
	 * Flow:
	 *  1. Ensure the company table exists (safety net alongside activation hook).
	 *  2. Handle the company setup / edit form submission (POST).
	 *  3. Render either the setup form or the main dashboard.
	 *
	 * @return void
	 */
	public function starter() {
		// Step 1: Table safety net.
		if ( ! $this->model->company_table_exists() ) {
			$this->model->create_company_table();
		}

		// Step 2: Handle POST submission.
		if (
			isset( $_POST['taxer_save_company'] ) &&
			check_admin_referer( 'taxer_save_company_action', 'taxer_company_nonce' )
		) {
			$this->save_company();
			return;
		}

		// Step 3: Render the correct view.
		$company = $this->model->get_company();

		if ( empty( $company ) ) {
			$this->view->render_company_setup_form();
		} else {
			$this->view->render_dashboard( $company );
		}
	}

	/**
	 * Return company data for the React frontend.
	 *
	 * @return WP_REST_Response
	 */
	public function toreactfrontend() {
		$company = $this->model->get_company();

		return rest_ensure_response(
			array(
				'company' => $company,
			)
		);
	}

/**
	 * Return company reportdata for the React frontend.
	 *
	 * @return WP_REST_Response
	 */

	private function checktype($re){


			$sales = $this->model->check_get_sales_report($re);

			if(!empty($sales)){

				return 'sales';

			}

			$purchase = $this->model->check_get_purchase_report($re);


			if(!empty($purchase)){

				return 'purchase';

			}


	}


	public function FullReport(WP_REST_Request $request) {

		$report = $this->model->generatereport();

		


		$fullreport=array();

		foreach($report as $rep){


			$fullreportpre=array();

			//print_r($rep->transaction_id);

			  $checktypeoftransaction=$this->checktype($rep->transaction_id);

			  $fullreportpre['data']=$rep;

			  $fullreportpre['type']=$checktypeoftransaction;

			  $fullreport[]=$fullreportpre;

		}

		//print_r($report);
 

		return rest_ensure_response(
			array(
				'reports' => $fullreport,
			)
		);
	}


	// ── Private helpers ────────────────────────────────────────────────────────

	/**
	 * Handle the company setup / edit form POST.
	 * Sanitisation is delegated to the model layer.
	 *
	 * @return void
	 */
	private function save_company() {
		// Collect and whitelist raw POST values; defaults prevent undefined-index notices.
		$data = array(
			'company_name'    => isset( $_POST['company_name'] )    ? wp_unslash( $_POST['company_name'] )    : '',
			'company_address' => isset( $_POST['company_address'] ) ? wp_unslash( $_POST['company_address'] ) : '',
			'company_trn'     => isset( $_POST['company_trn'] )     ? wp_unslash( $_POST['company_trn'] )     : '',
		);

		// Basic server-side validation.
		if ( empty( trim( $data['company_name'] ) ) || empty( trim( $data['company_trn'] ) ) ) {
			$this->view->render_company_setup_form( null, esc_html__( 'Company name and TRN are required.', 'taxer' ) );
			return;
		}

		$company = $this->model->get_company();

		if ( empty( $company ) ) {
			$this->model->insert_company( $data );
		} else {
			$this->model->update_company( $company->company_id, $data );
		}

		// Safe redirect — avoids JS-based reload and prevents resubmit on F5.
		wp_safe_redirect(
			add_query_arg( 'taxer_saved', '1', menu_page_url( 'tax-manager', false ) )
		);
		exit;
	}

	// ── AJAX handlers ──────────────────────────────────────────────────────────

	/**
	 * AJAX: return the company record as JSON.
	 * Nonce and capability are already verified in taxer.php before this runs.
	 *
	 * @return void  Terminates with wp_send_json_*.
	 */
	public function ajax_get_company() {
		$company = $this->model->get_company();

		if ( $company ) {
			// Sanitise output — data comes straight from DB.
			wp_send_json_success(
				array(
					'company_id'      => absint( $company->company_id ),
					'company_name'    => esc_html( $company->company_name ),
					'company_address' => esc_textarea( $company->company_address ),
					'company_trn'     => esc_html( $company->company_trn ),
					'company_amount'     => esc_html( $company->company_amount ),
					'company_data'    => esc_html( $company->company_data ),
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'No company record found.', 'taxer' ), 404 );
		}
	}

	/**
	 * AJAX: update the company record.
	 * Nonce and capability are already verified in taxer.php before this runs.
	 *
	 * @return void  Terminates with wp_send_json_*.
	 */
	public function ajax_update_company() {
		$id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'Invalid company ID.', 'taxer' ), 400 );
		}

		$data = array(
			'company_name'    => isset( $_POST['company_name'] )    ? wp_unslash( $_POST['company_name'] )    : '',
			'company_address' => isset( $_POST['company_address'] ) ? wp_unslash( $_POST['company_address'] ) : '',
			'company_trn'     => isset( $_POST['company_trn'] )     ? wp_unslash( $_POST['company_trn'] )     : '',
			'company_amount'     => isset( $_POST['company_amount'] )     ? wp_unslash( $_POST['company_amount'] )     : '',
			'company_data'    => isset( $_POST['company_data'] )    ? wp_unslash( $_POST['company_data'] )    : '',
		);

		if ( empty( trim( $data['company_name'] ) ) || empty( trim( $data['company_trn'] ) ) ) {
			wp_send_json_error( esc_html__( 'Company name and TRN are required.', 'taxer' ), 422 );
		}

		$result = $this->model->update_company( $id, $data );

		if ( false !== $result ) {
			wp_send_json_success( esc_html__( 'Company updated successfully.', 'taxer' ) );
		} else {
			wp_send_json_error( esc_html__( 'Database update failed. Please try again.', 'taxer' ), 500 );
		}
	}

	// ── REST handlers ──────────────────────────────────────────────────────────

	/**
	 * REST: update company record from React frontend.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reactUpdate( WP_REST_Request $request ) {
		$id              = intval( $request->get_param( 'id' ) );
		$company_trn     = sanitize_text_field( $request->get_param( 'trn' ) );
		$company_name    = sanitize_text_field( $request->get_param( 'name' ) );
		$company_address = sanitize_text_field( $request->get_param( 'address' ) );
		$tax_id          = sanitize_text_field( $request->get_param( 'tax_id' ) );
		$company_amount          = sanitize_text_field( $request->get_param( 'amount' ) );

		$data = compact( 'company_name', 'company_address', 'company_trn', 'tax_id', 'company_amount' );

		$result = $this->model->update_company( $id, $data );

		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Company updated successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database update failed.',
			array( 'status' => 500 )
		);
	}

	/**
	 * REST: insert a new stock item.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reactStockInsert( WP_REST_Request $request ) {
		$company_id   = intval( $request->get_param( 'company_id' ) );
		$stocks_name  = sanitize_text_field( $request->get_param( 'stocks_name' ) );
		$stocks_price = sanitize_text_field( $request->get_param( 'stocks_price' ) );
		$stocks_total = sanitize_text_field( $request->get_param( 'stocks_total' ) );
		$stocks_unit  = sanitize_text_field( $request->get_param( 'stocks_unit' ) );

		$data = array(
			'company_id'   => $company_id,
			'stocks_name'  => $stocks_name,
			'stocks_price' => $stocks_price,
			'stocks_total' => $stocks_total,
			'stocks_unit'  => $stocks_unit,
			'stocks_image' => '',
		);

		// Handle optional file upload.
		if ( ! empty( $_FILES['stocks_image']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_upload( 'stocks_image', 0 );
			if ( ! is_wp_error( $attachment_id ) ) {
				$data['stocks_image'] = wp_get_attachment_url( $attachment_id );
			}
		}

		





		$company = $this->model->get_company();

		if(!empty($company)){


		
		 
		 $stcoktotalcurretnt=$stocks_price*$stocks_total;


		$newcompanyamount=$company->company_amount-$stcoktotalcurretnt;




		if($newcompanyamount >=1){

			$result = $this->model->insert_stock( $data );

				$this->model->update_company_amount( $newcompanyamount,$company->company_id );
		}

		

		




		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Stock inserted successfully.',
				)
			);
		}

	}else{

		return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Please allocate Company amount.',
				)
			);


	}

		return new WP_Error(
			'db_error',
			'Database insert failed. Please try again.',
			array( 'status' => 500 )
		);
	}

	/**
	 * REST: return all stock records for the React frontend.
	 *
	 * @return WP_REST_Response
	 */
	public function toreactfrontendstock() {
		$stock = $this->model->get_stock();

		return rest_ensure_response(
			array(
				'stock' => $stock,
			)
		);
	}

	/**
	 * Delegate DB activation to the model.
	 *
	 * @return void
	 */
	public function activate_db() {
		$this->model->activate_company();
	}

	/**
	 * Delegate DB deactivation to the model.
	 *
	 * @return void
	 */
	public function deactivate_db() {
		$this->model->deactivate_company();
	}

	/**
	 * REST: update an existing stock item.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reactStockUpdate( WP_REST_Request $request ) {
		$stocks_id    = intval( $request->get_param( 'stocks_id' ) );
		$company_id   = intval( $request->get_param( 'company_id' ) );
		$stocks_name  = sanitize_text_field( $request->get_param( 'stocks_name' ) );
		$stocks_price = sanitize_text_field( $request->get_param( 'stocks_price' ) );
		$stocks_total = sanitize_text_field( $request->get_param( 'stocks_total' ) );
		$stocks_unit  = sanitize_text_field( $request->get_param( 'stocks_unit' ) );

		$data = array(
			'company_id'   => $company_id,
			'stocks_name'  => $stocks_name,
			'stocks_price' => $stocks_price,
			'stocks_total' => $stocks_total,
			'stocks_unit'  => $stocks_unit,
		);

		// Handle optional file upload.
		if ( ! empty( $_FILES['stocks_image']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_upload( 'stocks_image', 0 );
			if ( ! is_wp_error( $attachment_id ) ) {
				$data['stocks_image'] = wp_get_attachment_url( $attachment_id );
			}
		}




		$company = $this->model->get_company_by_idee($company_id);


		//manage old value before update 

		$currentstocks = $this->model->get_by_id_stock($stocks_id);


		 $oldmanagingamount=$currentstocks->stocks_price*$currentstocks->stocks_total;


		$newcompanyamount=$company->company_amount+$oldmanagingamount;


		$this->model->update_company_amount( $newcompanyamount,$company_id );	


		$result = $this->model->update_stock( $stocks_id, $data );

		if ( false !== $result ) {


			$company = $this->model->get_company_by_idee($company_id);


			$currentstocks = $this->model->get_by_id_stock($stocks_id);


		 $oldmanagingamount=$currentstocks->stocks_price*$currentstocks->stocks_total;


		$newcompanyamount=$company->company_amount-$oldmanagingamount;

		$this->model->update_company_amount( $newcompanyamount,$company_id );		 


			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Stock updated successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database update failed. Please try again.',
			array( 'status' => 500 )
		);
	}

	/**
	 * REST: delete a stock item.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function reactStockDelete( WP_REST_Request $request ) {


			$stocks_id = intval( $request->get_param( 'stocks_id' ) );

			$currentstocks = $this->model->get_by_id_stock($stocks_id);


			$company = $this->model->get_company_by_idee($currentstocks->company_id);

			 $oldmanagingamount=$currentstocks->stocks_price*$currentstocks->stocks_total;


			 $newcompanyamount=$company->company_amount+$oldmanagingamount;


			 $this->model->update_company_amount( $newcompanyamount,$currentstocks->company_id );	


		 


		$deleted   = $this->model->delete_stock( $stocks_id );

		if ( $deleted ) {
			return rest_ensure_response( array( 'success' => true,  'message' => 'Deleted!' ) );
		}

		return rest_ensure_response( array( 'success' => false, 'message' => 'Delete failed' ) );
	}

	/**
	 * REST: insert a new tax record.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function reactTaxInsert( WP_REST_Request $request ) {
		$tax_percent = sanitize_text_field( $request->get_param( 'tax_percentage' ) );
		$tax_name    = sanitize_text_field( $request->get_param( 'tax_name' ) );

		$data = array(
			'tax_name'    => $tax_name,
			'tax_percent' => $tax_percent,
		);

		$insert = $this->model->insertTax( $data );

		if ( $insert ) {
			return rest_ensure_response( array( 'success' => true,  'message' => 'Inserted!' ) );
		}

		return rest_ensure_response( array( 'success' => false, 'message' => 'Insert failed' ) );
	}

	/**
	 * REST: return all tax records.
	 *
	 * @return WP_REST_Response
	 */
	public function reactTaxGet() {
		$tax = $this->model->get_tax();

		return rest_ensure_response(
			array(
				'tax' => $tax,
			)
		);
	}

	/**
	 * REST: update an existing tax record.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reactTaxUpdate( WP_REST_Request $request ) {
		$tax_id      = intval( $request->get_param( 'tax_id' ) );
		$tax_name    = sanitize_text_field( $request->get_param( 'tax_name' ) );
		$tax_percent = sanitize_text_field( $request->get_param( 'tax_percentage' ) );

		$data = array(
			'tax_name'    => $tax_name,
			'tax_percent' => $tax_percent,
		);

		$result = $this->model->update_tax( $tax_id, $data );

		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Tax updated successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database update failed. Please try again.',
			array( 'status' => 500 )
		);
	}

	/**
	 * REST: delete a tax record.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function reactTaxDelete( WP_REST_Request $request ) {
		$tax_id  = intval( $request->get_param( 'tax_id' ) );
		$deleted = $this->model->delete_tax( $tax_id );

		if ( $deleted ) {
			return rest_ensure_response( array( 'success' => true,  'message' => 'Deleted!' ) );
		}

		return rest_ensure_response( array( 'success' => false, 'message' => 'Delete failed' ) );
	}





public function reactPaymentGet() {
		$payment = $this->model->get_payment();

		return rest_ensure_response(
			array(
				'payment' => $payment,
			)
		);
	}



}