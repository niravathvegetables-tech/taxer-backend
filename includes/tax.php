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
class Tax {

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
}
