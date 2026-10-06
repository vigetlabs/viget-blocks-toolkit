/* global vgtbtVariations */

const unregisterVariations = vgtbtVariations.unregister;

/**
 * WordPress Dependencies
 */
import domReady from '@wordpress/dom-ready';
import { unregisterBlockVariation } from '@wordpress/blocks';

/**
 * Unregister block variations.
 *
 * See block-styles.js: dynamic editor-package imports only in the parent document so this
 * bundle can load inside the iframed block canvas.
 */
const runUnregister = () => {
	unregisterVariations.forEach( ( variation ) => {
		const [ coreBlock, variationName ] = variation;
		unregisterBlockVariation( coreBlock, variationName );
	} );
};

domReady( () => {
	if ( window.parent !== window ) {
		runUnregister();
		return;
	}

	void Promise.all( [
		import( '@wordpress/edit-post' ),
		// eslint-disable-next-line import/no-unresolved -- A WordPress global, externalized at build.
		import( '@wordpress/edit-site' ),
	] ).then( runUnregister );
} );
