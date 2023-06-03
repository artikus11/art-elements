<?php

namespace Art\Elements;

class CPT {

	protected Main $main;


	public function __construct(Main $main ) {
		$this->main = $main;
	}


	public function setup_hook(): void {

		add_action( 'init', [ $this, 'register_post_type' ], 1 );

		$slug = $this->main->get_slug();
		add_filter(
			"manage_{$slug}_posts_columns",
			function( array $columns ) : array {
				return $this->filter_post_type_columns( $columns );
			}
		);
		add_action(
			"manage_{$slug}_posts_custom_column",
			function( string $column_name, int $post_id ) {
				$this->render_post_type_column( $column_name, $post_id );
			},
			10,
			2
		);
	}
	private function filter_post_type_columns( array $columns ) : array {

		$add = array(
			//'type'            => esc_html__( 'Type', 'kadence-pro' ),
			'hook'            => esc_html__( 'Placement', 'kadence-pro' ),
			'display'         => esc_html__( 'Display On', 'kadence-pro' ),
			//'user_visibility' => esc_html__( 'Visible To', 'kadence-pro' ),
			'shortcode'       => esc_html__( 'Shortcode', 'kadence-pro' ),
		);

		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns = array_merge( $new_columns, $add );
			}
		}

		return $new_columns;
	}
	public function get_item_label_in_array( $data, $value ) {
		foreach ( $data as $key => $item ) {
			foreach ( $item['options'] as $sub_key => $sub_item ) {
				if ( $sub_item['value'] === $value ) {
					return $sub_item['label'];
				}
			}
		}
		return false;
	}
	private function render_post_type_column( string $column_name, int $post_id ) {
		if ( 'hook' !== $column_name && 'display' !== $column_name && 'shortcode' !== $column_name && 'type' !== $column_name && 'user_visibility' !== $column_name ) {
			return;
		}
		$post = get_post( $post_id );
		$meta = $this->main->get_controller()->get_post_meta_array($post);
		do_action( 'qm/info',$meta);
		if ( ( 'hook' === $column_name ) && ! empty( $meta['hook'] ) ) {
			echo '<code>' . esc_html( $meta['hook'] ) . '</code>';

			
		}
		/*if ( 'type' === $column_name ) {
			if ( isset( $meta['type'] ) && ! empty( $meta['type'] ) ) {
				echo esc_html( ucwords( $meta['type'] ) );
			} else {
				echo esc_html__( 'Default', 'kadence-pro' );
			}
		}*/
		/*if ( 'display' === $column_name ) {
			if ( isset( $meta ) && isset( $meta['show'] ) && is_array( $meta['show'] ) && ! empty( $meta['show'] ) ) {
				foreach ( $meta['show'] as $key => $rule ) {
					$rule_split = explode( '|', $rule['rule'], 2 );
					if ( in_array( $rule_split[0], array( 'singular', 'tax_archive' ) ) ) {
						if ( ! isset( $rule['select'] ) || isset( $rule['select'] ) && 'all' === $rule['select'] ) {
							echo esc_html( 'All ' . $rule['rule'] );
							echo '<br>';
						} elseif ( isset( $rule['select'] ) && 'author' === $rule['select'] ) {
							$label = $this->get_item_label_in_array( $this->get_display_options(), $rule['rule'] );
							echo esc_html( $label . ' Author: ' );
							if ( isset( $rule['subRule'] ) ) {
								$user = get_userdata( $rule['subRule'] );
								if ( isset( $user ) && is_object( $user ) && $user->display_name ) {
									echo esc_html( $user->display_name );
								}
							}
							echo '<br>';
						} elseif ( isset( $rule['select'] ) && 'tax' === $rule['select'] ) {
							$label = $this->get_item_label_in_array( $this->get_display_options(), $rule['rule'] );
							echo esc_html( $label . ' Terms: ' );
							if ( isset( $rule['subRule'] ) && isset( $rule['subSelection'] ) && is_array( $rule['subSelection'] ) ) {
								foreach ( $rule['subSelection'] as $sub_key => $selection ) {
									echo esc_html( $selection['value'] . ', ' );
								}
							}
							echo '<br>';
						} elseif ( isset( $rule['select'] ) && 'ids' === $rule['select'] ) {
							$label = $this->get_item_label_in_array( $this->get_display_options(), $rule['rule'] );
							echo esc_html( $label . ' Items: ' );
							if ( isset( $rule['ids'] ) && is_array( $rule['ids'] ) ) {
								foreach ( $rule['ids'] as $sub_key => $sub_id ) {
									echo esc_html( $sub_id . ', ' );
								}
							}
							echo '<br>';
						} elseif ( isset( $rule['select'] ) && 'individual' === $rule['select'] ) {
							$label = $this->get_item_label_in_array( $this->get_display_options(), $rule['rule'] );
							echo esc_html( $label . ' Terms: ' );
							if ( isset( $rule['subSelection'] ) && is_array( $rule['subSelection'] ) ) {
								$show_taxs   = array();
								foreach ( $rule['subSelection'] as $sub_key => $selection ) {
									if ( isset( $selection['value'] ) && ! empty( $selection['value'] ) ) {
										$show_taxs[] = $selection['value'];
									}
								}
								echo implode( ', ', $show_taxs );
							}
							echo '<br>';
						}
					} else {
						$label = $this->get_item_label_in_array( $this->get_display_options(), $rule['rule'] );
						echo esc_html( $label ) . '<br>';
					}
				}
			} else {
				echo esc_html__( '[UNSET]', 'kadence-pro' );
			}
		}*/
		/*if ( 'user_visibility' === $column_name ) {
			if ( isset( $meta ) && isset( $meta['user'] ) && is_array( $meta['user'] ) && ! empty( $meta['user'] ) ) {
				$show_roles = array();
				foreach ( $meta['user'] as $key => $user_rule ) {
					if ( isset( $user_rule['role'] ) && ! empty( $user_rule['role'] ) ) {
						$show_roles[] = $this->get_item_label_in_array( $this->get_user_options(), $user_rule['role'] );
					}
				}
				if ( count( $show_roles ) !== 0 ) {
					echo esc_html__( 'Visible to:', 'kadence-pro' );
					echo '<br>';
					echo implode( ', ', $show_roles );
				} else {
					echo esc_html__( '[UNSET]', 'kadence-pro' );
				}
			} else {
				echo esc_html__( '[UNSET]', 'kadence-pro' );
			}
		}*/
		if ( 'shortcode' === $column_name ) {
			echo '<code>[ae_element id="' . esc_attr( $post_id ) . '"]</code>';
		}
	}
	public function register_post_type(): void {

		$labels = [
			'name'                  => __( 'Elements', 'kadence_pro' ),
			'singular_name'         => __( 'Element', 'kadence_pro' ),
			'menu_name'             => _x( 'Elements', 'Admin Menu text', 'kadence_pro' ),
			'add_new'               => _x( 'Add New', 'Element', 'kadence_pro' ),
			'add_new_item'          => __( 'Add New Element', 'kadence_pro' ),
			'new_item'              => __( 'New Element', 'kadence_pro' ),
			'edit_item'             => __( 'Edit Element', 'kadence_pro' ),
			'view_item'             => __( 'View Element', 'kadence_pro' ),
			'all_items'             => __( 'All Elements', 'kadence_pro' ),
			'search_items'          => __( 'Search Elements', 'kadence_pro' ),
			'parent_item_colon'     => __( 'Parent Element:', 'kadence_pro' ),
			'not_found'             => __( 'No Elements found.', 'kadence_pro' ),
			'not_found_in_trash'    => __( 'No Elements found in Trash.', 'kadence_pro' ),
			'archives'              => __( 'Element archives', 'kadence_pro' ),
			'insert_into_item'      => __( 'Insert into Element', 'kadence_pro' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Element', 'kadence_pro' ),
			'filter_items_list'     => __( 'Filter Elements list', 'kadence_pro' ),
			'items_list_navigation' => __( 'Elements list navigation', 'kadence_pro' ),
			'items_list'            => __( 'Elements list', 'kadence_pro' ),
		];

		$args = [
			'labels'              => $labels,
			'description'         => __( 'Element areas to include in your site.', 'kadence_pro' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'can_export'          => true,
			'show_in_rest'        => true,
			'rewrite'             => false,
			'rest_base'           => 'ae_element',
			'menu_position'       => 64,
			'menu_icon'           => 'dashicons-layout',
			//'capability_type'     => [ 'kadence_element', 'kadence_elements' ],
			'map_meta_cap'        => true,
			'supports'            => [
				'title',
				'editor',
				'custom-fields',
				'revisions',
			],
		];

		register_post_type( $this->main->get_slug(), $args );
	}
}