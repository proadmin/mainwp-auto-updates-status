/*!
 * MainWP Auto Updates Status Extension
 * Copyright (c) 2026 ProAdmin (https://proadminwp.net/)
 * Licensed under the GPL-2.0-or-later.
 */
/* global jQuery, mainwpAus */
( function ( $ ) {
	'use strict';

	var STORAGE_KEY = 'mainwpAusWidgetTab';
	var TABS = [ 'aus-status', 'aus-last-run', 'aus-history', 'aus-failures' ];
	var timer = null;
	var busy = false;

	function getTab() {
		var tab = 'aus-status';
		try {
			var saved = window.localStorage.getItem( STORAGE_KEY );
			if ( TABS.indexOf( saved ) !== -1 ) {
				tab = saved;
			}
		} catch ( e ) {}
		return tab;
	}

	function setTab( tab ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, tab );
		} catch ( e ) {}
	}

	function showTab( tab ) {
		var $widget = $( '#mainwp-aus-widget-body' );
		$widget.find( '.ui.tab' ).removeClass( 'active' );
		$widget.find( '.ui.tab[data-tab="' + tab + '"]' ).addClass( 'active' );
	}

	function schedule() {
		clearTimeout( timer );
		var running = $( '#mainwp-aus-widget-body .mainwp-aus-body' ).data( 'running' ) === 1;
		var interval = parseInt( mainwpAus.refreshInterval, 10 );
		if ( isNaN( interval ) ) {
			interval = 60;
		}
		if ( running && interval > 0 ) {
			timer = setTimeout( refresh, interval * 1000 );
		}
	}

	function refresh() {
		if ( busy ) {
			return;
		}
		busy = true;
		var $icon = $( '.mainwp-aus-refresh i' ).addClass( 'loading' );
		$.post( mainwpAus.ajaxUrl, { action: 'mainwp_aus_widget_refresh', nonce: mainwpAus.nonce } )
			.done( function ( res ) {
				if ( res && res.success && res.data && typeof res.data.html === 'string' ) {
					$( '#mainwp-aus-widget-body' ).html( res.data.html );
					showTab( getTab() );
				}
			} )
			.always( function () {
				busy = false;
				$icon.removeClass( 'loading' );
				schedule();
			} );
	}

	$( function () {
		var $dropdown = $( '#mainwp-aus-dropdown' );
		if ( ! $dropdown.length ) {
			return;
		}

		showTab( getTab() );

		if ( typeof $dropdown.dropdown === 'function' ) {
			$dropdown.dropdown( {
				onChange: function ( val ) {
					if ( TABS.indexOf( val ) !== -1 ) {
						setTab( val );
						showTab( val );
					}
				}
			} );
			$dropdown.dropdown( 'set selected', getTab() );
		} else {
			$dropdown.on( 'click', '.item', function ( e ) {
				e.preventDefault();
				var val = $( this ).data( 'value' );
				setTab( val );
				showTab( val );
			} );
		}

		$( document ).on( 'click', '.mainwp-aus-refresh', function ( e ) {
			e.preventDefault();
			refresh();
		} );

		schedule();
	} );
}( jQuery ) );
