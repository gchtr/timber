<?php

namespace Timber;

/**
 * Class Integrations
 *
 * This class is used for integrating external plugins into Timber.
 *
 * @package Timber
 */
class Integrations {
	public $acf;
	public $coauthors_plus;
	public $wpml;

	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'init', array( $this, 'maybe_init_integrations' ) );

		if ( class_exists('WP_CLI_Command') ) {
			\WP_CLI::add_command( 'timber', 'Timber\Integrations\Timber_WP_CLI_Command' );
		}
	}

	/**
	 * For each of the third party integrations, check if the plugin is activated and initialize the integration.
	 */
	public function maybe_init_integrations() {
		if ( class_exists( 'ACF' ) ) {
			$this->acf = new Integrations\ACF();
		}

		if ( class_exists( 'CoAuthors_Plus' ) ) {
			$this->coauthors_plus = new Integrations\CoAuthorsPlus();
		}

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$this->wpml = new Integrations\WPML();
		}
	}
}
