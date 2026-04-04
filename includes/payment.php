<?php
/**
 * Receipt handler for Taxer plugin.
 *
 * @package Taxer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Receipt
 */
class Payment {

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


	public function reactTaxPayment( WP_REST_Request $request ) {

		$company_id     = intval( $request->get_param( 'company_id' ) );
		$payment_name   = sanitize_text_field( $request->get_param( 'payment_name' ) );
		$payment_amount = sanitize_text_field( $request->get_param( 'payment_amount' ) );
		$payment_date   = sanitize_text_field( $request->get_param( 'payment_date' ) );

		$data = array(
			'company_id'     => $company_id,
			'payment_name'   => $payment_name,
			'payment_amount' => $payment_amount,
			'payment_date'   => $payment_date,
		);

		$result = $this->model->insert_payment( $data );

		$company          = $this->model->get_company_by_idee( $company_id );
		$newcompanyamount = $company->company_amount - $payment_amount;
		$this->model->update_company_amount( $newcompanyamount, $company_id );

		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Payment added successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database insert failed.',
			array( 'status' => 500 )
		);
	}


	public function reactReceiptGettttt() {

		$rece = $this->model->get_receipt();

		if ( $rece ) {
			return rest_ensure_response(
				array(
					'receipt' => $rece,
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'No receipt record found.', 'taxer' ), 404 );
		}
	}


	public function reactTaxReceiptUpdatettttttt( WP_REST_Request $request ) {

		$company_id     = intval( $request->get_param( 'company_id' ) );
		$receipt_id     = intval( $request->get_param( 'receipt_id' ) );
		$receipt_name   = sanitize_text_field( $request->get_param( 'receipt_name' ) );
		$receipt_amount = sanitize_text_field( $request->get_param( 'receipt_amount' ) );
		$receipt_date   = sanitize_text_field( $request->get_param( 'date' ) );

		$company      = $this->model->get_company_by_idee( $company_id );
		$prev_receipt = $this->model->get_receipt_by_idee( $receipt_id );

		if ( ! $company || ! $prev_receipt ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Record not found.',
				)
			);
		}

		// Subtract old amount from company
		$lesswitholdamount = $company->company_amount - $prev_receipt->receipt_amount;
		$this->model->update_company_amount( $lesswitholdamount, $company_id );

		$data = array(
			'company_id'     => $company_id,
			'receipt_name'   => $receipt_name,
			'receipt_amount' => $receipt_amount,
			'receipt_date'   => $receipt_date,
		);

		$result = $this->model->update_reciept( $receipt_id, $data );

		// Add new amount to company
		$company          = $this->model->get_company_by_idee( $company_id );
		$newaddedamount   = $company->company_amount + $receipt_amount;
		$this->model->update_company_amount( $newaddedamount, $company_id );

		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Receipt updated successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database update failed. Please try again.',
			array( 'status' => 500 )
		);
	}


	public function reactReceiptDeletetttttt( WP_REST_Request $request ) {

		$body = $request->get_json_params();

		$company_id = intval( $body['company_id'] );
		$receipt_id = intval( $body['receipt_id'] );

		$company      = $this->model->get_company_by_idee( $company_id );
		$prev_receipt = $this->model->get_receipt_by_idee( $receipt_id );

		if ( ! $company || ! $prev_receipt ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Record not found.',
				)
			);
		}

		// Subtract deleted receipt amount from company
		$lesswitholdamount = $company->company_amount - $prev_receipt->receipt_amount;
		$this->model->update_company_amount( $lesswitholdamount, $company_id );

		$deleted = $this->model->delete_receipt( $receipt_id );

		if ( $deleted ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Deleted!',
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'Delete failed.',
			)
		);
	}
}