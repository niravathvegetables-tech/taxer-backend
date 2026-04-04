<?php
/**
 * Taxer Model
 *
 * Handles all database interactions for the Taxer plugin.
 * Uses $wpdb prepared statements throughout — no raw interpolation.
 *
 * @package Taxer
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxer_Model
 */
class Taxer_Model {

	/** @var wpdb */
	private $wpdb;

	/** @var string Full table name for company records. */
	private $company_table;

	/** @var string Table name for stock records. */
	private $stocks_table;

	/** @var string Table name for tax records. */
	private $taxes_table;

	/** @var string Table name for transaction records. */
	private $transaction_table;

	/** @var string Table name for purchase records. */
	private $purchase_table;

	/** @var string Table name for receipt records. */
	private $receipt_table;

	/** @var string Table name for payment records. */
	private $payment_table;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database object.
	 */
	public function __construct( $wpdb ) {

		$this->wpdb              = $wpdb;
		$this->company_table     = $wpdb->prefix .'taxer_company';
		$this->stocks_table      = $wpdb->prefix .'taxer_stocks';
		$this->taxes_table       = $wpdb->prefix .'taxer_taxes';
		$this->transaction_table = $wpdb->prefix .'taxer_transaction';
		$this->purchase_table    = $wpdb->prefix .'taxer_purchase';
		$this->receipt_table     = $wpdb->prefix .'taxer_receipt';
		$this->payment_table     = $wpdb->prefix .'taxer_payment';
	}

	// ── Company methods ────────────────────────────────────────────────────────

	/**
	 * Check whether the company table exists in the database.
	 *
	 * @return bool
	 */
	public function company_table_exists() {
		$table  = $this->company_table;
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);
		return $result === $table;
	}

	/**
	 * Create the company table if it does not already exist.
	 *
	 * @return void
	 */
	public function create_company_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->wpdb->get_charset_collate();
		$table   = $this->company_table;

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			company_id      INT          NOT NULL AUTO_INCREMENT,
			company_name    VARCHAR(255) NOT NULL,
			company_address TEXT         NOT NULL,
			company_trn     VARCHAR(100) NOT NULL,
			tax_id          VARCHAR(100) NOT NULL,
			company_amount          VARCHAR(100) NOT NULL,
			PRIMARY KEY (company_id)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Retrieve the first (and only) company record.
	 *
	 * @return object|null
	 */
	public function get_company() {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $this->company_table ) . '` LIMIT %d',
				1
			)
		);
	}

public function get_company_by_idee($idee) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    return $this->wpdb->get_row(
        $this->wpdb->prepare(
            'SELECT * FROM `' . esc_sql( $this->company_table ) . '` WHERE company_id = %s LIMIT %d',
            $idee,
            1
        )
    );
}


	public function get_receipt() {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    return $this->wpdb->get_results(
        'SELECT * FROM `' . esc_sql( $this->receipt_table ) . '`'
    );
}


public function get_receipt_by_idee($idee) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    return $this->wpdb->get_row(
        $this->wpdb->prepare(
            'SELECT * FROM `' . esc_sql( $this->receipt_table ) . '` WHERE receipt_id = %s LIMIT %d',
            $idee,
            1
        )
    );
}

	/**
	 * Retrieve all stock records.
	 *
	 * @return array
	 */
	public function get_stock() {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $this->wpdb->get_results(
			'SELECT * FROM `' . esc_sql( $this->stocks_table ) . '`'
		);
	}

	/**
	 * Insert a new company record.
	 * Data is sanitised here so callers never need to remember.
	 *
	 * @param array $data {
	 *     @type string $company_name    Company display name.
	 *     @type string $company_address Full address.
	 *     @type string $company_trn     Tax registration number.
	 * }
	 * @return int|false Number of rows inserted, or false on error.
	 */
	public function insert_company( array $data ) {
		return $this->wpdb->insert(
			$this->company_table,
			array(
				'company_name'    => sanitize_text_field( $data['company_name'] ),
				'company_address' => sanitize_textarea_field( $data['company_address'] ),
				'company_trn'     => sanitize_text_field( $data['company_trn'] ),
				'tax_id'          => 0,
				'company_amount'          => 0,
			),
			array( '%s', '%s', '%s', '%s','%s')
		);
	}

	/**
	 * Insert a new stock record.
	 *
	 * @param array $data Stock column => value pairs.
	 * @return int|false
	 */
	public function insert_stock( array $data ) {
		$result = $this->wpdb->insert(
			$this->stocks_table,
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( 'Taxer insert_stock failed: ' . $this->wpdb->last_error );
		}

		return $result;
	}

	/**
	 * Update an existing company record by ID.
	 *
	 * @param int   $id   company_id primary key.
	 * @param array $data Same keys as insert_company().
	 * @return int|false Rows updated or false on error.
	 */
	public function update_company( $id, array $data ) {
		$update_data = array();
		$format      = array();

		if ( ! empty( $data['company_name'] ) ) {
			$update_data['company_name'] = sanitize_text_field( $data['company_name'] );
			$format[]                    = '%s';
		}

		if ( ! empty( $data['company_address'] ) ) {
			$update_data['company_address'] = sanitize_textarea_field( $data['company_address'] );
			$format[]                       = '%s';
		}

		if ( ! empty( $data['company_trn'] ) ) {
			$update_data['company_trn'] = sanitize_text_field( $data['company_trn'] );
			$format[]                   = '%s';
		}

		if ( ! empty( $data['company_data'] ) ) {
			$update_data['company_data'] = sanitize_text_field( $data['company_data'] );
			$format[]                    = '%s';
		}

		if ( ! empty( $data['tax_id'] ) ) {
			$update_data['tax_id'] = sanitize_text_field( $data['tax_id'] );
			$format[]              = '%s';
		}

		


		if ( ! empty( $data['company_amount'] ) ) {
			$update_data['company_amount'] = sanitize_text_field( $data['company_amount'] );
			$format[]                    = '%s';
		}

		return $this->wpdb->update(
			$this->company_table,
			$update_data,
			array( 'company_id' => absint( $id ) ),
			$format,
			array( '%d' )
		);
	}

	// ── Generic tax-record methods ─────────────────────────────────────────────

	/**
	 * Insert a generic tax record row.
	 *
	 * @param array $data Column => value pairs.
	 * @return int|false
	 */
	public function insert( array $data ) {
		$table = $this->wpdb->prefix . 'taxer';
		return $this->wpdb->insert( $table, $data );
	}

	/**
	 * Update a generic tax record by ID.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Column => value pairs.
	 * @return int|false
	 */
	public function update( $id, array $data ) {
		$table = $this->wpdb->prefix . 'taxer';
		return $this->wpdb->update(
			$table,
			$data,
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Delete a generic tax record by ID.
	 *
	 * @param int $id Row ID.
	 * @return int|false
	 */
	public function delete( $id ) {
		$table = $this->wpdb->prefix . 'taxer';
		return $this->wpdb->delete(
			$table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * Return all generic tax records.
	 *
	 * @return array
	 */
	public function get_all() {
		$table = esc_sql( $this->wpdb->prefix . 'taxer' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM `' . $table . '` LIMIT %d', 9999 )
		);
	}

	/**
	 * Return a single generic tax record by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public function get_by_id( $id ) {
		$table = esc_sql( $this->wpdb->prefix . 'taxer' );
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM `' . $table . '` WHERE id = %d',
				absint( $id )
			)
		);
	}

public function get_by_id_stock( $id ) {
    return $this->wpdb->get_row(
        $this->wpdb->prepare(
            'SELECT * FROM `' . esc_sql( $this->stocks_table ) . '` WHERE stocks_id = %d',
            absint( $id )
        )
    );
}

	// ── Activation / deactivation ──────────────────────────────────────────────

	/**
	 * Create all plugin tables on activation.
	 *
	 * @return void
	 */
	public function activate_company() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->wpdb->get_charset_collate();

		// Stocks table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->stocks_table} (
			stocks_id    INT          NOT NULL AUTO_INCREMENT,
			company_id   INT          NOT NULL,
			stocks_name  TEXT         NOT NULL,
			stocks_price TEXT         NOT NULL,
			stocks_total TEXT         NOT NULL,
			stocks_image TEXT         NOT NULL,
			stocks_unit  VARCHAR(100) NOT NULL,
			PRIMARY KEY (stocks_id)
		) {$charset};";
		dbDelta( $sql );

		// Purchase table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->purchase_table} (
			purchase_id        INT          NOT NULL AUTO_INCREMENT,
			transaction_id     INT          NOT NULL,
			stocks_id          INT          NOT NULL,
			purchase_amount    TEXT         NOT NULL,
			purchase_count     TEXT         NOT NULL,
			purchase_item_type TEXT         NOT NULL,
			purchase_total     VARCHAR(100) NOT NULL,
			date               VARCHAR(100) NOT NULL,
			PRIMARY KEY (purchase_id)
		) {$charset};";
		dbDelta( $sql );

		// Transaction table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->transaction_table} (
			transaction_id    INT          NOT NULL AUTO_INCREMENT,
			company_id        INT          NOT NULL,
			transactionamount VARCHAR(100) NOT NULL,
			tax               VARCHAR(100) NOT NULL,
			Total             VARCHAR(100) NOT NULL,
			Date              VARCHAR(100) NOT NULL,
			PRIMARY KEY (transaction_id)
		) {$charset};";
		dbDelta( $sql );

		// Taxes table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->taxes_table} (
			tax_id      INT  NOT NULL AUTO_INCREMENT,
			tax_name    TEXT NOT NULL,
			tax_percent TEXT NOT NULL,
			PRIMARY KEY (tax_id)
		) {$charset};";
		dbDelta( $sql );

		// Company table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->company_table} (
			company_id      INT          NOT NULL AUTO_INCREMENT,
			company_name    VARCHAR(255) NOT NULL,
			company_address TEXT         NOT NULL,
			company_trn     VARCHAR(100) NOT NULL,
			company_data    VARCHAR(255) NOT NULL,
			tax_id          VARCHAR(100) NOT NULL DEFAULT '',
			company_amount          VARCHAR(100) NOT NULL DEFAULT '',
			PRIMARY KEY (company_id)
		) {$charset};";
		dbDelta( $sql );


		// Recept table.

		$sql = "CREATE TABLE IF NOT EXISTS {$this->receipt_table} (
		receipt_id      INT          NOT NULL AUTO_INCREMENT,
		company_id      VARCHAR(255) NOT NULL,
		receipt_name    VARCHAR(255) NOT NULL,
		receipt_amount  VARCHAR(100) NOT NULL,
		receipt_date    DATE         NOT NULL,
		PRIMARY KEY (receipt_id)
		) {$charset};";

		dbDelta( $sql );

		// Payment table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->payment_table} (
		payment_id      INT          NOT NULL AUTO_INCREMENT,
		company_id      VARCHAR(255) NOT NULL,
		payment_name    VARCHAR(255) NOT NULL,
		payment_amount  VARCHAR(100) NOT NULL,
		payment_date    DATE         NOT NULL,
		PRIMARY KEY (payment_id)
		) {$charset};";

		dbDelta( $sql );

		 

			 
	}

	/**
	 * Drop all plugin tables on deactivation (only when company_data = 'yes').
	 *
	 * @return void
	 */
	public function deactivate_company() {
		$need_to_delete = $this->get_company();

		if ( $need_to_delete && 'yes' === $need_to_delete->company_data ) {
			$tables = array(
				$this->stocks_table,
				$this->purchase_table,
				$this->transaction_table,
				$this->taxes_table,
				$this->company_table,
				$this->receipt_table,
				$this->payment_table,
			);

			foreach ( $tables as $table ) {
				$this->wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
			}
		}
	}

	// ── Stock CRUD ─────────────────────────────────────────────────────────────

	/**
	 * Update an existing stock record.
	 *
	 * @param int   $stocks_id Stock primary key.
	 * @param array $data      Column => value pairs.
	 * @return int|false
	 */
	public function update_stock( $stocks_id, array $data ) {
		$result = $this->wpdb->update(
			$this->stocks_table,
			$data,
			array( 'stocks_id' => $stocks_id ),
			array( '%d', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'Taxer update_stock failed: ' . $this->wpdb->last_error );
		}

		return $result;
	}

	/**
	 * Delete a stock record by ID.
	 *
	 * @param int $stocks_id Stock primary key.
	 * @return int|false
	 */
	public function delete_stock( $stocks_id ) {
		return $this->wpdb->delete(
			$this->stocks_table,
			array( 'stocks_id' => intval( $stocks_id ) )
		);
	}

	// ── Tax CRUD ───────────────────────────────────────────────────────────────

	/**
	 * Insert a new tax category record.
	 *
	 * @param array $data Column => value pairs.
	 * @return int|false
	 */
	public function insertTax( array $data ) {
		return $this->wpdb->insert( $this->taxes_table, $data );
	}

	/**
	 * Return all tax category records.
	 *
	 * @return array
	 */
	public function get_tax() {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $this->wpdb->get_results(
			'SELECT * FROM `' . esc_sql( $this->taxes_table ) . '`'
		);
	}

	/**
	 * Update an existing tax category record.
	 *
	 * @param int   $tax_id Tax primary key.
	 * @param array $data   Column => value pairs.
	 * @return int|false
	 */
	public function update_tax( $tax_id, array $data ) {
		$result = $this->wpdb->update(
			$this->taxes_table,
			$data,
			array( 'tax_id' => $tax_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'Taxer update_tax failed: ' . $this->wpdb->last_error );
		}

		return $result;
	}

	/**
	 * Delete a tax category record by ID.
	 *
	 * @param int $tax_id Tax primary key.
	 * @return int|false
	 */
	public function delete_tax( $tax_id ) {
		return $this->wpdb->delete(
			$this->taxes_table,
			array( 'tax_id' => intval( $tax_id ) )
		);
	}

	// ── Transaction / Purchase ─────────────────────────────────────────────────

	/**
	 * Insert a transaction record and return the new transaction ID.
	 *
	 * @param array $data Column => value pairs.
	 * @return int|false New transaction ID or false on failure.
	 */
	public function transaction( array $data ) {
		$result = $this->wpdb->insert(
			$this->transaction_table,
			$data,
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false !== $result ) {
			return $this->wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Insert a purchase record and adjust stock levels.
	 *
	 * @param array $data          Purchase column => value pairs.
	 * @param int   $stocks_id     Stock item to adjust.
	 * @param int   $purchase_count Quantity to add to stock.
	 * @return int|false
	 */
	public function purchase( array $data, $stocks_id, $purchase_count ) {
		$result = $this->wpdb->insert(
			$this->purchase_table,
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$this->adjuststock( $stocks_id, $purchase_count );

		return $result;
	}

	/**
	 * Adjust the total quantity of a stock item.
	 *
	 * @param int   $stocks_id     Stock primary key.
	 * @param float $purchase_count Quantity to add (negative to subtract).
	 * @return int|false
	 */
	public function adjuststock( $stocks_id, $purchase_count ) {
		$stock = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $this->stocks_table ) . '` WHERE stocks_id = %d',
				$stocks_id
			)
		);

		if ( ! $stock ) {
			error_log( 'Taxer adjuststock: stock not found for ID ' . $stocks_id );
			return false;
		}

		$new_total = floatval( $stock->stocks_total ) + floatval( $purchase_count );

		if ( $new_total < 0 ) {
			error_log( 'Taxer adjuststock: insufficient stock for ID ' . $stocks_id );
			return false;
		}

		return $this->wpdb->update(
			$this->stocks_table,
			array( 'stocks_total' => $new_total ),
			array( 'stocks_id'    => $stocks_id ),
			array( '%s' ),
			array( '%d' )
		);
	}





	public function update_company_amount($newcompanyamount,$company_id){


			$update_data['company_amount'] = sanitize_text_field( $newcompanyamount );
			$format[]                    = '%s';
		 

		return $this->wpdb->update(
			$this->company_table,
			$update_data,
			array( 'company_id' => absint( $company_id ) ),
			$format,
			array( '%d' )
		);

	}



	public function insert_receipt($data){


		 	$result = $this->wpdb->insert(
			$this->receipt_table,
			$data,
			array( '%d', '%s', '%s', '%s' )
		);

	}

	public function insert_payment($data){


		 	$result = $this->wpdb->insert(
			$this->payment_table,
			$data,
			array( '%d', '%s', '%s', '%s' )
		);

	}
 




/**
	 * Update an existing Recipt category record.
	 *
	 * @param int   $receipt_id Tax primary key.
	 * @param array $data   Column => value pairs.
	 * @return int|false
	 */
	public function update_reciept( $receipt_id, array $data ) {



		$result = $this->wpdb->update(
			$this->receipt_table,
			$data,
			array( 'receipt_id' => $receipt_id ),
			array( '%s', '%s', '%s', '%s'),
			array( '%d' )
		);

		if ( false === $result ) {
			error_log( 'Reciept update_tax failed: ' . $this->wpdb->last_error );
		}

		return $result;
	}



		public function delete_receipt( $receipt_id ) {

		return $this->wpdb->delete(
			$this->receipt_table,
			array( 'receipt_id' => intval( $receipt_id ) )
		);
	}



} //end of classs paranthesis