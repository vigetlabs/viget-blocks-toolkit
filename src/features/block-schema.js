/**
 * WordPress Dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';

/**
 * Add FAQ Schema attribute to core/accordion block.
 *
 * @param {Object} settings Block settings.
 * @param {string} name     Block name.
 * @return {Object} Modified block settings.
 */
function addFaqSchemaAttribute( settings, name ) {
	if ( name !== 'core/accordion' ) {
		return settings;
	}

	// Add the FAQ Schema attribute.
	const faqSchemaAttribute = {
		useFaqSchema: {
			type: 'boolean',
			default: false,
		},
	};

	return {
		...settings,
		attributes: {
			...settings.attributes,
			...faqSchemaAttribute,
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'viget-blocks-toolkit/add-faq-schema-attribute',
	addFaqSchemaAttribute
);

/**
 * Add FAQ Schema toggle control to accordion block settings.
 */
const withFaqSchemaControl = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { attributes, setAttributes, name } = props;

		// Only add controls to core/accordion block.
		if ( name !== 'core/accordion' ) {
			return <BlockEdit {...props} />;
		}

		const { useFaqSchema } = attributes;

		// Create help text with link to Schema.org
		const helpText = (
			<>
				{ __(
					'Add FAQPage Schema markup from ',
					'viget-blocks-toolkit'
				) }
				<a
					href="https://schema.org/FAQPage"
					target="_blank"
					rel="noopener noreferrer"
				>
					Schema.org
				</a>
				{ __( ' to this accordion.', 'viget-blocks-toolkit' ) }
			</>
		);

		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody title={ __( 'Schema', 'viget-blocks-toolkit' ) }>
						<ToggleControl
							label={ __( 'FAQPage Schema', 'viget-blocks-toolkit' ) }
							checked={ useFaqSchema || false }
							onChange={ ( value ) =>
								setAttributes( { useFaqSchema: value } )
							}
							help={ helpText }
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withFaqSchemaControl' );

// Use priority 9 to ensure this runs before breakpoint-visibility (priority 10)
addFilter(
	'editor.BlockEdit',
	'viget-blocks-toolkit/with-faq-schema-control',
	withFaqSchemaControl,
	9
);

