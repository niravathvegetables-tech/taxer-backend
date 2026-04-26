<?php
/**
 * Payment handler for Taxer plugin.
 *
 * @package Taxer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxer_Payment
 */
class Taxer_Payment {

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


	public function reactPaymentGet() {

		$payme = $this->model->get_payment();

		if ( $payme ) {
			return rest_ensure_response(
				array(
					'payment' => $payme,
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'No payment record found.', 'taxer' ), 404 );
		}
	}


	public function reactTaxPaymentUpdate( WP_REST_Request $request ) {

		$company_id     = intval( $request->get_param( 'company_id' ) );
		$payment_id     = intval( $request->get_param( 'payment_id' ) );
		$payment_name   = sanitize_text_field( $request->get_param( 'payment_name' ) );
		$payment_amount = sanitize_text_field( $request->get_param( 'payment_amount' ) );
		$payment_date   = sanitize_text_field( $request->get_param( 'payment_date' ) );


		$company      = $this->model->get_company_by_idee( $company_id );
		$prev_payment = $this->model->get_payment_by_idee( $payment_id );

		if ( ! $company || ! $prev_payment ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Record not found.',
				)
			);
		}

		// Subtract old amount from company
		$lesswitholdamount = $company->company_amount + $prev_payment->payment_amount;
		$this->model->update_company_amount( $lesswitholdamount, $company_id );

		$data = array(
			'company_id'     => $company_id,
			'payment_name'   => $payment_name,
			'payment_amount' => $payment_amount,
			'payment_date'   => $payment_date,
		);

		$result = $this->model->update_payment( $payment_id, $data );

		// Add new amount to company
		$company          = $this->model->get_company_by_idee( $company_id );
		$newaddedamount   = $company->company_amount - $payment_amount;
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


	public function reactPaymentDelete( WP_REST_Request $request ) {

		$body = $request->get_json_params();

		$company_id = intval( $body['company_id'] );
		$payment_id = intval( $body['payment_id'] );

		$company      = $this->model->get_company_by_idee( $company_id );
		$prev_payment = $this->model->get_payment_by_idee( $payment_id );

		if ( ! $company || ! $prev_payment ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Record not found.',
				)
			);
		}

		// Subtract deleted receipt amount from company
		$lesswitholdamount = $company->company_amount + $prev_payment->payment_amount;
		$this->model->update_company_amount( $lesswitholdamount, $company_id );

		$deleted = $this->model->delete_payment( $payment_id );

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