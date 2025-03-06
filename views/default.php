<?php
/**
 * Default Block Template
 *
 * @global array $block
 *
 * @package VigetBlocksToolkit
 */

?>
<section <?php block_attrs( $block ); ?>>
	<p style="text-align:center">
		<?php esc_html_e( 'Default Block template', 'viget-blocks-toolkit' ); ?>:
		<?php echo esc_html( $block['slug'] ); ?>
	</p>
</section>
