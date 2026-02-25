<?php

namespace Art\Elements;

class Shortcodes {
	protected Main $main;


	public function __construct(Main $main ) {
		$this->main = $main;
	}
	public function setup_hook(): void {
		add_shortcode( $this->main->get_slug(), [ $this, 'view' ] );
	}

	public function view($atts){
		$atts = shortcode_atts(
			[
				'id'    => '',
			],
			$atts
		);

		$id    = $atts['id'] ?? '';

		$post = get_post($id);
		
		do_action( 'qm/info',$post);
		
	}

}