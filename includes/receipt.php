<?php
/**
 * Tax handler for Taxer plugin.
 *
 * @package Taxer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax
 */
class Receipt {

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


	public function reactTaxReceipt(WP_REST_Request $request){


		 

		$company_id   = intval( $request->get_param( 'company_id' ) );
		$receipt_name  = sanitize_text_field( $request->get_param( 'receipt_name' ) );
		$receipt_amount = sanitize_text_field( $request->get_param( 'receipt_amount' ) );
		$receipt_date = sanitize_text_field( $request->get_param( 'date' ) );
		 

		$data = array(
			'company_id'   => $company_id,
			'receipt_name'  => $receipt_name,
			'receipt_amount' => $receipt_amount,
			'receipt_date' => $receipt_date
			
		);

		$result = $this->model->insert_receipt( $data );


		$company = $this->model->get_company_by_idee($company_id);

		$newcompanyamount=$company->company_amount+$receipt_amount;


		$this->model->update_company_amount( $newcompanyamount,$company_id );




		 	if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Reciept updated successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database update failed.',
			array( 'status' => 500 )
		);


	}


	public function reactReceiptGet(){

			$rece = $this->model->get_receipt();

			if ( $rece ) {
			// Sanitise output — data comes straight from DB.
			 return rest_ensure_response(
			array(
				'receipt' => $rece,
			)
		);
		} else {
			wp_send_json_error( esc_html__( 'No reciept record found.', 'taxer' ), 404 );
		}


	}




}
