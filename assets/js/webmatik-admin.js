/**
 * Webmatik admin page JS
 * Handles connect/disconnect and audit run/poll.
 */
(function () {
	'use strict';

	var config = window.webmatikAdmin || {};

	// ── Connect ──
	var connectBtn = document.getElementById( 'webmatik-connect' );
	if ( connectBtn ) {
		connectBtn.addEventListener( 'click', function () {
			var w = 480, h = 600;
			var left = ( screen.width - w ) / 2;
			var top  = ( screen.height - h ) / 2;
			window.open(
				config.connectUrl,
				'webmatik-connect',
				'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top
			);
		});

		window.addEventListener( 'message', function ( e ) {
			// Only accept messages from webmatik.ai
			if ( e.origin !== 'https://webmatik.ai' ) return;
			if ( ! e.data || e.data.type !== 'webmatik-connect' || ! e.data.key ) return;

			wp.ajax.post( 'webmatik_save_key', {
				nonce:   config.saveNonce,
				api_key: e.data.key
			}).done( function () {
				location.reload();
			});
		});
	}

	// ── Disconnect ──
	var disconnectBtn = document.getElementById( 'webmatik-disconnect' );
	if ( disconnectBtn ) {
		disconnectBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Disconnect from Webmatik?' ) ) return;
			wp.ajax.post( 'webmatik_save_key', {
				nonce:   config.saveNonce,
				api_key: ''
			}).done( function () {
				location.reload();
			});
		});
	}

	// ── Run Audit ──
	var btn    = document.getElementById( 'webmatik-run-audit' );
	var status = document.getElementById( 'webmatik-status' );
	var result = document.getElementById( 'webmatik-result' );

	if ( btn && ! btn.disabled ) {
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			status.textContent = 'Starting audit...';
			if ( result ) result.style.display = 'none';

			wp.ajax.post( 'webmatik_run_audit', {
				nonce: config.runNonce
			}).done( function ( data ) {
				if ( data.auditId ) {
					status.textContent = 'Analyzing\u2026 this takes 2\u20133 minutes.';
					pollAudit( data.auditId );
				} else {
					status.textContent = 'Error: ' + ( data.error || 'Unknown error' );
					btn.disabled = false;
				}
			}).fail( function ( err ) {
				status.textContent = 'Error: ' + ( err || 'Request failed' );
				btn.disabled = false;
			});
		});
	}

	function pollAudit( auditId ) {
		wp.ajax.post( 'webmatik_poll_audit', {
			nonce:    config.pollNonce,
			audit_id: auditId
		}).done( function ( data ) {
			if ( data.status === 'processing' ) {
				setTimeout( function () { pollAudit( auditId ); }, 5000 );
			} else if ( data.status === 'completed' ) {
				status.textContent = '';
				btn.disabled = false;
				result.style.display = 'block';
				result.innerHTML =
					'<div style="display:flex;align-items:center;gap:15px;">' +
						'<div style="font-size:36px;font-weight:700;color:#2271b1;">' + sanitizeText( data.score ) + '</div>' +
						'<div><div style="font-size:14px;font-weight:600;">Growth Score</div>' +
						'<div style="color:#50575e;">Grade: ' + sanitizeText( data.grade ) + '</div></div>' +
					'</div>' +
					( data.reportUrl ? '<p style="margin:10px 0 0;"><a href="' + encodeURI( data.reportUrl ) + '" target="_blank" class="button">View Full Report &rarr;</a></p>' : '' ) +
					'<p style="margin:8px 0 0;color:#787c82;font-size:12px;">Just now</p>';
			} else {
				status.textContent = 'Audit failed. Please try again.';
				btn.disabled = false;
			}
		}).fail( function () {
			setTimeout( function () { pollAudit( auditId ); }, 10000 );
		});
	}

	function sanitizeText( str ) {
		var el = document.createElement( 'span' );
		el.textContent = String( str );
		return el.innerHTML;
	}
})();
