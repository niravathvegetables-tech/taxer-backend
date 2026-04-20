<?php
/**
 * Sales handler for Taxer plugin.
 *
 * @package Taxer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sales
 */
class Sales {

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







	public function reactTaxSales( WP_REST_Request $request ) {

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
		$salesses_raw = $request->get_param( 'saleses' );

		$saleses     = is_array( $salesses_raw )
			? $salesses_raw
			: json_decode( $salesses_raw, true );

		if ( ! is_array( $saleses ) || empty( $saleses ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No valid saleses data',
				),
				400
			);
		}

		$inserted_count = 0;
		$errors         = array();

		foreach ( $saleses as $row ) {

			if ( empty( $row['stocks_id'] ) || empty( $row['sales_amount'] ) ) {

				$errors[] = 'Invalid row: ' . wp_json_encode( $row );

				continue;

			}

			$sales_data = array(
				'transaction_id'     => intval( $transaction_id ),
				'stocks_id'          => intval( $row['stocks_id'] ),
				'sales_amount'    => sanitize_text_field( $row['sales_amount'] ),
				'sales_count'     => sanitize_text_field( $row['sales_count'] ),
				'sales_item_type' => sanitize_text_field( $row['sales_item_type'] ),
				'sales_total'     => sanitize_text_field( $row['sales_total'] ),
				'date'               => $date,
			);

			$result = $this->model->sales( $sales_data, $row['stocks_id'], $row['sales_count'] );

			if ( $result ) {
				$inserted_count++;
			} else {
				$errors[] = $this->wpdb->last_error;
			}
		}


		$company = $this->model->get_company_by_idee($company_id);


		$newcompanyamount=$company->company_amount+$total;

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




		public function GetSalesDetails(WP_REST_Request $request){



		$report = $this->model->get_sales_report();

		return rest_ensure_response(
			array(
				'getreport' => $report,
			)
		);

	}


}
