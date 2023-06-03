<?php

namespace Art\Elements;

use Kadence_Blocks_Frontend;

class Controller {

	/**
	 * @var string[]
	 */
	public static ?array $current_condition = null;

	/**
	 * @var array|string[]
	 */
	public static ?array $current_user = null;

	protected static string $slug;

	protected Main $main;


	public function __construct( Main $main ) {

		$this->main = $main;

		self::$slug = $this->main->get_slug();
	}


	public function setup_hook(): void {

		add_action( 'wp', [ $this, 'init_frontend_hooks' ], 99 );
		add_action( 'init', [ $this, 'setup_content_filter' ], 9 );

	}


	public function init_frontend_hooks(): void {

		if ( is_admin() || is_singular( self::$slug ) ) {
			return;
		}

		$args = [
			'post_type'              => self::$slug,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'post_status'            => 'publish',
			'numberposts'            => 333,
			'order'                  => 'ASC',
			'orderby'                => 'menu_order',
			'suppress_filters'       => false,
		];

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {
			$meta = $this->get_post_meta_array( $post );

			//		if ( apply_filters( 'ae_element_display', $this->check_element_conditionals( $post, $meta ), $post, $meta ) ) {
			if ( ( 'custom' !== $meta['hook'] && ! empty( $meta['hook'] ) && strpos( $meta['hook'], 'fixed' ) !== 0 )
			     && 'replace_header' !== $meta['hook']
			     && 'replace_404' !== $meta['hook']
			     && 'replace_footer' !== $meta['hook']
			     && 'kadence_before_wrapper' !== $meta['hook']
			     && 'replace_login_modal' !== $meta['hook']
			     && 'woocommerce_before_single_product_image' !== $meta['hook']
			     && 'woocommerce_after_single_product_image' !== $meta['hook']
			     && 'kadence_inside_the_content_before_h1' !== $meta['hook']
			     && 'kadence_inside_the_content_after_h1' !== $meta['hook']
			     && 'kadence_inside_the_content_after_p1' !== $meta['hook']
			     && 'kadence_inside_the_content_after_p2' !== $meta['hook']
			     && 'kadence_inside_the_content_after_p3' !== $meta['hook']
			     && 'kadence_inside_the_content_after_p4' !== $meta['hook']
			     && 'kadence_replace_sidebar' !== $meta['hook']
			) {
				add_action(
					esc_attr( $meta['hook'] ),
					function () use ( $post, $meta ) {

						$this->output_element( $post, $meta );
					},
					absint( $meta['priority'] )
				);
				$this->enqueue_element_styles( $post, $meta );
			} elseif ( isset( $meta['hook'], $meta['custom'] ) && 'custom' === $meta['hook'] && ! empty( $meta['custom'] ) ) {
				do_action( 'qm/info', $meta );
				add_action(
					esc_attr( $meta['custom'] ),
					function () use ( $post, $meta ) {

						$this->output_element( $post, $meta );
					},
					absint( $meta['priority'] )
				);
				$this->enqueue_element_styles( $post, $meta );
			} elseif ( isset( $meta['hook'] ) && 'woocommerce_before_single_product_image' === $meta['hook'] ) {
				add_action( 'woocommerce_before_single_product_summary', [ $this, 'product_image_before_wrap' ], 11 );
				add_action( 'woocommerce_before_single_product_summary', [ $this, 'product_image_after_wrap' ], 80 );
				add_action(
					'woocommerce_before_single_product_summary',
					function () use ( $post, $meta ) {

						echo '<!-- [special-element-' . esc_attr( $post->ID ) . '] -->';
						echo '<div class="product-before-images-element">';
						$this->output_element( $post, $meta );
						echo '</div>';
						echo '<!-- [/special-element-' . esc_attr( $post->ID ) . '] -->';
					},
					12
				);
			} elseif ( isset( $meta['hook'] ) && 'woocommerce_after_single_product_image' === $meta['hook'] ) {
				add_action( 'woocommerce_before_single_product_summary', [ $this, 'product_image_before_wrap' ], 11 );
				add_action( 'woocommerce_before_single_product_summary', [ $this, 'product_image_after_wrap' ], 80 );
				add_action(
					'woocommerce_before_single_product_summary',
					function () use ( $post, $meta ) {

						echo '<!-- [special-element-' . esc_attr( $post->ID ) . '] -->';
						echo '<div class="product-after-images-element">';
						$this->output_element( $post, $meta );
						echo '</div>';
						echo '<!-- [/special-element-' . esc_attr( $post->ID ) . '] -->';
					},
					50
				);
			}
		}
		//	}
	}


	public function check_element_conditionals( $post, $meta ): bool {

		$current_condition = self::get_current_page_conditions();

		$rules_with_sub_rules = [ 'singular', 'tax_archive' ];
		$show                 = false;
		$all_must_be_true     = ( isset( $meta, $meta['all_show'] ) ? $meta['all_show'] : false );
		if ( isset( $meta, $meta['show'] ) && is_array( $meta['show'] ) && ! empty( $meta['show'] ) ) {

			foreach ( $meta['show'] as $key => $rule ) {
				$rule_show = false;

				if ( isset( $rule['rule'] ) && in_array( $rule['rule'], $current_condition, true ) ) {
					$rule_split = explode( '|', $rule['rule'], 2 );

					if ( in_array( $rule_split[0], $rules_with_sub_rules, true ) ) {
						if ( ! isset( $rule['select'] ) || ( ! empty( $rule['select'] ) && 'all' === $rule['select'] ) ) {
							$show      = true;
							$rule_show = true;
						} elseif ( ! empty( $rule['select'] ) && 'author' === $rule['select'] ) {
							if ( isset( $rule['subRule'] ) && $rule['subRule'] === get_post_field( 'post_author', get_queried_object_id() ) ) {
								$show      = true;
								$rule_show = true;
							}
						} elseif ( ! empty( $rule['select'] ) && 'tax' === $rule['select'] ) {
							if ( isset( $rule['subRule'], $rule['subSelection'] ) && is_array( $rule['subSelection'] ) ) {
								foreach ( $rule['subSelection'] as $sub_key => $selection ) {
									if ( has_term( $selection['value'], $rule['subRule'] ) ) {
										$show      = true;
										$rule_show = true;
									} elseif ( $this->post_is_in_descendant_term( $selection['value'], $rule['subRule'] ) ) {
										$show      = true;
										$rule_show = true;
									} elseif ( isset( $rule['mustMatch'] ) && $rule['mustMatch'] ) {
										return false;
									}
								}
							}
						} elseif ( ( ! empty( $rule['select'] ) && 'ids' === $rule['select'] ) && ( ! empty( $rule['ids'] ) && is_array( $rule['ids'] ) ) ) {

							$current_id = get_the_ID();
							foreach ( $rule['ids'] as $sub_key => $sub_id ) {
								if ( $current_id === $sub_id ) {
									$show      = true;
									$rule_show = true;
								}
							}

						} elseif ( ! empty( $rule['select'] ) && 'individual' === $rule['select'] ) {
							if ( isset( $rule['subSelection'] ) && is_array( $rule['subSelection'] ) ) {
								$queried_obj = get_queried_object();
								$show_taxs   = [];
								foreach ( $rule['subSelection'] as $sub_key => $selection ) {
									if ( isset( $selection['value'] ) && ! empty( $selection['value'] ) ) {
										$show_taxs[] = $selection['value'];
									}
								}
								if ( in_array( $queried_obj->term_id, $show_taxs, true ) ) {
									$show      = true;
									$rule_show = true;
								}
							}
						}
					} else {
						$show      = true;
						$rule_show = true;
					}
				}
				if ( ! $rule_show && $all_must_be_true ) {
					return false;
				}
			}
		}
		// Exclude Rules.
		if ( $show ) {
			if ( isset( $meta, $meta['hide'] ) && is_array( $meta['hide'] ) && ! empty( $meta['hide'] ) ) {
				foreach ( $meta['hide'] as $key => $rule ) {
					if ( isset( $rule['rule'] ) && in_array( $rule['rule'], $current_condition, true ) ) {
						$rule_split = explode( '|', $rule['rule'], 2 );
						if ( in_array( $rule_split[0], $rules_with_sub_rules, true ) ) {
							if ( ! isset( $rule['select'] ) || ( ! empty( $rule['select'] ) && 'all' === $rule['select'] ) ) {
								$show = false;
							} elseif ( ! empty( $rule['select'] ) && 'author' === $rule['select'] ) {
								if ( isset( $rule['subRule'] ) && $rule['subRule'] === get_post_field( 'post_author', get_queried_object_id() ) ) {
									$show = false;
								}
							} elseif ( ! empty( $rule['select'] ) && 'tax' === $rule['select'] ) {
								if ( isset( $rule['subRule'], $rule['subSelection'] ) && is_array( $rule['subSelection'] ) ) {
									foreach ( $rule['subSelection'] as $sub_key => $selection ) {
										if ( has_term( $selection['value'], $rule['subRule'] ) ) {
											$show = false;
										} elseif ( isset( $rule['mustMatch'] ) && $rule['mustMatch'] ) {
											$show = true;
											continue;
										}
									}
								}
							} elseif ( ! empty( $rule['select'] ) && 'ids' === $rule['select'] ) {
								if ( isset( $rule['ids'] ) && is_array( $rule['ids'] ) ) {
									$current_id = get_the_ID();
									foreach ( $rule['ids'] as $sub_key => $sub_id ) {
										if ( $current_id === $sub_id ) {
											$show = false;
										}
									}
								}
							} elseif ( ! empty( $rule['select'] ) && 'individual' === $rule['select'] ) {
								if ( isset( $rule['subSelection'] ) && is_array( $rule['subSelection'] ) ) {
									$queried_obj = get_queried_object();
									$show_taxs   = [];
									foreach ( $rule['subSelection'] as $sub_key => $selection ) {
										if ( isset( $selection['value'] ) && ! empty( $selection['value'] ) ) {
											$show_taxs[] = $selection['value'];
										}
									}
									if ( in_array( $queried_obj->term_id, $show_taxs ) ) {
										$show = false;
									}
								}
							}
						} else {
							$show = false;
						}
					}
				}
			}
		}

		if ( $show ) {
			if ( isset( $meta, $meta['user'] ) && is_array( $meta['user'] ) && ! empty( $meta['user'] ) ) {
				$user_info  = self::get_current_user_info();
				$show_roles = [];
				foreach ( $meta['user'] as $key => $user_rule ) {
					if ( isset( $user_rule['role'] ) && ! empty( $user_rule['role'] ) ) {
						$show_roles[] = $user_rule['role'];
					}
				}

				$match = array_intersect( $show_roles, $user_info );

				if ( count( $match ) === 0 ) {
					$show = false;
				}
			}
		}

		if ( $show ) {
			if ( isset( $meta, $meta['enable_expires'], $meta['expires'] ) && true === $meta['enable_expires'] && ! empty( $meta['expires'] ) ) {
				$expires = strtotime( get_date_from_gmt( $meta['expires'] ) );
				$now     = strtotime( get_date_from_gmt( current_time( 'Y-m-d H:i:s' ) ) );
				if ( $expires < $now ) {
					$show = false;
				}
			}
		}

		return $show;
	}


	public static function get_current_page_conditions(): ?array {

		if ( ! is_null( self::$current_condition ) ) {
			return self::$current_condition;
		}

		$condition = [ 'general|site' ];

		if ( is_front_page() ) {
			$condition[] = 'general|front_page';
		}

		if ( is_home() ) {
			$condition[] = 'general|archive';
			$condition[] = 'post_type_archive|post';
			$condition[] = 'general|home';
		} elseif ( is_search() ) {
			$condition[] = 'general|search';
			if ( class_exists( 'woocommerce' ) && function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
				$condition[] = 'general|product_search';
			}
		} elseif ( is_404() ) {
			$condition[] = 'general|404';
		} elseif ( is_singular() ) {
			$condition[] = 'general|singular';
			$condition[] = 'singular|' . get_post_type();

		} elseif ( is_archive() ) {
			$queried_obj = get_queried_object();
			$condition[] = 'general|archive';
			if ( is_object( $queried_obj ) && is_post_type_archive() ) {
				$condition[] = 'post_type_archive|' . $queried_obj->name;
			} elseif ( is_tax() || is_category() || is_tag() ) {
				if ( is_object( $queried_obj ) ) {
					$condition[] = 'tax_archive|' . $queried_obj->taxonomy;
				}
			} elseif ( is_date() ) {
				$condition[] = 'general|date';
			} elseif ( is_author() ) {
				$condition[] = 'general|author';
			}
		}
		if ( is_paged() ) {
			$condition[] = 'general|paged';
		}
		if ( class_exists( 'woocommerce' ) ) {
			if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
				$condition[] = 'general|woocommerce';
			}
		}
		self::$current_condition = $condition;

		return self::$current_condition;
	}


	public static function get_current_user_info(): array {

		if ( ! is_null( self::$current_user ) ) {
			return self::$current_user;
		}

		$user_info = [ 'public' ];

		if ( is_user_logged_in() ) {
			$user_info[] = 'logged_in';
			$user        = wp_get_current_user();
			$user_info   = array_merge( $user_info, $user->roles );
		} else {
			$user_info[] = 'logged_out';
		}

		self::$current_user = $user_info;

		return self::$current_user;

	}


	public function post_is_in_descendant_term( $term_id, $tax ): bool {

		$descendants = get_term_children( (int) $term_id, $tax );
		if ( is_array( $descendants ) && ! is_wp_error( $descendants ) ) {
			foreach ( $descendants as $child_id ) {
				if ( has_term( $child_id, $tax ) ) {
					return true;
				}
			}
		}

		return false;
	}


	public function get_post_meta_array( $post ): array {

		$meta = [
			'hook'           => 'custom',
			'custom'         => '',
			'priority'       => '',
			'scroll'         => '300',
			'show'           => [],
			'all_show'       => false,
			'hide'           => [],
			'user'           => [],
			'device'         => [],
			'enable_expires' => false,
			'expires'        => '',
			'type'           => '',
			'fixed_width'    => '',
			'width'          => 300,
			'fixed_position' => 'left',
			'xposition'      => 0,
			'yposition'      => 0,
		];

		if ( get_post_meta( $post->ID, '_ae_element_type', true ) ) {
			$meta['type'] = get_post_meta( $post->ID, '_ae_element_type', true );
		}

		if ( get_post_meta( $post->ID, $this->main->get_field_slug() . '_hook_custom', true ) ) {
			$meta['hook'] = get_post_meta( $post->ID, $this->main->get_field_slug() . '_hook_custom', true );
			if ( ( 'custom' === $meta['hook'] ) && get_post_meta( $post->ID, $this->main->get_field_slug() . '_hook_custom', true ) ) {
				$meta['custom'] = get_post_meta( $post->ID, $this->main->get_field_slug() . '_hook_custom', true );
			}
		}

		if ( get_post_meta( $post->ID, $this->main->get_field_slug() . '_hook_priority', true ) ) {
			$meta['priority'] = get_post_meta( $post->ID, $this->main->get_field_slug() . '_hook_priority', true );
		}

		/*if ( '' !== get_post_meta( $post->ID, '_ae_element_hook_scroll', true ) ) {
			$meta['scroll'] = get_post_meta( $post->ID, '_ae_element_hook_scroll', true );
		}*/

		if ( get_post_meta( $post->ID, '_ae_element_show_conditionals', true ) ) {
			$meta['show'] = json_decode( get_post_meta( $post->ID, '_ae_element_show_conditionals', true ), true );
		}

		if ( get_post_meta( $post->ID, '_ae_element_all_show', true ) ) {
			$meta['all_show'] = boolval( get_post_meta( $post->ID, '_ae_element_all_show', true ) );
		}

		if ( get_post_meta( $post->ID, '_ae_element_hide_conditionals', true ) ) {
			$meta['hide'] = json_decode( get_post_meta( $post->ID, '_ae_element_hide_conditionals', true ), true );
		}

		if ( get_post_meta( $post->ID, '_ae_element_user_conditionals', true ) ) {
			$meta['user'] = json_decode( get_post_meta( $post->ID, '_ae_element_user_conditionals', true ), true );
		}

		if ( get_post_meta( $post->ID, '_ae_element_device_conditionals', true ) ) {
			$meta['device'] = json_decode( get_post_meta( $post->ID, '_ae_element_device_conditionals', true ), true );
		}

		if ( get_post_meta( $post->ID, '_ae_element_enable_expires', true ) ) {
			$meta['enable_expires'] = get_post_meta( $post->ID, '_ae_element_enable_expires', true );
		}

		if ( get_post_meta( $post->ID, '_ae_element_expires', true ) ) {
			$meta['expires'] = get_post_meta( $post->ID, '_ae_element_expires', true );
		}

		if ( get_post_meta( $post->ID, '_ae_element_fixed_width', true ) ) {
			$meta['fixed_width'] = get_post_meta( $post->ID, '_ae_element_fixed_width', true );
		}

		if ( isset( $meta['fixed_width'] ) && '' !== $meta['fixed_width'] ) {
			if ( get_post_meta( $post->ID, '_ae_element_width', true ) ) {
				$meta['width'] = get_post_meta( $post->ID, '_ae_element_width', true );
			}
			if ( get_post_meta( $post->ID, '_ae_element_fixed_position', true ) ) {
				$meta['fixed_position'] = get_post_meta( $post->ID, '_ae_element_fixed_position', true );
			}
			if ( get_post_meta( $post->ID, '_ae_element_xposition', true ) ) {
				$meta['xposition'] = get_post_meta( $post->ID, '_ae_element_xposition', true );
			}
			if ( get_post_meta( $post->ID, '_ae_element_yposition', true ) ) {
				$meta['yposition'] = get_post_meta( $post->ID, '_ae_element_yposition', true );
			}
		}

		return $meta;
	}


	public function enqueue_element_styles( $post, $meta, $shortcode = false ): void {

		$content = $post->post_content;

		if ( ! $content ) {
			return;
		}

		if ( has_blocks( $content ) ) {
			if ( class_exists( 'Kadence_Blocks_Frontend' ) ) {
				$kadence_blocks = Kadence_Blocks_Frontend::get_instance();
				if ( method_exists( $kadence_blocks, 'frontend_build_css' ) ) {
					$kadence_blocks->frontend_build_css( $post );
				}
				if ( class_exists( 'Kadence_Blocks_Pro_Frontend' ) ) {
					$kadence_blocks_pro = Kadence_Blocks_Pro_Frontend::get_instance();
					if ( method_exists( $kadence_blocks_pro, 'frontend_build_css' ) ) {
						$kadence_blocks_pro->frontend_build_css( $post );
					}
				}
			}

			return;
		}

		$builder_info = $this->check_for_pagebuilder( $post );
		$post_id      = $post->ID;
		/**
		 * Get block scripts based on its editor/builder.
		 */
		/*switch ( $builder_info ) {
			case 'elementor':
				add_action( 'wp_enqueue_scripts', function() use ( $post_id ) {
					if ( class_exists( '\Elementor\Plugin' ) ) {

						$elementor = \Elementor\Plugin::instance();
						$elementor->frontend->enqueue_styles();

						if ( class_exists( '\ElementorPro\Plugin' ) ) {
							$elementor_pro = \ElementorPro\Plugin::instance();
							$elementor_pro->enqueue_styles();
						}
						if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
							$css_file = new \Elementor\Core\Files\CSS\Post( $post_id );
							$css_file->enqueue();
						}
					}
				} );
				break;
			case 'brizy':
				$brizy_element = \Brizy_Editor_Post::get( $post_id );
				if ( method_exists( '\Brizy_Public_Main', 'get' ) ) {
					$brizy_class = \Brizy_Public_Main::get( $brizy_element );
				} else {
					$brizy_class = new \Brizy_Public_Main( $brizy_element );
				}

				// Enqueue general Brizy scripts.
				add_filter( 'body_class', array( $brizy_class, 'body_class_frontend' ) );
				add_action( 'wp_enqueue_scripts', array( $brizy_class, '_action_enqueue_preview_assets' ), 999 );
				// Enqueue current page scripts.
				add_action( 'wp_head', function() use ( $brizy_element ) {
					$brizy_project = \Brizy_Editor_Project::get();
					$brizy_html    = new \Brizy_Editor_CompiledHtml( $brizy_element->get_compiled_html() );

					echo apply_filters( 'brizy_content', $brizy_html->get_head(), $brizy_project, $brizy_element->get_wp_post() );
				} );
				break;
		}*/
	}


	public function setup_content_filter(): void {

		global $wp_embed;
		add_filter( 'ae_the_content', [ $wp_embed, 'run_shortcode' ], 8 );
		add_filter( 'ae_the_content', [ $wp_embed, 'autoembed' ], 8 );
		add_filter( 'ae_the_content', 'do_blocks' );
		add_filter( 'ae_the_content', 'wptexturize' );
		add_filter( 'ae_the_content', 'convert_chars' );
		// Don't use this unless classic editor add_filter( 'ae_the_content', 'wpautop' );
		add_filter( 'ae_the_content', 'shortcode_unautop' );
		add_filter( 'ae_the_content', 'do_shortcode', 11 );
		add_filter( 'ae_the_content', 'convert_smilies', 20 );

		add_filter( 'ae_code_the_content', [ $wp_embed, 'run_shortcode' ], 8 );
		add_filter( 'ae_code_the_content', [ $wp_embed, 'autoembed' ], 8 );
		add_filter( 'ae_code_the_content', 'do_blocks' );
		//add_filter( 'ktp_code_the_content', 'wptexturize' );
		//add_filter( 'ktp_code_the_content', 'convert_chars' );
		// Don't use this unless classic editor add_filter( 'ktp_code_the_content', 'wpautop' );
		add_filter( 'ae_code_the_content', 'shortcode_unautop' );
		add_filter( 'ae_code_the_content', 'do_shortcode', 11 );
		add_filter( 'ae_code_the_content', 'convert_smilies', 20 );
	}


	public function output_element( $post, $meta, $shortcode = false ) {

		$content = $post->post_content;
		if ( ! $content ) {
			return;
		}
		if ( isset( $meta['device'] ) && ! empty( $meta['device'] ) && is_array( $meta['device'] ) ) {
			$element_device_classes = [ 'ae-element-wrap' ];
			$devices                = [];

			foreach ( $meta['device'] as $key => $setting ) {
				$devices[] = $setting['value'];
			}

			if ( ! in_array( 'desktop', $devices, true ) ) {
				$element_device_classes[] = 'vs-lg-false';
			}

			if ( ! in_array( 'tablet', $devices, true ) ) {
				$element_device_classes[] = 'vs-md-false';
			}

			if ( ! in_array( 'mobile', $devices, true ) ) {
				$element_device_classes[] = 'vs-sm-false';
			}

			echo '<div class="' . esc_attr( implode( " ", $element_device_classes ) ) . '">';
		}
		// if ( has_blocks( $content ) ) {
		// 	echo apply_filters( 'ae_the_content', $content );
		// 	if ( isset( $meta['device'] ) && ! empty( $meta['device'] ) && is_array( $meta['device'] ) ) {
		// 		echo '</div>';
		// 	}
		// 	return;
		// }
		if ( isset( $meta['type'] ) && ! empty( $meta['type'] ) && 'script' === $meta['type'] ) {
			echo apply_filters( 'ae_code_the_content', $content );
			if ( isset( $meta['device'] ) && ! empty( $meta['device'] ) && is_array( $meta['device'] ) ) {
				echo '</div>';
			}

			return;
		}

		$builder_info = $this->check_for_pagebuilder( $post );

		switch ( $builder_info ) {
			case 'elementor':
				$content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $post->ID );
				break;
			case 'beaver':
				ob_start();
				FLBuilder::render_query(
					[
						'post_type' => self::$slug,
						'p'         => $post->ID,
					]
				);
				$content = ob_get_clean();
				break;
			case 'brizy':
				$brizy = Brizy_Editor_Post::get( $post->ID );
				$html  = new Brizy_Editor_CompiledHtml( $brizy->get_compiled_html() );
				// the <head> content
				// the $headHtml contains all the assets the page needs
				$scripts = apply_filters( 'brizy_content', $html->get_head(), Brizy_Editor_Project::get(), $brizy->getWpPost() );
				// the <body> content
				$content = apply_filters( 'brizy_content', $html->get_body(), Brizy_Editor_Project::get(), $brizy->getWpPost() );
				break;
			case 'panels':
				$content = siteorigin_panels_render( $post->ID );
				break;
			default:
				$content = apply_filters( 'ae_the_content', $content );
				break;
		}

		if ( isset( $scripts ) && ! empty( $scripts ) ) {
			echo '<!-- [element-script-' . esc_attr( $post->ID ) . '] -->';
			echo $scripts;
			echo '<!-- [/element-script-' . esc_attr( $post->ID ) . '] -->';
		}

		if ( $content ) {
			echo '<!-- [element-' . esc_attr( $post->ID ) . '] -->';
			echo $content;
			echo '<!-- [/element-' . esc_attr( $post->ID ) . '] -->';
		}

		if ( isset( $meta['device'] ) && ! empty( $meta['device'] ) && is_array( $meta['device'] ) ) {
			echo '</div>';
		}
	}


	/**
	 * Check if page is built with elementor.
	 *
	 * @param  object $post the post object.
	 */
	protected function check_for_pagebuilder( $post ): string {

		$builder = 'default';
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::instance()->db->is_built_with_elementor( $post->ID ) ) {
			// Element is built with elementor.
			$builder = 'elementor';
		} elseif ( class_exists( 'Brizy_Editor_Post' ) && class_exists( 'Brizy_Editor' ) ) {
			$supported_post_types = Brizy_Editor::get()->supported_post_types();
			if ( in_array( self::$slug, $supported_post_types, true ) ) {
				if ( Brizy_Editor_Post::get( $post->ID )->uses_editor() ) {
					// Element is built with brizy.
					$builder = 'brizy';
				}
			}
		} elseif ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_enabled( $post->ID ) ) {
			// Element is built with beaver.
			$builder = 'beaver';
		} elseif ( class_exists( 'SiteOrigin_Panels_Settings' ) && siteorigin_panels_render( $post->ID ) ) {
			// Element is built with SiteOrigin.
			$builder = 'panels';
		}

		return $builder;
	}

}