/* global vgtbtStyles */

const unregisterStyles = vgtbtStyles.unregister;

/**
 * WordPress Dependencies
 */
import domReady from '@wordpress/dom-ready';
import { unregisterBlockStyle } from '@wordpress/blocks';

/**
 * Unregister block styles.
 *
 * Avoid static imports of `@wordpress/edit-post` / `@wordpress/edit-site`: they become
 * script dependencies and prevent the bundle from loading in the iframed block canvas,
 * which does not enqueue those handles.
 */
const runUnregister = () => {
	unregisterStyles.forEach((style) => {
		const styles = Array.isArray(style[1]) ? style[1] : [style[1]];
		unregisterBlockStyle(style[0], styles);
	});
};

domReady(() => {
	if (window.parent !== window) {
		runUnregister();
		return;
	}

	void Promise.all([
		import('@wordpress/edit-post'),
		import('@wordpress/edit-site'),
	]).then(runUnregister);
});
