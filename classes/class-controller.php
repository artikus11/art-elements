<?php

namespace Art\Elements;

use Kadence_Blocks_Frontend;

class Controller {

	public const SLUG = 'ae_element';


	/**
	 * @var string[]
	 */
	public static ?array $current_condition = null;

	/**
	 * @var array|string[]
	 */
	public static ?array $current_user = null;

	public static string $fields = '_ae_elements_fields';


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
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning instances of the class is Forbidden', 'kadence-pro' ), '1.0' );
	}


	/**
	 * Disable un-serializing of the class.
	 *
	 * @return void
	 */
	public function __wakeup() {

		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of the class is forbidden', 'kadence-pro' ), '1.0' );
	}


	public function setup_hook(): void {

		add_action( 'init', [ $this, 'register_post_type' ], 1 );
		add_action( 'init', [ $this, 'register_meta' ], 20 );
		add_action( 'wp', [ $this, 'init_frontend_hooks' ], 99 );
		add_action( 'init', array( $this, 'setup_content_filter' ), 9 );

		add_action( 'add_meta_boxes', [ $this, 'add_meta_box_review' ] );
		add_action( 'save_post_' . self::SLUG, [ $this, 'save_metabox' ], 10, 2 );
	}


	public function add_meta_box_review(): void {

		add_meta_box(
			'ae_elements_metabox',
			__( 'Hooks' ),
			[ $this, 'render_meta_box_content' ],
			self::SLUG,
			'side',
			'high',
		);

	}


	public function save_metabox( int $post_id ): void {

		/*if ( ! isset( $_POST['ainsys_template_inner_nonce'] ) ) {
			return;
		}*/

		$nonce = $_POST['ainsys_template_inner_nonce'];

		if ( ! wp_verify_nonce( $nonce, 'ainsys_template_inner' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( self::SLUG === $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = $this->get_fields( $post_id );

		$_fields = array_map( 'sanitize_text_field', $_POST );

		foreach ( $fields as $key => $val ) {

			$key_post = self::$fields . '_' . $key;

			if ( isset( $_fields[ $key_post ] ) ) {
				update_post_meta( $post_id, $key_post, $_fields[ $key_post ] );
			} else {
				delete_post_meta( $post_id, $key_post );
			}
		}
	}


	private function get_fields( $post_id ): array {

		return [
			'hook_custom'   => [
				'label'             => __( 'Hook Name' ),
				'required'          => true,
				'type'              => 'text',
				'class'             => [ 'template-input' ],
				'value'             => get_post_meta( $post_id, self::$fields . '_hook_custom', true ) ? : '',
				'custom_attributes' => [ 'style' => 'width:100%' ],
			],
			'hook_priority' => [
				'label'             => __( 'Hook Priority' ),
				'required'          => true,
				'type'              => 'number',
				'class'             => [ 'template-input' ],
				'value'             => get_post_meta( $post_id, self::$fields . '_hook_priority', true ) ? : '',
				'custom_attributes' => [ 'style' => 'width:100%' ],
			],

		];

	}


	public function metabox_field( $key, $args, $value = null ) {

		$defaults = [
			'type'              => 'text',
			'label'             => '',
			'description'       => '',
			'placeholder'       => '',
			'maxlength'         => false,
			'required'          => false,
			'autocomplete'      => false,
			'id'                => $key,
			'class'             => [],
			'label_class'       => [],
			'input_class'       => [],
			'return'            => false,
			'options'           => [],
			'custom_attributes' => [],
			'validate'          => [],
			'default'           => '',
			'autofocus'         => '',
			'priority'          => '',
		];

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'ainsys_form_field_args', $args, $key, $value );

		if ( is_string( $args['class'] ) ) {
			$args['class'] = [ $args['class'] ];
		}

		$required = '';
		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required        = '&nbsp;*';
		}

		if ( is_string( $args['label_class'] ) ) {
			$args['label_class'] = [ $args['label_class'] ];
		}

		if ( is_null( $value ) ) {
			$value = $args['default'];
		}

		$custom_attributes         = [];
		$args['custom_attributes'] = array_filter( (array) $args['custom_attributes'], 'strlen' );

		if ( $args['maxlength'] ) {
			$args['custom_attributes']['maxlength'] = absint( $args['maxlength'] );
		}

		if ( ! empty( $args['autocomplete'] ) ) {
			$args['custom_attributes']['autocomplete'] = $args['autocomplete'];
		}

		if ( true === $args['autofocus'] ) {
			$args['custom_attributes']['autofocus'] = 'autofocus';
		}

		if ( $args['description'] ) {
			$args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
		}

		if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
			foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field           = '';
		$label_id        = $args['id'];
		$sort            = $args['priority'] ? : '';
		$field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';

		switch ( $args['type'] ) {
			case 'textarea':
				$field .= sprintf(
					'<textarea name="%s_%s" class="input-text %s" id="%s" placeholder="%s" %s%s%s>%s</textarea>',
					self::$fields,
					esc_attr( $key ),
					esc_attr( implode( ' ', $args['input_class'] ) ),
					esc_attr( $args['id'] ), esc_attr( $args['placeholder'] ),
					empty( $args['custom_attributes']['rows'] ) ? ' rows="2"' : '',
					empty( $args['custom_attributes']['cols'] ) ? ' cols="5"' : '',
					implode( ' ', $custom_attributes ),
					esc_textarea( $value )
				);

				break;
			case 'checkbox':
				$field = sprintf(
					'<label class="checkbox %s" %s><input type="%s" class="input-checkbox %s" name="%s_%s" id="%s" value="1" %s /> %s%s</label>',
					implode( ' ', $args['label_class'] ),
					implode( ' ', $custom_attributes ),
					esc_attr( $args['type'] ),
					esc_attr( implode( ' ', $args['input_class'] ) ),
					self::$fields,
					esc_attr( $key ),
					esc_attr( $args['id'] ),
					checked( $value, 1, false ),
					$args['label'],
					$required
				);

				break;
			case 'text':
			case 'password':
			case 'datetime':
			case 'datetime-local':
			case 'date':
			case 'month':
			case 'time':
			case 'week':
			case 'number':
			case 'email':
			case 'url':
			case 'tel':
				$field .= sprintf(
					'<input type="%s" class="input-text %s" name="%s_%s" id="%s" placeholder="%s"  value="%s" %s />',
					esc_attr( $args['type'] ),
					esc_attr( implode( ' ', $args['input_class'] ) ),
					self::$fields,
					esc_attr( $key ), esc_attr( $args['id'] ),
					esc_attr( $args['placeholder'] ),
					esc_attr( $value ),
					implode( ' ', $custom_attributes )
				);

				break;
			case 'hidden':
				$field .= sprintf(
					'<input type="%s" class="input-hidden %s" name="%s_%s" id="%s" value="%s" %s />',
					esc_attr( $args['type'] ),
					esc_attr( implode( ' ', $args['input_class'] ) ),
					self::$fields,
					esc_attr( $key ),
					esc_attr( $args['id'] ),
					esc_attr( $value ),
					implode( ' ', $custom_attributes )
				);

				break;
			case 'select':
				$field   = '';
				$options = '';

				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						if ( '' === $option_key ) {
							if ( empty( $args['placeholder'] ) ) {
								$args['placeholder'] = $option_text ? : __( 'Choose an option', AINSYS_CONNECTOR_CONTENT_TEXTDOMAIN );
							}
							$custom_attributes[] = 'data-allow_clear="true"';
						}

						$options .= sprintf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $option_key ),
							selected( $value, $option_key, false ),
							esc_html( $option_text )
						);
					}

					$field .= sprintf(
						'<select name="%s_%s" id="%s" class="select %s" %s data-placeholder="%s">%s</select>',
						self::$fields,
						esc_attr( $key ),
						esc_attr( $args['id'] ),
						esc_attr( implode( ' ', $args['input_class'] ) ),
						implode( ' ', $custom_attributes ),
						esc_attr( $args['placeholder'] ),
						$options
					);
				}

				break;
			case 'radio':
				$label_id .= sprintf( '_%s', current( array_keys( $args['options'] ) ) );

				if ( ! empty( $args['options'] ) ) {
					foreach ( $args['options'] as $option_key => $option_text ) {
						$field .= sprintf(
							'<input type="radio" class="input-radio %s" value="%s" name="%s_%s" %s id="%s_%s"%s />',
							esc_attr( implode( ' ', $args['input_class'] ) ),
							esc_attr( $option_key ),
							self::$fields,
							esc_attr( $key ),
							implode( ' ', $custom_attributes ),
							esc_attr( $args['id'] ),
							esc_attr( $option_key ),
							checked( $value, $option_key, false )
						);
						$field .= sprintf(
							'<label for="%s_%s" class="radio %s">%s</label>',
							esc_attr( $args['id'] ),
							esc_attr( $option_key ),
							implode( ' ', $args['label_class'] ),
							esc_html( $option_text )
						);
					}
				}

				break;
		}

		if ( ! empty( $field ) ) {
			$field_html = '';

			if ( $args['label'] && 'checkbox' !== $args['type'] ) {
				$field_html .= sprintf(
					'<label for="%s" class="%s">%s%s</label>',
					esc_attr( $label_id ),
					esc_attr( implode( ' ', $args['label_class'] ) ),
					wp_kses_post( $args['label'] ),
					$required
				);
			}

			$field_html .= '<span class="ainsys-input-wrapper">' . $field;

			if ( $args['description'] ) {
				$field_html .= sprintf(
					'<span class="description" id="%s-description" aria-hidden="true">%s</span>',
					esc_attr( $args['id'] ),
					wp_kses_post( $args['description'] )
				);
			}

			$field_html .= '</span>';

			$container_class = esc_attr( implode( ' ', $args['class'] ) );
			$container_id    = esc_attr( $args['id'] ) . '_field';
			$field           = sprintf( $field_container, $container_class, $container_id, $field_html );
		}

		$field = apply_filters( 'ainsys_form_field_' . $args['type'], $field, $key, $args, $value );

		$field = apply_filters( 'ainsys_form_field', $field, $key, $args, $value );

		if ( $args['return'] ) {
			return $field;
		}

		echo $field;
	}


	/**
	 *
	 * @param  /WP_Post $post
	 */
	public function render_meta_box_content( $post ): void {

		wp_nonce_field( 'ainsys_template_inner', 'ainsys_template_inner_nonce' );

		$post_id = (int) $post->ID;
		$fields  = $this->get_fields( $post_id );

		foreach ( $fields as $key => $field ) {
			$this->metabox_field( $key, $field, $field['value'] );
		}

	}


	public function init_frontend_hooks(): void {

		if ( is_admin() || is_singular( self::SLUG ) ) {
			return;
		}

		$args = [
			'post_type'              => self::SLUG,
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
				if ( ('custom' !== $meta['hook'] && ! empty( $meta['hook'] ) && strpos( $meta['hook'], 'fixed' ) !== 0)
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
					do_action( 'qm/info',$meta);
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

		$current_condition    = self::get_current_page_conditions();
		do_action( 'qm/info',$current_condition);
		
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


		if ( get_post_meta( $post->ID, self::$fields . '_hook_custom', true ) ) {
			$meta['hook'] = get_post_meta( $post->ID, self::$fields . '_hook_custom', true );
			if ( ( 'custom' === $meta['hook'] ) && get_post_meta( $post->ID, self::$fields . '_hook_custom', true ) ) {
				$meta['custom'] = get_post_meta( $post->ID, self::$fields . '_hook_custom', true );
			}
		}

		if ( get_post_meta( $post->ID, '_ae_element_hook_priority', true ) ) {
			$meta['priority'] = get_post_meta( $post->ID, '_ae_element_hook_priority', true );
		}

		if ( '' !== get_post_meta( $post->ID, '_ae_element_hook_scroll', true ) ) {
			$meta['scroll'] = get_post_meta( $post->ID, '_ae_element_hook_scroll', true );
		}

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

	public function setup_content_filter() {
		global $wp_embed;
		add_filter( 'ae_the_content', array( $wp_embed, 'run_shortcode' ), 8 );
		add_filter( 'ae_the_content', array( $wp_embed, 'autoembed'     ), 8 );
		add_filter( 'ae_the_content', 'do_blocks' );
		add_filter( 'ae_the_content', 'wptexturize' );
		add_filter( 'ae_the_content', 'convert_chars' );
		// Don't use this unless classic editor add_filter( 'ae_the_content', 'wpautop' );
		add_filter( 'ae_the_content', 'shortcode_unautop' );
		add_filter( 'ae_the_content', 'do_shortcode', 11 );
		add_filter( 'ae_the_content', 'convert_smilies', 20 );

		add_filter( 'ae_code_the_content', array( $wp_embed, 'run_shortcode' ), 8 );
		add_filter( 'ae_code_the_content', array( $wp_embed, 'autoembed'     ), 8 );
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
						'post_type' => self::SLUG,
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
			if ( in_array( self::SLUG, $supported_post_types, true ) ) {
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


	/**
	 * Register Post Meta options
	 */
	public function register_meta(): void {

		register_post_meta(
			self::SLUG,
			'_ae_element_hook_custom',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => '__return_true',
			]
		);
		register_post_meta(
			self::SLUG,
			'_ae_element_hook_priority',
			[
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'number',
				'default'       => 10,
				'auth_callback' => '__return_true',
			]
		);

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

		register_post_type( self::SLUG, $args );
	}

}