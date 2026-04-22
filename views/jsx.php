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

// `??` treats an empty array as set; use explicit empty check so defaults apply when the
// template is [] (e.g. before blockPattern markup is resolved).
$resolved_template = ! empty( $block['template'] )
	? $block['template']
	: ( $block_template ?? [] );

$inner = [
	'template' => $resolved_template,
];

$has_container = ! isset( $block['supports']['innerContainer'] ) || true === $block['supports']['innerContainer'];
?>
<<?php echo esc_html( $tag ); ?> <?php block_attrs( $block ); ?>>
	<?php if ( $has_container ) : ?>
	<div class="acf-block-inner__container">
		<?php endif; ?>

		<?php inner_blocks( $inner ); ?>

		<?php if ( $has_container ) : ?>
	</div>
	<?php endif; ?>
</<?php echo esc_html( $tag ); ?>>
