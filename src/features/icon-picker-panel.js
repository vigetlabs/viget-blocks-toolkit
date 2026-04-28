/**
 * Shared icon picker UI (sidebar + floating toolbar).
 */
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	PanelRow,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis, import/named -- experimental Grid
	__experimentalGrid as Grid,
} from '@wordpress/components';

const iconSettings = window.vgtbtIcons || {};
const icons = iconSettings.json || [];

export const ICONS = icons;

/** Default toolbar glyph (WordPress “send” / paper-plane style). */
export const DEFAULT_TOOLBAR_ICON = createElement(
	'span',
	{
		className:
			'vgtbt-toolbar-icon-trigger__glyph vgtbt-toolbar-icon-trigger__glyph--send',
		'aria-hidden': true,
	},
	createElement(
		'svg',
		{
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24',
			width: '20',
			height: '20',
			'aria-hidden': true,
			focusable: 'false',
		},
		createElement('path', {
			fill: 'currentColor',
			fillRule: 'evenodd',
			clipRule: 'evenodd',
			d: 'M6.332 5.748c-1.03-.426-2.06.607-1.632 1.636l1.702 3.93 7.481.575c.123.01.123.19 0 .2l-7.483.575-1.7 3.909c-.429 1.029.602 2.062 1.632 1.636l12.265-5.076c1.03-.426 1.03-1.884 0-2.31L6.332 5.748Z',
		}),
	),
);

/**
 * @param {Object}   props
 * @param {Object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {boolean}  [props.showToggle=true] Show “icon on left” toggle (toolbar can omit if moved).
 */
export function IconPickerPanel({
	attributes,
	setAttributes,
	showToggle = true,
}) {
	const { icon: currentIcon, iconPositionLeft } = attributes;

	return (
		<>
			<PanelRow>
				<Grid className="button-icon-picker__grid" columns="5" gap="0">
					{ICONS.map((icon, index) => (
						<Button
							key={index}
							label={icon?.label}
							isPressed={currentIcon === icon.value}
							className={
								'button-icon-picker__button button-icon-picker__icon-' +
								icon.value
							}
							onClick={() =>
								setAttributes({
									icon: currentIcon === icon.value ? null : icon.value,
									iconPositionLeft: iconPositionLeft || icon?.defaultLeft,
								})
							}
						>
							<span
								dangerouslySetInnerHTML={{
									__html: icon.icon ?? icon.value,
								}}
							/>
						</Button>
					))}
				</Grid>
			</PanelRow>
			{showToggle && (
				<PanelRow>
					<ToggleControl
						label={__('Show icon on left', 'viget-blocks-toolkit')}
						checked={iconPositionLeft}
						onChange={() => {
							setAttributes({
								iconPositionLeft: !iconPositionLeft,
							});
						}}
					/>
				</PanelRow>
			)}
		</>
	);
}

export function isIconToolbarBlock(blockName) {
	return Boolean(iconSettings.supportedBlocks?.includes(blockName));
}

export function getToolbarIconDisplay(currentIcon) {
	const label = __('Icon', 'viget-blocks-toolkit');

	if (!currentIcon) {
		return { icon: DEFAULT_TOOLBAR_ICON, label };
	}

	const def = ICONS.find((i) => i.value === currentIcon);
	if (!def) {
		return { icon: DEFAULT_TOOLBAR_ICON, label };
	}

	return {
		icon: (
			<span
				className="vgtbt-toolbar-icon-trigger__glyph"
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={{
					__html: def.icon ?? def.value,
				}}
			/>
		),
		label: def?.label || def.value || label,
	};
}
