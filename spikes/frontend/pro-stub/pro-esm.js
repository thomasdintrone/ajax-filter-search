( window.__afsOrder = window.__afsOrder || [] ).push( 'pro' );
const registry = ( globalThis.afsRegistry = globalThis.afsRegistry || { q: [] } );
const use = ( api ) => {
	api.on( 'results', ( { root } ) => root.setAttribute( 'data-afs-pro-after', '1' ) );
	document.documentElement.setAttribute( 'data-afs-pro-badge', '1' );
};
registry.use ? registry.use( use ) : registry.q.push( use );
