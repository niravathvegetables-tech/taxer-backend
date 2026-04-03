<?php
/**
 * Purchase handler for Taxer plugin.
 *
 * @package Taxer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Purchase
 *
 * Handles purchase transaction REST requests.
 */
class Purchase {

	/** @var Taxer_Model */
	private $model;

	/** @var Taxer_View */
	private $view;

	/** @var wpdb */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->model = new Taxer_Model( $wpdb );
		$this->view  = new Taxer_View();
		$this->wpdb  = $wpdb;
	}

	/**
	 * REST: record a purchase (transaction + line items).
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function reactTaxPurchase( WP_REST_Request $request ) {

		$company_id        = intval( $request->get_param( 'company_id' ) );
		$transactionamount = sanitize_text_field( $request->get_param( 'sub_total' ) );
		$tax               = sanitize_text_field( $request->get_param( 'tax_amount' ) );
		$total             = sanitize_text_field( $request->get_param( 'grand_total' ) );
		$date              = sanitize_text_field( $request->get_param( 'date' ) );

		$data_transaction = array(
			'company_id'        => $company_id,
			'transactionamount' => $transactionamount,
			'tax'               => $tax,
			'Total'             => $total,
			'date'              => $date,
		);

		$transaction_id = $this->model->transaction( $data_transaction );

		if ( ! $transaction_id ) {
			return new WP_REST_Response(
				array(
					'success'  => false,
					'message'  => 'Transaction insert failed',
					'db_error' => $this->wpdb->last_error,
				),
				500
			);
		}

		// Handle both raw JSON string and already-decoded array.
		$purchases_raw = $request->get_param( 'purchases' );

		$purchases     = is_array( $purchases_raw )
			? $purchases_raw
			: json_decode( $purchases_raw, true );

		if ( ! is_array( $purchases ) || empty( $purchases ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No valid purchases data',
				),
				400
			);
		}

		$inserted_count = 0;
		$errors         = array();

		foreach ( $purchases as $row ) {

			if ( empty( $row['stocks_id'] ) || empty( $row['purchase_amount'] ) ) {

				$errors[] = 'Invalid row: ' . wp_json_encode( $row );

				continue;

			}

			$purchase_data = array(
				'transaction_id'     => intval( $transaction_id ),
				'stocks_id'          => intval( $row['stocks_id'] ),
				'purchase_amount'    => sanitize_text_field( $row['purchase_amount'] ),
				'purchase_count'     => sanitize_text_field( $row['purchase_count'] ),
				'purchase_item_type' => sanitize_text_field( $row['purchase_item_type'] ),
				'purchase_total'     => sanitize_text_field( $row['purchase_total'] ),
				'date'               => $date,
			);

			$result = $this->model->purchase( $purchase_data, $row['stocks_id'], $row['purchase_count'] );

			if ( $result ) {
				$inserted_count++;
			} else {
				$errors[] = $this->wpdb->last_error;
			}
		}


		$company = $this->model->get_company_by_idee($company_id);


		$newcompanyamount=$company->company_amount-$total;

		 $this->model->update_company_amount( $newcompanyamount,$company_id );	

		return new WP_REST_Response(
			array(
				'success'        => true,
				'transaction_id' => $transaction_id,
				'inserted'       => $inserted_count,
				'errors'         => $errors,
			),
			200
		);
	}
}
