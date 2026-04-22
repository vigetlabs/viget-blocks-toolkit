/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { IconPickerPanel, isIconToolbarBlock } from './icon-picker-panel';

/**
 * Add the attributes needed for button icons.
 *
 * @since 0.1.0
 * @param {Object} settings
 */
function addAttributes(settings) {
	const iconSettings = window.vgtbtIcons || {};
	if (!iconSettings.supportedBlocks?.includes(settings.name)) {
		return settings;
	}

	const iconAttributes = {
		icon: {
			type: 'string',
			role: 'content',
		},
		iconPositionLeft: {
			type: 'boolean',
			default: false,
			role: 'content',
		},
	};

	return {
		...settings,
		attributes: {
			...settings.attributes,
			...iconAttributes,
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'viget-blocks-toolkit/add-attributes',
	addAttributes,
);

/**
 * Filter the BlockEdit object and add icon inspector controls to button blocks.
 * Floating toolbar controls live in breakpoint-visibility.js (combined toolbar).
 *
 * @since 0.1.0
 * @param {Object} BlockEdit
 */
function addInspectorControls(BlockEdit) {
	return (props) => {
		if (!isIconToolbarBlock(props.name)) {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody
						title={__('Icon', 'viget-blocks-toolkit')}
						className="button-icon-picker"
						initialOpen={true}
					>
						<IconPickerPanel
							attributes={attributes}
							setAttributes={setAttributes}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}

addFilter(
	'editor.BlockEdit',
	'viget-blocks-toolkit/add-inspector-controls',
	addInspectorControls,
);

/**
 * Add icon and position classes in the Editor.
 *
 * @since 0.1.0
 * @param {Object} BlockListBlock
 */
function addClasses(BlockListBlock) {
	return (props) => {
		const { name, attributes } = props;

		if (!isIconToolbarBlock(name) || !attributes?.icon) {
			return <BlockListBlock {...props} />;
		}

		const classes = classnames(props?.className, {
			[`has-icon__${attributes?.icon}`]: attributes?.icon,
			'has-icon-position__left': attributes?.iconPositionLeft,
		});

		return <BlockListBlock {...props} className={classes} />;
	};
}

addFilter(
	'editor.BlockListBlock',
	'viget-blocks-toolkit/add-classes',
	addClasses,
);
