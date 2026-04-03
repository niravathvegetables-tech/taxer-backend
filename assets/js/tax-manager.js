/**
 * tax-manager.js
 *
 * Admin JS for the Taxer plugin.
 * Depends on: jQuery (WordPress bundled), TaxerAjax (wp_localize_script)
 *
 * Security notes:
 *  - All AJAX calls include a nonce verified server-side.
 *  - User-supplied values are treated as plain text (escHtml) before injecting
 *    into innerHTML, preventing stored XSS in the modal.
 *  - sendJson uses $.ajax with explicit type:'POST' — no GET requests with data.
 */

/* global TaxerAjax */
( function ( $ ) {
	'use strict';

	// ── DOM references (set once the page is ready) ────────────────────────────
	var $modal, $overlay, $body;

	// ── Helpers ────────────────────────────────────────────────────────────────

	/**
	 * Escape a value for safe insertion into HTML.
	 *
	 * @param  {*}      value  Any value.
	 * @return {string}        HTML-safe string.
	 */
	function escHtml( value ) {
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/**
	 * Show a status message inside the modal.
	 *
	 * @param {string} type  'success' | 'error'
	 * @param {string} msg   Plain-text message (will be escaped).
	 */
	function showModalMsg( type, msg ) {
		var cls = 'success' === type ? 'notice-success' : 'notice-error';
		$( '#taxer-modal-msg' )
			.removeClass( 'notice-success notice-error' )
			.addClass( cls )
			.html( '<p>' + escHtml( msg ) + '</p>' )
			.show();
	}

	/**
	 * Show a generic AJAX error inside the modal.
	 */
	function showAjaxError() {
		showModalMsg( 'error', 'An error occurred. Please refresh and try again.' );
	}

	// ── Modal lifecycle ────────────────────────────────────────────────────────

	/**
	 * Open the edit-company modal and fetch the current company data via AJAX.
	 */
	function openModal() {
		$modal.show();
		$body.html( '<p>Loading&hellip;</p>' );

		$.ajax( {
			url:  TaxerAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'taxer_get_company',
				nonce:  TaxerAjax.nonce
			},
			success: function ( response ) {
				if ( response.success ) {
					renderModalForm( response.data );
				} else {
					$body.html(
						'<p class="taxer-error">' +
						escHtml( response.data || 'Failed to load company data.' ) +
						'</p>'
					);
				}
			},
			error: showAjaxError
		} );
	}

	/**
	 * Close the modal and reset its body.
	 */
	function closeModal() {
		$modal.hide();
		$body.html( '<p>Loading&hellip;</p>' );
	}

	// ── Modal form ─────────────────────────────────────────────────────────────

	/**
	 * Inject the edit form into the modal body.
	 * All company values run through escHtml before touching the DOM.
	 *
	 * @param {Object} company  Response data from taxer_get_company.
	 */
	function renderModalForm( company ) { console.log(company);

	var cheked='checked';
	var checkval='yes';
	if(company.company_data =='no'){

		 cheked='';

		 checkval='no';

	}
		var html =
			'<button type="button" id="taxer-modal-close" class="taxer-modal-close" aria-label="Close">&times;</button>' +
			'<table class="form-table" role="presentation">' +
				'<tr>' +
					'<th scope="row"><label for="m_company_name">Company Name </label></th>' +
					'<td><input type="text" id="m_company_name" class="regular-text" value="' + escHtml( company.company_name ) + '" maxlength="255" required></td>' +
				'</tr>' +
				'<tr>' +
					'<th scope="row"><label for="m_company_address">Address</label></th>' +
					'<td><textarea id="m_company_address" class="regular-text" rows="3" required>' + escHtml( company.company_address ) + '</textarea></td>' +
				'</tr>' +
				'<tr>' +
					'<th scope="row"><label for="m_company_trn">TRN Number</label></th>' +
					'<td><input type="text" id="m_company_trn" class="regular-text" value="' + escHtml( company.company_trn ) + '" maxlength="100" required></td>' +
				'</tr>' +

				'<tr>' +
					'<th scope="row"><label for="m_company_trn">Amount</label></th>' +
					'<td><input type="text" id="company_amount" class="regular-text" value="' + escHtml( company.company_amount ) + '" maxlength="100" required></td>' +
				'</tr>' +

				'<tr>' +
					'<th scope="row"><label for="m_company_trn">Delete Data when deactivating the plugin.</label></th>' +
					'<td><input type="checkbox" id="m_company_data" class="regular-text" '+cheked+' value="' + escHtml( checkval ) + '" maxlength="100" required></td>' +
				'</tr>' +

			'</table>' +
			'<div id="taxer-modal-msg" class="notice" style="display:none;"></div>' +
			'<p class="submit">' +
				'<button type="button" id="taxer-save-btn" class="button button-primary" data-id="' + escHtml( company.company_id ) + '">Update Company</button>' +
				'<button type="button" id="taxer-cancel-btn" class="button" style="margin-left:8px;">Cancel</button>' +
			'</p>';

		$body.html( html );
	}

	// ── Save company (delegated — runs after modal AJAX populates form) ─────────

	/**
	 * Collect form values, validate, and POST to taxer_update_company.
	 */
	function saveCompany() {
		var companyId = $( '#taxer-save-btn' ).data( 'id' );
		var name      = $.trim( $( '#m_company_name' ).val() );
		var address   = $.trim( $( '#m_company_address' ).val() );
		var trn       = $.trim( $( '#m_company_trn' ).val() );

		var company_amount       = $.trim( $( '#company_amount' ).val() );

		var isChecked = $('#m_company_data').is(':checked');

		var m_company_data='';


		if(isChecked){


		 m_company_data='yes';
		}else{

					 m_company_data='no';
		}


		// Client-side validation — server duplicates this check.
		if ( ! name || ! trn ) {
			showModalMsg( 'error', 'Company name and TRN number are required.' );
			return;
		}

		// Disable the button to prevent double-submit.
		$( '#taxer-save-btn' ).prop( 'disabled', true ).text( 'Saving…' );

		$.ajax( {
			url:  TaxerAjax.ajax_url,
			type: 'POST',
			data: {
				action:          'taxer_update_company',
				nonce:           TaxerAjax.nonce,
				company_id:      companyId,
				company_name:    name,
				company_address: address,
				company_trn:     trn,
				company_amount: company_amount,
				company_data: m_company_data
			},
			success: function ( response ) {
				if ( response.success ) {
					showModalMsg( 'success', 'Company updated successfully.' );
					setTimeout( function () {
						location.reload();
					}, 1200 );
				} else {
					showModalMsg( 'error', response.data || 'Update failed.' );
					$( '#taxer-save-btn' ).prop( 'disabled', false ).text( 'Update Company' );
				}
			},
			error: function () {
				showAjaxError();
				$( '#taxer-save-btn' ).prop( 'disabled', false ).text( 'Update Company' );
			}
		} );
	}

	// ── Event bindings ─────────────────────────────────────────────────────────

	$( document ).ready( function () {
		$modal   = $( '#taxer-company-modal' );
		$overlay = $( '#taxer-modal-overlay' );
		$body    = $( '#taxer-modal-body' );

		// Open button in the dashboard.
		$( '#taxer-open-modal' ).on( 'click', openModal );

		// Delegated — these elements are injected by renderModalForm().
		$( document ).on( 'click', '#taxer-save-btn',   saveCompany );
		$( document ).on( 'click', '#taxer-cancel-btn', closeModal );
		$( document ).on( 'click', '#taxer-modal-close', closeModal );

		// Close on overlay click.
		$( document ).on( 'click', '#taxer-modal-overlay', closeModal );

		// Close on Escape key for accessibility.
		$( document ).on( 'keydown', function ( e ) {
			if ( 27 === e.which && $modal.is( ':visible' ) ) {
				closeModal();
			}
		} );
	} );

} )( jQuery );

// ── Tab switching ──────────────────────────────────────────────────────────

( function ( $ ) {
	'use strict';

	/**
	 * Initialise the tab system once the DOM is ready.
	 */
	$( document ).ready( function () {

		var $tabBtns   = $( '.taxer-tab-btn' );
		var $tabPanels = $( '.taxer-tab-panel' );

		if ( ! $tabBtns.length ) {
			return;
		}

		// Click: switch active tab and show matching panel.
		$tabBtns.on( 'click', function () {
			var $clicked  = $( this );
			var targetTab = $clicked.data( 'tab' );

			$tabBtns
				.removeClass( 'active' )
				.attr( 'aria-selected', 'false' );

			$clicked
				.addClass( 'active' )
				.attr( 'aria-selected', 'true' );

			$tabPanels.each( function () {
				var $panel = $( this );
				if ( $panel.attr( 'id' ) === 'tab-' + targetTab ) {
					$panel.removeAttr( 'hidden' );
				} else {
					$panel.attr( 'hidden', true );
				}
			} );
		} );

		// Keyboard: left/right arrows, Home, End navigate between tabs.
		$tabBtns.on( 'keydown', function ( e ) {
			var $all   = $( '.taxer-tab-btn' );
			var index  = $all.index( this );
			var total  = $all.length;
			var target = -1;

			if ( 37 === e.which ) {        // Arrow left.
				target = ( index - 1 + total ) % total;
			} else if ( 39 === e.which ) { // Arrow right.
				target = ( index + 1 ) % total;
			} else if ( 36 === e.which ) { // Home.
				target = 0;
			} else if ( 35 === e.which ) { // End.
				target = total - 1;
			}

			if ( target >= 0 ) {
				e.preventDefault();
				$all.eq( target ).trigger( 'click' ).trigger( 'focus' );
			}
		} );

	} );

} )( jQuery );
