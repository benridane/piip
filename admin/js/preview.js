/**
 * Masking Preview
 *
 * Sends sample input to the piip_preview_mask AJAX endpoint as the user
 * types and renders the masked result and detected PII breakdown.
 *
 * @package PIIP
 * @since   1.4.1
 */

( function ( $ ) {
	'use strict';

	var DEBOUNCE_MS = 400;

	var $input = $( '#piip-preview-input' );
	var $fieldName = $( '#piip-preview-field-name' );
	var $fieldNameRow = $( '#piip-preview-field-name-row' );
	var $result = $( '#piip-preview-result' );
	var $status = $( '#piip-preview-status' );
	var $consentNotice = $( '#piip-preview-consent-notice' );
	var $disabledNotice = $( '#piip-preview-disabled-notice' );
	var $detectedWrap = $( '#piip-preview-detected-wrap' );
	var $detectedBody = $( '#piip-preview-detected tbody' );
	var $unsavedNotice = $( '#piip-preview-unsaved-notice' );

	var debounceTimer = null;
	var currentRequest = null;

	function getMode() {
		return $( 'input[name="piip-preview-mode"]:checked' ).val() || 'text';
	}

	function clearResult() {
		$result.text( '' );
		$status.text( '' );
		$consentNotice.hide();
		$disabledNotice.hide();
		$detectedWrap.hide();
		$detectedBody.empty();
	}

	function renderDetected( detected ) {
		$detectedBody.empty();

		if ( ! detected || ! detected.length ) {
			$detectedWrap.hide();
			return;
		}

		$.each( detected, function ( i, item ) {
			var type = item.provider ? item.type + ' (' + item.provider + ')' : item.type;
			var statusText;
			if ( ! item.maskable ) {
				statusText = piipPreview.i18n.notMaskable;
			} else if ( item.was_masked ) {
				statusText = piipPreview.i18n.masked;
			} else if ( ! item.enabled ) {
				statusText = piipPreview.i18n.disabled;
			} else {
				statusText = piipPreview.i18n.notMasked;
			}

			// Build cells with .text() only; detected values are user input.
			$( '<tr>' )
				.append( $( '<td>' ).text( type ) )
				.append( $( '<td>' ).text( item.value ) )
				.append( $( '<td>' ).text( Math.round( item.confidence * 100 ) + '%' ) )
				.append( $( '<td>' ).text( statusText ) )
				.appendTo( $detectedBody );
		} );

		$detectedWrap.show();
	}

	function requestPreview() {
		var text = $input.val();

		if ( currentRequest ) {
			currentRequest.abort();
			currentRequest = null;
		}

		if ( '' === text ) {
			clearResult();
			return;
		}

		$status.text( piipPreview.i18n.checking );

		currentRequest = $.post( piipPreview.ajaxUrl, {
			action: 'piip_preview_mask',
			nonce: piipPreview.nonce,
			mode: getMode(),
			field_name: $fieldName.val(),
			text: text
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					$status.text( ( response && response.data && response.data.message ) || piipPreview.i18n.error );
					return;
				}

				var data = response.data;
				$result.text( data.masked );
				$status.text( '' );
				$consentNotice.toggle( !! data.consent_bypassed );
				$disabledNotice.toggle( ! data.masking_enabled );
				renderDetected( data.detected );
			} )
			.fail( function ( jqXHR, textStatus ) {
				if ( 'abort' === textStatus ) {
					return;
				}
				$status.text( piipPreview.i18n.error );
			} )
			.always( function () {
				currentRequest = null;
			} );
	}

	function schedulePreview() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( requestPreview, DEBOUNCE_MS );
	}

	$input.on( 'input', schedulePreview );
	$fieldName.on( 'input', schedulePreview );

	$( 'input[name="piip-preview-mode"]' ).on( 'change', function () {
		$fieldNameRow.toggle( 'field' === getMode() );
		if ( '' !== $input.val() ) {
			schedulePreview();
		}
	} );

	// The preview always runs against saved settings; warn about unsaved edits.
	$( 'form[action="options.php"]' ).one( 'change', ':input', function () {
		$unsavedNotice.show();
	} );
} )( jQuery );
