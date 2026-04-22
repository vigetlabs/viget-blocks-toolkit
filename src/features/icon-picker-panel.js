/**
 * Shared icon picker UI (sidebar + floating toolbar).
 */
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
	if (!currentIcon) {
		return { icon: 'format-image', label: __('Icon', 'viget-blocks-toolkit') };
	}
	const def = ICONS.find((i) => i.value === currentIcon);
	if (!def) {
		return { icon: 'format-image', label: __('Icon', 'viget-blocks-toolkit') };
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
		label: def?.label || def.value || __('Icon', 'viget-blocks-toolkit'),
	};
}
