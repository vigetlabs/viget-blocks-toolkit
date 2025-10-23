/**
 * Navigation Slug Reference Feature
 *
 * Adds refSlug attribute to core/navigation blocks for slug-based menu references.
 * Works with the existing WordPress menu selector instead of creating a separate dropdown.
 *
 * @package Viget\BlocksToolkit
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Add the refSlug attribute to core/navigation blocks.
 *
 * @param {Object} settings Block settings.
 * @return {Object} Modified block settings.
 */
function addRefSlugAttribute(settings) {
	if ('core/navigation' !== settings.name) {
		return settings;
	}

	const refSlugAttribute = {
		refSlug: {
			type: 'string',
		},
	};

	return {
		...settings,
		attributes: {
			...settings.attributes,
			...refSlugAttribute,
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'viget-blocks-toolkit/navigation-ref-slug-attribute',
	addRefSlugAttribute,
);

/**
 * Sync refSlug with ref when menu is selected through existing WordPress interface.
 *
 * @param {Object} BlockEdit Block edit component.
 * @return {Object} Enhanced block edit component.
 */
function syncRefSlugWithRef(BlockEdit) {
	return (props) => {
		if (props.name !== 'core/navigation') {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;
		const { refSlug, ref } = attributes;

		// Get available navigation menus
		const navigationMenus = useSelect((select) => {
			return select('core').getEntityRecords('postType', 'wp_navigation', {
				status: 'publish',
				per_page: -1,
			});
		}, []);

		// Sync refSlug when ref changes (user selects menu through existing interface)
		useEffect(() => {
			if (ref && navigationMenus) {
				const selectedMenu = navigationMenus.find((menu) => menu.id === ref);
				if (selectedMenu && selectedMenu.slug !== refSlug) {
					// Update refSlug to match the selected menu's slug
					setAttributes({
						refSlug: selectedMenu.slug,
					});
				}
			}
		}, [ref, navigationMenus, refSlug, setAttributes]);

		return <BlockEdit {...props} />;
	};
}

addFilter(
	'editor.BlockEdit',
	'viget-blocks-toolkit/navigation-ref-slug-sync',
	syncRefSlugWithRef,
);
