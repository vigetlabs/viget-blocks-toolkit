<?php
/**
 * Default JSX Template
 *
 * @global array $block
 *
 * @package VigetBlocksToolkit
 */

if ( ! isset( $block_template ) && empty( $block['template'] ) ) {
	$block_template = [
		[
			'core/paragraph',
			[
				'placeholder' => __( 'Type / to choose a block', 'viget-blocks-toolkit' ),
			],
		],
	];
}

$tag = $block['tagName'];

// Set the inner blocks template.
$inner = [
	'template' => ! empty( $block['template'] )
		? $block['template']
		: ( $block_template ?? [] )
];

$has_container = ! isset( $block['supports']['innerContainer'] ) || true === $block['supports']['innerContainer'];

// Get the block attributes.
ob_start();
block_attrs( $block );
$block_attrs = ob_get_clean();

// Open the block.
printf(
	'<%s %s>',
	esc_html( $tag ),
	$block_attrs
);

// Open the container if it is supported.
if ( $has_container ) {
	echo '<div class="acf-block-inner__container">';
}

// Render the inner blocks.
inner_blocks( $inner );

// Close the container if it was opened.
if ( $has_container ) {
	echo '</div>';
}

// Close the block.
printf(
	'</%s>',
	esc_html( $tag )
);
