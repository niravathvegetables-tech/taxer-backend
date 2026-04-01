<?php
/**
 * Taxer View
 *
 * Renders all HTML for the Taxer plugin admin screens.
 * Every dynamic value is escaped before output — no exceptions.
 *
 * @package Taxer
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Taxer_View
 */
class Taxer_View {

	// ── Company setup / edit form ──────────────────────────────────────────────

	/**
	 * Render the company setup (or edit) form.
	 *
	 * @param object|null $company Existing company object, or null for first-time setup.
	 * @param string      $error   Optional error message to display.
	 * @return void
	 */
	public function render_company_setup_form( $company = null, $error = '' ) {
		$name         = esc_attr( $company->company_name    ?? '' );
		$address      = esc_textarea( $company->company_address ?? '' );
		$trn          = esc_attr( $company->company_trn     ?? '' );
		$company_data = esc_attr( $company->company_data    ?? '' );

		$heading     = $company
			? esc_html__( 'Edit Company', 'taxer' )
			: esc_html__( 'Company Setup', 'taxer' );

		$button_text = $company
			? esc_html__( 'Update Company', 'taxer' )
			: esc_html__( 'Save & Continue', 'taxer' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['taxer_saved'] ) && '1' === $_GET['taxer_saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Company details saved.', 'taxer' )
				. '</p></div>';
		}
		?>
		<div class="wrap taxer-wrap">
			<h1><?php echo esc_html( $heading ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $company ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'No company found.', 'taxer' ); ?></strong>
						<?php esc_html_e( 'Please fill in your company details to get started.', 'taxer' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'taxer_save_company_action', 'taxer_company_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="company_name">
								<?php esc_html_e( 'Company Name', 'taxer' ); ?>
								<span class="taxer-required" aria-hidden="true">*</span>
							</label>
						</th>
						<td>
							<input type="text" name="company_name" id="company_name"
								class="regular-text" value="<?php echo $name; ?>"
								maxlength="255" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="company_address">
								<?php esc_html_e( 'Company Address', 'taxer' ); ?>
							</label>
						</th>
						<td>
							<textarea name="company_address" id="company_address"
								class="regular-text" rows="4"><?php echo $address; ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="company_trn">
								<?php esc_html_e( 'TRN Number', 'taxer' ); ?>
								<span class="taxer-required" aria-hidden="true">*</span>
							</label>
						</th>
						<td>
							<input type="text" name="company_trn" id="company_trn"
								class="regular-text" value="<?php echo $trn; ?>"
								maxlength="100" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="company_data">
								<?php esc_html_e( 'Company Data', 'taxer' ); ?>
								<span class="taxer-required" aria-hidden="true">*</span>
							</label>
						</th>
						<td>
							<input type="checkbox" name="company_data" id="company_data"
								class="regular-text" value="<?php echo $company_data; ?>"
								maxlength="100" required>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="taxer_save_company" class="button button-primary">
						<?php echo esc_html( $button_text ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	// ── Main dashboard ─────────────────────────────────────────────────────────

	/**
	 * Render the main dashboard with tab navigation.
	 *
	 * @param object $company Company DB row.
	 * @return void
	 */
	public function render_dashboard( $company ) {
		?>
		<div class="wrap taxer-wrap">
			<h1><?php esc_html_e( 'Tax Manager Dashboard', 'taxer' ); ?></h1>

			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: company name */
						esc_html__( 'Welcome, %s', 'taxer' ),
						'<strong>' . esc_html( $company->company_name ) . '</strong>'
					);
					?>
				</p>
			</div>

			<!-- ── Tab navigation ────────────────────────────────────────── -->
			<div class="taxer-tabs">

				<ul class="taxer-tab-nav" role="tablist">
					<li role="presentation">
						<button type="button" class="taxer-tab-btn active"
							role="tab" aria-selected="true"
							aria-controls="tab-company" data-tab="company">
							<?php esc_html_e( 'Company Details', 'taxer' ); ?>
						</button>
					</li>
					<li role="presentation">
						<button type="button" class="taxer-tab-btn"
							role="tab" aria-selected="false"
							aria-controls="tab-voucher" data-tab="voucher">
							<?php esc_html_e( 'Voucher', 'taxer' ); ?>
						</button>
					</li>
					<li role="presentation">
						<button type="button" class="taxer-tab-btn"
							role="tab" aria-selected="false"
							aria-controls="tab-ledger" data-tab="ledger">
							<?php esc_html_e( 'Ledger', 'taxer' ); ?>
						</button>
					</li>
					<li role="presentation">
						<button type="button" class="taxer-tab-btn"
							role="tab" aria-selected="false"
							aria-controls="tab-report" data-tab="report">
							<?php esc_html_e( 'Report', 'taxer' ); ?>
						</button>
					</li>
				</ul>

				<!-- Company Details panel -->
				<div id="tab-company" class="taxer-tab-panel" role="tabpanel">

					<table class="widefat fixed striped taxer-company-table">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Company Name', 'taxer' ); ?></th>
								<td><?php echo esc_html( $company->company_name ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Address', 'taxer' ); ?></th>
								<td><?php echo esc_html( $company->company_address ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'TRN Number', 'taxer' ); ?></th>
								<td><?php echo esc_html( $company->company_trn ); ?></td>
							</tr>
						</tbody>
					</table>

					<p class="taxer-actions">
						<button type="button" class="button button-secondary"
							id="taxer-open-modal" aria-haspopup="dialog">
							<?php esc_html_e( 'Edit Company', 'taxer' ); ?>
						</button>
					</p>

				</div><!-- #tab-company -->

				<!-- Voucher panel -->
				<div id="tab-voucher" class="taxer-tab-panel" role="tabpanel" hidden>
					<p><?php esc_html_e( 'Voucher content coming soon.', 'taxer' ); ?></p>
				</div>

				<!-- Ledger panel -->
				<div id="tab-ledger" class="taxer-tab-panel" role="tabpanel" hidden>
					<p><?php esc_html_e( 'Ledger content coming soon.', 'taxer' ); ?></p>
				</div>

				<!-- Report panel -->
				<div id="tab-report" class="taxer-tab-panel" role="tabpanel" hidden>
					<p><?php esc_html_e( 'Report content coming soon.', 'taxer' ); ?></p>
				</div>

			</div><!-- .taxer-tabs -->

			<?php $this->render_modal(); ?>

		</div><!-- .wrap -->
		<?php
	}

	// ── Edit modal ─────────────────────────────────────────────────────────────

	/**
	 * Render the hidden edit-company modal markup.
	 * Content is populated via AJAX once the modal opens.
	 *
	 * @return void
	 */
	private function render_modal() {
		?>
		<div id="taxer-company-modal" class="taxer-modal"
			role="dialog" aria-modal="true"
			aria-labelledby="taxer-modal-title"
			style="display:none;">
			<div id="taxer-modal-overlay" class="taxer-modal-overlay" aria-hidden="true"></div>
			<div id="taxer-modal-box" class="taxer-modal-box">
				<h2 id="taxer-modal-title"><?php esc_html_e( 'Edit Company', 'taxer' ); ?></h2>
				<div id="taxer-modal-body">
					<p><?php esc_html_e( 'Loading&hellip;', 'taxer' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}
