/* eslint-disable @wordpress/no-unsafe-wp-apis, import/named -- match prior breakpoint controls */
import {
	Dropdown,
	PanelBody,
	SelectControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	__experimentalNumberControl as NumberControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { BlockControls, InspectorControls } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

import {
	IconPickerPanel,
	getToolbarIconDisplay,
	isIconToolbarBlock,
} from './icon-picker-panel';

const excludeBlocks = window.vgtbtBreakpointVisibility?.excludeBlocks || [];

const DEFAULT_BREAKPOINT_VISIBILITY = {
	useCustom: false,
	desktop: false,
	tablet: false,
	mobile: false,
	customBreakpoint: {
		width: '768',
		unit: 'px',
		action: 'hide',
		mobileFirst: false,
	},
};

/**
 * Shared responsive fields (sidebar + floating toolbar).
 *
 * @param {Object}   props
 * @param {Object}   props.visibility
 * @param {Function} props.updateVisibility
 * @param {Function} props.updateCustomBreakpoint
 */
function ResponsivePanelFields({
	visibility,
	updateVisibility,
	updateCustomBreakpoint,
}) {
	return (
		<>
			<ToggleControl
				label={__('Hide on Desktop', 'viget-blocks-toolkit')}
				checked={visibility.desktop}
				onChange={(value) => updateVisibility('desktop', value)}
				disabled={visibility.useCustom}
			/>
			<ToggleControl
				label={__('Hide on Tablet', 'viget-blocks-toolkit')}
				checked={visibility.tablet}
				onChange={(value) => updateVisibility('tablet', value)}
				disabled={visibility.useCustom}
			/>
			<ToggleControl
				label={__('Hide on Mobile', 'viget-blocks-toolkit')}
				checked={visibility.mobile}
				onChange={(value) => updateVisibility('mobile', value)}
				disabled={visibility.useCustom}
			/>

			<hr />

			<ToggleControl
				label={__('Use Custom Breakpoint', 'viget-blocks-toolkit')}
				checked={visibility.useCustom}
				onChange={(value) => updateVisibility('useCustom', value)}
			/>

			{visibility.useCustom && (
				<>
					<div className="vgtbt-toolbar-responsive__grid">
						<NumberControl
							label={__('Breakpoint Width', 'viget-blocks-toolkit')}
							value={visibility.customBreakpoint.width}
							onChange={(value) => updateCustomBreakpoint('width', value)}
							min={0}
							step={1}
						/>
						<SelectControl
							label={__('Unit', 'viget-blocks-toolkit')}
							value={visibility.customBreakpoint.unit}
							options={[
								{ label: 'px', value: 'px' },
								{ label: '%', value: '%' },
								{ label: 'rem', value: 'rem' },
								{ label: 'vw', value: 'vw' },
								{ label: 'vh', value: 'vh' },
							]}
							onChange={(value) => updateCustomBreakpoint('unit', value)}
						/>
					</div>
					<ToggleGroupControl
						label={__('Visibility Action', 'viget-blocks-toolkit')}
						value={visibility.customBreakpoint.action}
						onChange={(value) => updateCustomBreakpoint('action', value)}
						isBlock
					>
						<ToggleGroupControlOption
							value="show"
							label={__('Show', 'viget-blocks-toolkit')}
						/>
						<ToggleGroupControlOption
							value="hide"
							label={__('Hide', 'viget-blocks-toolkit')}
						/>
					</ToggleGroupControl>
					<ToggleControl
						label={__('Mobile First', 'viget-blocks-toolkit')}
						help={__(
							'When enabled, applies to screens smaller than breakpoint',
							'viget-blocks-toolkit',
						)}
						checked={visibility.customBreakpoint.mobileFirst}
						onChange={(value) => updateCustomBreakpoint('mobileFirst', value)}
					/>
				</>
			)}
		</>
	);
}

const toolbarDropdownBase = {
	popoverProps: {
		placement: 'bottom-start',
		focusOnMount: 'firstElement',
	},
};

const iconToolbarDropdownProps = {
	...toolbarDropdownBase,
	popoverProps: {
		...toolbarDropdownBase.popoverProps,
		className:
			'vgtbt-toolbar-dropdown-popover vgtbt-toolbar-dropdown-popover--icon',
	},
	contentClassName:
		'vgtbt-toolbar-dropdown__content vgtbt-toolbar-dropdown__content--icon',
};

const responsiveToolbarDropdownProps = {
	...toolbarDropdownBase,
	popoverProps: {
		...toolbarDropdownBase.popoverProps,
		className:
			'vgtbt-toolbar-dropdown-popover vgtbt-toolbar-dropdown-popover--responsive',
	},
	contentClassName:
		'vgtbt-toolbar-dropdown__content vgtbt-toolbar-dropdown__content--responsive',
};

/**
 * Add breakpoint visibility controls to block
 */
const withBreakpointVisibility = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		if (excludeBlocks.includes(props.name) || !props.attributes) {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;

		const showToolbarControls = useSelect(
			(select) => {
				const { getBlockParents, getBlock } = select('core/block-editor');
				const parents = getBlockParents(props.clientId) || [];
				for (const parentId of parents) {
					const parent = getBlock(parentId);
					if (parent?.attributes?.templateLock === 'contentOnly') {
						return true;
					}

					const parentType = parent?.name
						? select('core/blocks').getBlockType(parent.name)
						: null;
					if (parentType?.supports?.contentRole) {
						return true;
					}
				}
				return false;
			},
			[props.clientId],
		);

		const visibility = attributes.breakpointVisibility || {
			...DEFAULT_BREAKPOINT_VISIBILITY,
			customBreakpoint: {
				...DEFAULT_BREAKPOINT_VISIBILITY.customBreakpoint,
			},
		};

		const isVisibilitySet =
			visibility.useCustom ||
			visibility.desktop ||
			visibility.tablet ||
			visibility.mobile;

		const [isPanelOpen, setIsPanelOpen] = useState(isVisibilitySet);

		const updateVisibility = (key, value) => {
			setAttributes({
				breakpointVisibility: {
					...visibility,
					[key]: value,
				},
			});
		};

		const updateCustomBreakpoint = (key, value) => {
			setAttributes({
				breakpointVisibility: {
					...visibility,
					customBreakpoint: {
						...visibility.customBreakpoint,
						[key]: value,
					},
				},
			});
		};

		const visibilityClassName = isVisibilitySet
			? 'has-breakpoint-visibility'
			: '';

		const blockProps = {
			...props,
			className: `${props.className || ''} ${visibilityClassName}`.trim(),
			'data-visibility': isVisibilitySet ? 'true' : 'false',
		};

		const showIconToolbar =
			showToolbarControls && isIconToolbarBlock(props.name);
		const { icon: currentIcon } = attributes;
		const iconDisplay = getToolbarIconDisplay(currentIcon);

		return (
			<>
				<BlockEdit {...blockProps} />
				<BlockControls>
					<ToolbarGroup>
						{showIconToolbar && (
							<Dropdown
								{...iconToolbarDropdownProps}
								renderToggle={({ isOpen, onToggle }) => (
									<ToolbarButton
										label={iconDisplay.label}
										onClick={onToggle}
										aria-expanded={isOpen}
										className={
											currentIcon
												? 'vgtbt-toolbar-icon-trigger vgtbt-toolbar-icon-trigger--has-selection'
												: 'vgtbt-toolbar-icon-trigger'
										}
									>
										{iconDisplay.icon}
									</ToolbarButton>
								)}
								renderContent={() => (
									<div className="vgtbt-toolbar-dropdown__body vgtbt-toolbar-dropdown__body--icon">
										<IconPickerPanel
											attributes={attributes}
											setAttributes={setAttributes}
										/>
									</div>
								)}
							/>
						)}
						{showToolbarControls && (
							<Dropdown
								{...responsiveToolbarDropdownProps}
								renderToggle={({ isOpen, onToggle }) => (
									<ToolbarButton
										className={
											isVisibilitySet || isOpen
												? 'vgtbt-toolbar-responsive-trigger vgtbt-toolbar-responsive-trigger--active'
												: 'vgtbt-toolbar-responsive-trigger'
										}
										icon="smartphone"
										label={__('Responsive', 'viget-blocks-toolkit')}
										onClick={onToggle}
										aria-expanded={isOpen}
									/>
								)}
								renderContent={() => (
									<div className="vgtbt-toolbar-dropdown__body vgtbt-toolbar-dropdown__body--responsive">
										<ResponsivePanelFields
											visibility={visibility}
											updateVisibility={updateVisibility}
											updateCustomBreakpoint={updateCustomBreakpoint}
										/>
									</div>
								)}
							/>
						)}
					</ToolbarGroup>
				</BlockControls>
				<InspectorControls>
					<PanelBody
						title={__('Responsive', 'viget-blocks-toolkit')}
						opened={isPanelOpen}
						onToggle={() => setIsPanelOpen(!isPanelOpen)}
					>
						<ResponsivePanelFields
							visibility={visibility}
							updateVisibility={updateVisibility}
							updateCustomBreakpoint={updateCustomBreakpoint}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withBreakpointVisibility');

/**
 * Add visibility attributes to blocks
 */
addFilter(
	'blocks.registerBlockType',
	'viget-blocks-toolkit/breakpoint-visibility-attributes',
	(settings) => {
		if (excludeBlocks.includes(settings.name) || !settings.attributes) {
			return settings;
		}

		settings.attributes.breakpointVisibility = {
			type: 'object',
			role: 'content',
			default: {
				...DEFAULT_BREAKPOINT_VISIBILITY,
				customBreakpoint: {
					...DEFAULT_BREAKPOINT_VISIBILITY.customBreakpoint,
				},
			},
		};
		return settings;
	},
);

// Apply the breakpoint visibility to all blocks
addFilter(
	'editor.BlockEdit',
	'viget-blocks-toolkit/with-breakpoint-visibility',
	withBreakpointVisibility,
);

/**
 * Add visibility attributes to block wrapper
 */
addFilter(
	'blocks.getSaveContent.extraProps',
	'viget-blocks-toolkit/breakpoint-visibility-attributes',
	(extraProps, blockType, attributes) => {
		if (!attributes.breakpointVisibility) {
			return extraProps;
		}

		const { useCustom, desktop, tablet, mobile } =
			attributes.breakpointVisibility;

		if (!useCustom) {
			if (desktop) extraProps['data-visibility-desktop'] = 'hide';
			if (tablet) extraProps['data-visibility-tablet'] = 'hide';
			if (mobile) extraProps['data-visibility-mobile'] = 'hide';
		}
		return extraProps;
	},
);
