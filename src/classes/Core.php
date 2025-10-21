<?php
/**
 * Core API
 *
 * @package Viget\BlocksToolkit
 */

namespace Viget\BlocksToolkit;

/**
 * Core API
 */
class Core {

	/**
	 * Instance of this class.
	 *
	 * @var ?Core
	 */
	private static ?Core $instance = null;

	/**
	 * Block Icons
	 *
	 * @var ?BlockIcons
	 */
	public ?BlockIcons $block_icons = null;

	/**
	 * Breakpoint Visibility
	 *
	 * @var ?BreakpointVisibility
	 */
	public ?BreakpointVisibility $bp_visibility = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->block_icons   = new BlockIcons();
		$this->bp_visibility = new BreakpointVisibility();

		// Initialize GitHub updater
		new \GitHub_Plugin_Updater(
			VGTBT_PLUGIN_FILE,
			'vigetlabs',
			'viget-blocks-toolkit'
		);
	}

	/**
	 * Get the instance of this class.
	 *
	 * @return Core
	 */
	public static function instance(): Core {
		if ( null === self::$instance ) {
			require_once VGTBT_PLUGIN_PATH . 'src/classes/BlockTemplate/Template.php';
			require_once VGTBT_PLUGIN_PATH . 'src/classes/BlockTemplate/Block.php';

			self::$instance = new self();

			BlockRegistration::init();
			Settings::init();
		}

		return self::$instance;
	}
}
