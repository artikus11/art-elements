<?php

namespace Art\Elements;

class Main {

	protected const SLUG = 'ae_element';


	/**
	 * Instance.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private static ?object $instance = null;

	/**
	 * @var void
	 */
	protected $controller;


	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @return void
	 */
	public function __clone() {

		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning instances of the class is Forbidden', AE_PLUGIN_SLUG ), '1.0' );
	}


	/**
	 * Disable un-serializing of the class.
	 *
	 * @return void
	 */
	public function __wakeup() {

		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of the class is forbidden', AE_PLUGIN_SLUG ), '1.0' );
	}


	public function __construct() {

		( new CPT( $this ) )->setup_hook();
		( new Metabox( $this ) )->setup_hook();
		$this->controller = new Controller( $this );
		$this->controller->setup_hook();

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


	/**
	 * @return string
	 */
	public function get_slug(): string {

		return self::SLUG;
	}


	/**
	 * @return string
	 */
	public function get_field_slug(): string {

		return sprintf( '_%s_fields', $this->get_slug() );
	}



	public function get_controller() {

		return $this->controller;
	}

}