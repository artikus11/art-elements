<?php

namespace Art\Elements;

class Main {

	/**
	 * Instance.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private static ?object $instance = null;


	public function __construct() {

		(new Controller())->setup_hook();

		$this->updater_init();
	}


	/**
	 * @return string
	 */
	public function plugin_path(): string {

		return untrailingslashit( ASID_PLUGIN_DIR );
	}


	/**
	 * @return string
	 */
	public function template_path(): string {

		return apply_filters( 'ae_template_path', ASID_PLUGIN_SLUG . '/' );
	}


	/**
	 * @param  string $template_name
	 *
	 * @return string
	 */
	public function get_template( string $template_name ): string {

		$template_path = locate_template( $this->template_path() . $template_name );

		if ( ! $template_path ) {
			$template_path = sprintf( '%s/%s/%s', $this->plugin_path(), ASID_PLUGIN_TEPMLATES, $template_name );
		}

		return apply_filters( 'ae_locate_template', $template_path );
	}


	protected function updater_init(): void {

		$updater = new Updater( AE_PLUGIN_AFILE );
		$updater->set_repository( 'art-elements' );
		$updater->set_username( 'artikus11' );
		$updater->set_authorize( 'Z2hwX3FmOHVsOXJVV2pSaVFUVjd3MXVybkpVbWNVT3VCbzBNV0ZCWA==' );
		$updater->init();
	}


	/**
	 * Instance.
	 *
	 * @return object Instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) :
			self::$instance = new self();
		endif;

		return self::$instance;

	}

}