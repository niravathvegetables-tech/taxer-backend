<?php
/**
 * Contra (Bank) handler for Taxer plugin.
 *
 * @package Taxer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxer_Contra
 */
class Taxer_Contra {

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


	public function reactContraPayment( WP_REST_Request $request ) {

		$company_id     = intval( $request->get_param( 'company_id' ) );
		$contra_name   = sanitize_text_field( $request->get_param( 'contra_name' ) );
		$contra_amount = sanitize_text_field( $request->get_param( 'contra_amount' ) );
		$contra_date   = sanitize_text_field( $request->get_param( 'contra_date' ) );

		$data = array(
			'company_id'     => $company_id,
			'contra_name'   => $contra_name,
			'contra_amount' => $contra_amount,
			'contra_date'   => $contra_date,
		);

		$result = $this->model->insert_contra( $data );

		$company          = $this->model->get_company_by_idee( $company_id );
		$newcompanyamount = $company->company_amount - $contra_amount;
		$this->model->update_company_amount( $newcompanyamount, $company_id );

		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Bank added successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database insert failed.',
			array( 'status' => 500 )
		);
	}


	public function reactContraGet() {

		$payme = $this->model->get_contra();

		if ( $payme ) {
			return rest_ensure_response(
				array(
					'contra' => $payme,
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'No payment record found.', 'taxer' ), 404 );
		}
	}


	public function reactContraPaymentUpdate( WP_REST_Request $request ) {

		$company_id     = intval( $request->get_param( 'company_id' ) );
		$contra_id     = intval( $request->get_param( 'contra_id' ) );
		$contra_name   = sanitize_text_field( $request->get_param( 'contra_name' ) );
		$contra_amount = sanitize_text_field( $request->get_param( 'contra_amount' ) );
		$contra_date   = sanitize_text_field( $request->get_param( 'contra_date' ) );


		$company      = $this->model->get_company_by_idee( $company_id );
		$prev_payment = $this->model->get_contra_by_idee( $contra_id );

		if ( ! $company || ! $prev_payment ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Record not found.',
				)
			);
		}

		// Subtract old amount from company
		$lesswitholdamount = $company->company_amount + $prev_payment->contra_amount;
		$this->model->update_company_amount( $lesswitholdamount, $company_id );

		$data = array(
			'company_id'     => $company_id,
			'contra_name'   => $contra_name,
			'contra_amount' => $contra_amount,
			'contra_date'   => $contra_date,
		);

		$result = $this->model->update_contra( $contra_id, $data );

		// Add new amount to company
		$company          = $this->model->get_company_by_idee( $company_id );
		$newaddedamount   = $company->company_amount - $contra_amount;
		$this->model->update_company_amount( $newaddedamount, $company_id );

		if ( false !== $result ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'Bank updated successfully.',
				)
			);
		}

		return new WP_Error(
			'db_error',
			'Database update failed. Please try again.',
			array( 'status' => 500 )
		);
	}


	public function reactContraDelete( WP_REST_Request $request ) {

		$body = $request->get_json_params();

		$company_id = intval( $body['company_id'] );
		$contra_id = intval( $body['contra_id'] );

		$company      = $this->model->get_company_by_idee( $company_id );
		$prev_payment = $this->model->get_contra_by_idee( $contra_id );

		if ( ! $company || ! $prev_payment ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'Record not found.',
				)
			);
		}

		// add deleted receipt amount from company
		$lesswitholdamount = $company->company_amount + $prev_payment->contra_amount;
		$this->model->update_company_amount( $lesswitholdamount, $company_id );

		$deleted = $this->model->delete_contra( $contra_id );

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