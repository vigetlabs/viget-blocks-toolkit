/**
 * WordPress Dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getBlockSupport } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';

const MediaPosition = (BlockEdit) => {
	return (props) => {
		// Early return if block doesn't support attributes
		if (!props.attributes || typeof props.attributes !== 'object') {
			return <BlockEdit {...props} />;
		}

		const mediaPositionSupport = getBlockSupport(
			props.name,
			'mediaPosition',
			false,
		);

		// Support either `transformations` or `transforms` (block.json schema uses `supports.*` and
		// different projects may name this key differently).
		const transformations =
			mediaPositionSupport?.transformations || mediaPositionSupport?.transforms;

		if (!Array.isArray(transformations) || !transformations.length) {
			return <BlockEdit {...props} />;
		}

		return (
			<MediaPositionEdit
				BlockEdit={BlockEdit}
				props={props}
				transformations={transformations}
			/>
		);
	};
};

/**
 * Media position toolbar around a block that supports it.
 *
 * @param {Object}   root                 Props.
 * @param {Function} root.BlockEdit       Original block edit component.
 * @param {Object}   root.props           Block edit props.
 * @param {Array}    root.transformations Transformation rules from block supports.
 * @return {Element} Block edit with the toolbar.
 */
function MediaPositionEdit({ BlockEdit, props, transformations }) {
	const { attributes, setAttributes, clientId } = props;
	const className = attributes.className || '';
	const classes = className.split(' ');
	// The class is what's saved, so it's the source of truth after a reload.
	const currentPosition = classes.includes('has-media-on-the-right')
		? 'right'
		: 'left';

	const { replaceInnerBlocks } = useDispatch('core/block-editor');
	const { getBlocks } = useSelect((select) => ({
		getBlocks: select('core/block-editor').getBlocks,
	}));

	const findTransformationRule = (blockName, rules) => {
		return rules.find((t) => Object.keys(t)[0] === blockName)?.[blockName];
	};

	const transformBlock = (
		block,
		rules,
		parentInnerBlocks = null,
		newPosition,
	) => {
		const positionToUse = newPosition || currentPosition;

		let newBlock = { ...block };
		let newInnerBlocks = [...block.innerBlocks];

		// Helper function to apply attribute transformations
		const applyAttributes = (currentAttrs, transformRules) => {
			// Create a new attributes object that includes all current attributes
			const newAttrs = { ...currentAttrs };

			// Process each transformation rule
			Object.entries(transformRules).forEach(([attr, values]) => {
				// If the value is an object but doesn't have position keys, it's a nested attribute
				if (typeof values === 'object' && !values[positionToUse]) {
					newAttrs[attr] = applyAttributes(newAttrs[attr] || {}, values);
				} else {
					// Get the new value for the current position
					const newValue = values[positionToUse];
					if (newValue !== undefined) {
						newAttrs[attr] = newValue;
					}
				}
			});

			return newAttrs;
		};

		// First check for root level transformations
		const rootRule = findTransformationRule(block.name, rules);

		if (rootRule) {
			if (rootRule.attributes) {
				newBlock.attributes = applyAttributes(
					newBlock.attributes,
					rootRule.attributes,
				);
			}

			if (rootRule.reverse) {
				newInnerBlocks = newInnerBlocks.reverse();
			}

			if (rootRule.innerBlocks) {
				newInnerBlocks = newInnerBlocks.map((innerBlock) =>
					transformBlock(
						innerBlock,
						rules,
						rootRule.innerBlocks,
						positionToUse,
					),
				);
				return {
					...newBlock,
					innerBlocks: newInnerBlocks,
				};
			}
		}

		// Check for parent-specific transformations
		if (parentInnerBlocks) {
			const innerBlockRule = parentInnerBlocks.find(
				(t) => Object.keys(t)[0] === block.name,
			)?.[block.name];

			if (innerBlockRule) {
				if (innerBlockRule.attributes) {
					// Apply the transformations and ensure we're setting the new attributes
					const transformedAttributes = applyAttributes(
						newBlock.attributes,
						innerBlockRule.attributes,
					);
					newBlock = {
						...newBlock,
						attributes: transformedAttributes,
					};
				}

				if (innerBlockRule.innerBlocks) {
					newInnerBlocks = newInnerBlocks.map((innerBlock) =>
						transformBlock(
							innerBlock,
							rules,
							innerBlockRule.innerBlocks,
							positionToUse,
						),
					);
					return {
						...newBlock,
						innerBlocks: newInnerBlocks,
					};
				}
			}
		}

		// Continue traversing children with root transformations
		newInnerBlocks = newInnerBlocks.map((innerBlock) =>
			transformBlock(innerBlock, rules, null, positionToUse),
		);

		return {
			...newBlock,
			innerBlocks: newInnerBlocks,
		};
	};

	const setMediaPosition = (position) => {
		// Transforms reverse the order, so applying the current side again would flip it.
		if (position === currentPosition) {
			return;
		}

		const newClasses = classes.filter(
			(c) => !['has-media-on-the-left', 'has-media-on-the-right'].includes(c),
		);
		newClasses.push(`has-media-on-the-${position}`);

		// Get the blocks BEFORE updating attributes
		const innerBlocks = getBlocks(clientId);
		if (!innerBlocks?.length) {
			return;
		}

		// Transform blocks using the new position value
		const transformedBlocks = innerBlocks.map((block) =>
			transformBlock(block, transformations, null, position),
		);

		// Update attributes and blocks together
		setAttributes({ className: newClasses.join(' ').trim() });
		replaceInnerBlocks(clientId, transformedBlocks, false);
	};

	return (
		<>
			<BlockControls group="block">
				<ToolbarGroup>
					<ToolbarButton
						icon="align-pull-left"
						title={__('Show media on left')}
						onClick={() => setMediaPosition('left')}
						isActive={currentPosition === 'left'}
					/>
					<ToolbarButton
						icon="align-pull-right"
						title={__('Show media on right')}
						onClick={() => setMediaPosition('right')}
						isActive={currentPosition === 'right'}
					/>
				</ToolbarGroup>
			</BlockControls>
			<BlockEdit {...props} />
		</>
	);
}

addFilter('editor.BlockEdit', 'acf-bt/media-position', MediaPosition);
