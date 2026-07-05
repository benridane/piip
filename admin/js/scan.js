/**
 * PII Scan
 *
 * Drives the piip_scan_batch AJAX endpoint in sequential batches over the
 * selected content types, renders a dry-run report, and re-runs the same
 * loop in apply mode to mask the listed items.
 *
 * @package PIIP
 * @since   1.5.0
 */

( function ( $ ) {
	'use strict';

	var $startButton = $( '#piip-scan-start' );
	var $applyButton = $( '#piip-scan-apply' );
	var $progressWrap = $( '#piip-scan-progress-wrap' );
	var $progressBar = $( '#piip-scan-progress-bar' );
	var $progressText = $( '#piip-scan-progress-text' );
	var $summary = $( '#piip-scan-summary' );
	var $resultsWrap = $( '#piip-scan-results-wrap' );
	var $resultsBody = $( '#piip-scan-results tbody' );

	var running = false;

	function selectedTargets() {
		return $( 'input[name="piip-scan-target"]:checked' )
			.map( function () {
				return $( this ).val();
			} )
			.get();
	}

	function sprintf1( template, a, b ) {
		return template.replace( '%1$s', a ).replace( '%2$s', b ).replace( '%s', a );
	}

	function setSummary( text ) {
		$summary.find( 'p' ).text( text );
		$summary.show();
	}

	function statusText( item ) {
		if ( item.applied ) {
			return piipScan.i18n.statusApplied;
		}
		if ( item.consent_bypassed ) {
			return piipScan.i18n.statusConsent;
		}
		if ( item.would_change ) {
			return piipScan.i18n.statusChange;
		}
		return piipScan.i18n.statusNoChange;
	}

	function renderItem( item, applyMode ) {
		// Build cells with .text() only; excerpts are stored user content.
		var $status = $( '<td>' ).text(
			applyMode && item.would_change && ! item.applied ? piipScan.i18n.statusFailed : statusText( item )
		);

		var $idCell = $( '<td>' );
		if ( item.edit_link ) {
			$( '<a>' )
				.attr( 'href', item.edit_link )
				.attr( 'target', '_blank' )
				.text( '#' + item.id )
				.appendTo( $idCell );
		} else {
			$idCell.text( '#' + item.id );
		}

		$( '<tr>' )
			.append( $( '<td>' ).text( item.target ) )
			.append( $idCell )
			.append( $( '<td>' ).text( item.label ) )
			.append( $( '<td>' ).text( item.detected_types.join( ', ' ) ) )
			.append( $status )
			.appendTo( $resultsBody );
	}

	function setProgress( done, total, label ) {
		var percent = total > 0 ? Math.min( 100, Math.round( ( done / total ) * 100 ) ) : 100;
		$progressBar.css( 'width', percent + '%' );
		$progressText.text( label + ' ' + done + ' / ' + total );
	}

	function finishRun() {
		running = false;
		$startButton.prop( 'disabled', false );
		$applyButton.prop( 'disabled', false );
	}

	/**
	 * Scan all selected targets sequentially, one batch at a time.
	 *
	 * @param {boolean} applyMode Whether to apply masking.
	 */
	function run( applyMode ) {
		var targets = selectedTargets();

		if ( ! targets.length ) {
			window.alert( piipScan.i18n.noTargets );
			return;
		}

		running = true;
		$startButton.prop( 'disabled', true );
		$applyButton.prop( 'disabled', true );
		$resultsBody.empty();
		$resultsWrap.hide();
		$summary.hide();
		$progressWrap.show();
		$progressBar.css( 'width', 0 );

		var progressLabel = applyMode ? piipScan.i18n.applying : piipScan.i18n.scanning;
		var targetIndex = 0;
		var offset = 0;
		var scannedTotal = 0;
		var scannedDone = 0;
		var foundCount = 0;
		var appliedCount = 0;
		var totalsKnown = {};

		function nextBatch() {
			if ( targetIndex >= targets.length ) {
				setProgress( scannedDone, scannedDone, progressLabel );

				if ( applyMode ) {
					setSummary( sprintf1( piipScan.i18n.applied, String( appliedCount ) ) );
				} else if ( 0 === foundCount ) {
					setSummary( piipScan.i18n.noResults );
				} else {
					setSummary( sprintf1( piipScan.i18n.summary, String( foundCount ), String( scannedDone ) ) );
					$applyButton.show();
				}

				finishRun();
				return;
			}

			$.post( piipScan.ajaxUrl, {
				action: 'piip_scan_batch',
				nonce: piipScan.nonce,
				target: targets[ targetIndex ],
				offset: offset,
				apply: applyMode ? 1 : 0
			} )
				.done( function ( response ) {
					if ( ! response || ! response.success ) {
						$progressText.text(
							( response && response.data && response.data.message ) || piipScan.i18n.error
						);
						finishRun();
						return;
					}

					var data = response.data;

					if ( ! totalsKnown[ data.target ] ) {
						totalsKnown[ data.target ] = true;
						scannedTotal += data.total;
					}

					scannedDone += data.processed;
					offset += data.processed;

					$.each( data.items, function ( i, item ) {
						foundCount++;
						if ( item.applied ) {
							appliedCount++;
						}
						renderItem( item, applyMode );
					} );

					if ( data.items.length ) {
						$resultsWrap.show();
					}

					setProgress( scannedDone, scannedTotal, progressLabel );

					if ( data.done || 0 === data.processed ) {
						targetIndex++;
						offset = 0;
					}

					nextBatch();
				} )
				.fail( function () {
					$progressText.text( piipScan.i18n.error );
					finishRun();
				} );
		}

		nextBatch();
	}

	$startButton.on( 'click', function () {
		if ( ! running ) {
			run( false );
		}
	} );

	$applyButton.on( 'click', function () {
		if ( running ) {
			return;
		}
		if ( window.confirm( piipScan.i18n.confirmApply ) ) {
			run( true );
		}
	} );
} )( jQuery );
