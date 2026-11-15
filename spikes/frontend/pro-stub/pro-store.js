import { store } from '@wordpress/interactivity';
( window.__afsOrder = window.__afsOrder || [] ).push( 'pro' );
store( 'afsSpike', {
	actions: {
		// A new key: should survive in either load order.
		proBadge() {
			document.documentElement.setAttribute( 'data-afs-pro-badge', '1' );
		},
	},
	callbacks: {
		// An override of a key the free plugin also defines: order-dependent with deep merge.
		afterResults( root ) {
			root.setAttribute( 'data-afs-pro-after', '1' );
			store( 'afsSpike' ).actions.proBadge();
		},
	},
} );
