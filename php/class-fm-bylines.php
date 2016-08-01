<?php
/**
 * Fieldmanager Byline Class
 *
 */

if ( ! class_exists( 'FM_Bylines' ) ) {

	class FM_Bylines {

		private static $instance;

		/**
		 * The post type name
		 */
		public $name = 'byline';

		/**
		 * Slug used for rewrites
		 */
		public $slug = 'byline';

		private function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new FM_Bylines();
				self::$instance->setup();
			}
			return self::$instance;
		}

		public function setup() {
			// Set the slug we will use for the base byline type.
			$this->slug = sanitize_title_with_dashes( apply_filters( 'fm_bylines_filter_rewrite_slug', $this->slug ) );

			add_action( 'init', array( $this, 'setup_data_structure' ), 20 );

			// Add custom meta boxes
			if ( is_admin() ) {
				add_action( 'fm_post_' . $this->name, array( $this, 'add_meta_boxes' ) );
				add_filter( 'enter_title_here', array( $this, 'set_display_name_description' ), 10, 2 );
				add_filter( 'fm_presave_alter_values', array( $this, 'save_byline_meta' ), 10, 3 );

				add_action( 'delete_post', array( $this, 'delete_byline' ) );

				add_filter( 'fm_element_classes', array( $this, 'display_default_byline' ), 10, 3 );
			}

			add_filter( 'get_avatar', array( $this, 'get_avatar' ), 20, 6 );

			// Force the user avatar in the admin bar and admin area
			add_action( 'admin_bar_menu', array( $this, 'force_user_avatar' ), 0 );
			add_action( 'add_admin_bar_menus', array( $this, 'remove_force_user_avatar' ), 9999 );
		}

		public function setup_data_structure() {
			$this->create_post_type();
		}

		/**
		 * Creates the post type.
		 *
		 * @access public
		 * @return void
		 */
		public function create_post_type() {
			$labels = array(
				'name' => _x( 'Bylines', 'post type general name', 'fm_bylines' ),
				'singular_name' => _x( 'Byline', 'post type singular name', 'fm_bylines' ),
				'plural_name' => _x( 'All Bylines', 'post type plural name', 'fm_bylines' ),
				'add_new' => esc_html__( 'Add New', 'fm_bylines' ),
				'add_new_item' => esc_html__( 'Add New', 'fm_bylines' ),
				'edit_item' => esc_html__( 'Edit', 'fm_bylines' ),
				'new_item' => esc_html__( 'New', 'fm_bylines' ),
				'view_item' => esc_html__( 'View', 'fm_bylines' ),
				'search_items' => esc_html__( 'Search', 'fm_bylines' ),
				'not_found' => esc_html__( 'No bylines found', 'fm_bylines' ),
				'not_found_in_trash' => esc_html__( 'No bylines found in Trash', 'fm_bylines' ),
			);

			$args = array(
				'labels' => $labels,
				'publicly_queryable' => true,
				'public' => true,
				'show_ui' => true,
				'query_var' => true,
				'taxonomies' => array(),
				'has_archive' => true,
				'rewrite' => array( 'slug' => $this->slug, 'with_front' => false ),
				'hierarchical' => false,
				'supports' => array( 'title', 'thumbnail' ),
				'show_in_menu' => 'users.php',
				'show_in_nav_menus' => false,
				'menu_icon' => '',
			);

			register_post_type( $this->name, $args );
		}

		/**
		 * Return all the known meta keys for bylines mapped to the correct meta field.  Use for get_the_author_meta() and
		 * Uses relevant default author meta fields and byline fm fields
		 * User login specific meta keys have been dropped.
		 * Use the filter hook if you need to add more
		 */
		public function byline_meta_keys() {
			$keys = array(
				'ID' => 'ID', // These values are actually post fields.
				'user_nicename' => 'post_name',
				'nickname' => 'post_title',
				'nicename' => 'post_name',

				'email' => 'email', // These are you basic post meta keys.
				'website' => 'website',
				'url' => 'website',
				'user_email' => 'email',
				'user_url' => 'website',
				'display_name' => 'post_title',
				'first_name' => 'first_name',
				'last_name' => 'last_name',
				'user_firstname' => 'first_name',
				'user_lastname' => 'last_name',
				'description' => 'bio',
				'bio' => 'bio',

				'twitter' => 'twitter',
				'jabber' => 'jabber', // These social media sites below are not default in FM Bylines. Feel free to add them using the fm_bylines_filter_contact_info_fields hook.
				'aim' => 'aim',
				'yim' => 'yim',
				'googleplus' => 'googleplus',

				'login' => 'fm_error_key', // We don't want this info shared when we are using FM bylines, so we set a default error key.
				'pass' => 'fm_error_key',
				'registered' => 'fm_error_key',
				'activation_key' => 'fm_error_key',
				'status' => 'fm_error_key',
				'user_login' => 'fm_error_key',
				'user_pass' => 'fm_error_key',
				'user_registered' => 'fm_error_key',
				'user_activation_key' => 'fm_error_key',
				'user_status' => 'fm_error_key',
				'roles' => 'fm_error_key',
				'user_level' => 'fm_error_key',
				'rich_editing' => 'fm_error_key',
				'comment_shortcuts' => 'fm_error_key',
				'admin_color' => 'fm_error_key',
				'plugins_per_page' => 'fm_error_key',
				'plugins_last_view ' => 'fm_error_key',
			);
			return apply_filters( 'fm_bylines_filter_meta_keys', $keys );
		}

		/**
		 * Adds custom meta boxes for this post type.
		 *
		 * @access public
		 * @return void
		 */
		public function add_meta_boxes() {

			// Names.
			$name_types = array(
				'first_name' => __( 'First Name', 'fm_bylines' ),
				'last_name' => __( 'Last Name', 'fm_bylines' ),
			);
			$fm_name_children = array();
			foreach ( $name_types as $slug => $label ) {
				$fm_name_children[ $slug ] = new Fieldmanager_Textfield( $label, array() );
			}

			$fm_name_children = apply_filters( 'fm_bylines_filter_name_fields', $fm_name_children );

			$fm_names = new Fieldmanager_Group( array(
				'name' => 'fm_bylines_names',
				'children' => $fm_name_children,
			) );
			$fm_names->add_meta_box( __( 'Additional Name Info', 'fm_bylines' ), array( $this->name ) );

			// Contact Info.
			$contact_types = array(
				'email' => __( 'E-mail', 'fm_bylines' ),
				'website' => __( 'Website', 'fm_bylines' ),
				'twitter' => __( 'Twitter', 'fm_bylines' ),
			);
			$fm_contact_children = array();
			foreach ( $contact_types as $slug => $label ) {
				$fm_contact_children[ $slug ] = new Fieldmanager_Textfield( $label, array() );
			}

			$fm_contact_children = apply_filters( 'fm_bylines_filter_contact_info_fields', $fm_contact_children );

			$fm_contact_info = new Fieldmanager_Group( array(
				'name' => 'fm_bylines_contact_info',
				'children' => $fm_contact_children,
			) );
			$fm_contact_info->add_meta_box( __( 'Contact Info', 'fm_bylines' ), array( $this->name ) );

			// About.
			$fm_about_children = array();
			$fm_about_children['bio'] = new Fieldmanager_Textarea( __( 'Bio', 'fm_bylines' ), array() );

			$fm_about_children = apply_filters( 'fm_bylines_filter_about_fields', $fm_about_children );

			$fm_about = new Fieldmanager_Group( array(
				'name' => 'fm_bylines_about',
				'children' => $fm_about_children,
			) );
			$fm_about->add_meta_box( __( 'About', 'fm_bylines' ), array( $this->name ) );

			//  User Mapping.
			$fm_user_mapping = new Fieldmanager_Autocomplete( array(
				'name' => 'fm_bylines_user_mapping',
				'label' => __( 'WordPress User Login', 'fm_bylines' ),
				'description' => __( 'This mapping will populate byline meta boxes with this byline by default when this user is logged in.  Users without a mapping will default to byline metaboxes being empty.', 'fm_bylines' ),
				'datasource' => new Fieldmanager_Datasource_User( array(
					'reciprocal' => 'fm_bylines_user_mapping',
					'query_callback' => 'FM_Bylines::get_user_list',
				) ),
				'attributes' => array(
					'style' => 'width: 100%',
				),
			) );
			$fm_user_mapping->add_meta_box( __( 'WordPress User Mapping', 'fm_bylines' ), array( $this->name ), 'side', 'default' );
		}

		/**
		 * Make user search allow partial matches
		 */
		public static function get_user_list( $fragment ) {
			$user_args = array(
				'search_columns' => array( 'user_login', 'user_email' ),
			);
			$ret = array();

			if ( $fragment ) {
				$user_args['search'] = '*' . $fragment . '*';
			}

			$users = get_users( $user_args );
			foreach ( $users as $u ) {
				$ret[ $u->ID ] = $u->user_login;
			}

			return $ret;
		}

		/**
		 * Add in a FM_Byline meta box w/ all it's bells and whistles
		 * @param string $type
		 * @param string. Optional label
		 * @param array $args
		 */
		function add_byline_meta_box( $type = 'author', $label = null, $args = array() ) {

			if ( is_admin() ) {
				$context = fm_get_context();
				$fm_context = $context[0];
				$fm_context_type = $context[1];
				$label = empty( $label ) ? fm_bylines_wordify_slug( $type ) : $label;
				$defaults = array(
					'name' => 'fm_bylines_' . sanitize_title_with_dashes( $type ),
					'limit' => 0,
					'add_more_label' => __( 'Add another', 'fm_bylines' ),
					'sortable' => true,
					'label' => __( 'Name', 'fm_bylines' ),
					'children' => array(
						'byline_id' => new Fieldmanager_Autocomplete( array(
							'default_value' => null,
							'datasource' => new Fieldmanager_Datasource_Post( array(
								'query_args' => array(
									'post_type' => $this->name,
									'no_found_rows' => true,
									'ignore_sticky_posts' => true,
									'post_status' => 'publish',
									'suppress_filters' => false,
								),
							) ),
						) ),
						'fm_byline_type' => new Fieldmanager_Hidden( array( 'default_value' => sanitize_title_with_dashes( $type ) ) ),
					),
				);

				$fm_byline_box = new Fieldmanager_Group( wp_parse_args( $args, $defaults ) );

				if ( 'post' == $fm_context ) {
					$fm_byline_box->add_meta_box( $label, $fm_context_type, apply_filters( 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_filter_post_metabox_context', 'normal' ), apply_filters( 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_filter_post_metabox_priority', 'default' ) );
				} elseif ( 'term' == $fm_context ) {
					$fm_byline_box->add_term_form( $label, $fm_context_type );
				} elseif ( 'submenu' == $fm_context ) {
					fm_register_submenu_page( 'fm_bylines_' . sanitize_title_with_dashes( $type ), apply_filters( 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_filter_metabox_submenu', 'tools.php' ), $label );
					$fm_byline_box->activate_submenu_page();
				} elseif ( 'user' == $fm_context ) {
					$fm_byline_box->add_user_form( $label );
				} elseif ( 'quickedit' == $fm_context ) {
					$fm_byline_box->add_quickedit_box( $label, $fm_context_type, function( $post_id, $data ) {
						return ! empty( $data[ 'fm_bylines_' . sanitize_title_with_dashes( $type ) ] ) ? $data[ 'fm_bylines_' . sanitize_title_with_dashes( $type ) ] : 'not set';
					} );
				}
			}
		}

		/**
		 * Only set the default for the first byline in the metabox.
		 * We overwrite the default value for only the first entry in the metabox if the logged in user is mapped to a byline.
		 */
		public function display_default_byline( $classes, $name, $field ) {
			if ( ! empty( $field->data_id ) && get_post_type( $field->data_id ) != $this->name ) {
				$pattern = '/^fm-fm_bylines_(.*)-(.*)-.*-\d$/';
				preg_match( $pattern, $field->get_element_id(), $matches );
				if ( 'byline_id' == $name && ! empty( $matches ) ) {
					$type = $matches[1];
					$index = $matches[2];
					// On new posts only, use default values.  This allows for empty bylines.
					if ( absint( $index ) === 0 && get_post_status( $field->data_id ) === 'auto-draft' ) {
						$field->default_value = ( apply_filters( "fm_bylines_{$type}_enable_user_mapping_defaults", $this->get_byline_user_mapping() ) ) ? $this->get_byline_user_mapping() : null;
					} else {
						$field->default_value = null;
					}
				}
			}
			return $classes;
		}


		/**
		 * Set the display name description
		 *
		 */
		public function set_display_name_description( $text, $post ) {
			if ( $post->post_type == $this->name ) {
				$text = __( 'Enter display name here', 'fm_bylines' );
			}
			return $text;
		}

		/**
		 * Presave alter value hook to save some additional meta info and unset unnecessary data
		 *
		 */
		public function save_byline_meta( $values, $object, $current_values ) {

			// Check the byline type as this field is not added on the byline post type page.
			if ( preg_match( '/fm_bylines_/', $object->name ) && get_post_type() != $this->name ) {
				$post_id = get_the_ID();
				foreach ( $current_values as $current_value ) {
					if ( ! empty( $current_value['byline_id'] ) ) {
						delete_post_meta( $post_id, 'fm_bylines_' . sanitize_title_with_dashes( $current_value['fm_byline_type'] ) . '_' . $current_value['byline_id'] );
					}
				}

				$order = 1;
				foreach ( $values as $i => $value ) {
					if ( empty( $value['byline_id'] ) || empty( $value['fm_byline_type'] ) ) {
						unset( $values[ $i ] );
					} else {
						// We set the boolean as the order. This might come in handy somewhere.
						update_post_meta( $post_id, 'fm_bylines_' . sanitize_title_with_dashes( $value['fm_byline_type'] ) . '_' . $value['byline_id'], $order );
						$order++;
					}
				}
			}
			return $values;
		}

		/**
		 * Delete additional byline postmeta data on delete
		 * @param int $byline_id
		 * @return void
		 */
		public function delete_byline( $byline_id ) {
			if ( get_post_type( $byline_id ) === $this->name ) {
				$associated_posts = $this->get_byline_associated_posts( $byline_id );
				foreach ( $associated_posts as $post_id => $byline_types ) {
					foreach ( $byline_types as $type ) {
						$meta_key = 'fm_bylines_' . sanitize_title_with_dashes( $type );
						$meta_data = get_post_meta( $post_id, $meta_key, true );
						foreach ( $meta_data as $i => $data ) {
							if ( ( empty( $data['byline_id'] ) || $data['byline_id'] == $byline_id ) && $data['fm_byline_type'] == $type ) {
								unset( $meta_data[ $i ] );
							}
						}
						update_post_meta( $post_id, $meta_key, $meta_data );
						delete_post_meta( $post_id, $meta_key . '_' . $byline_id );
					}
				}
			}
		}

		/**
		 * Helper functions for remove and adding filter hooks
		 */
		public function force_user_avatar() {
			add_filter( 'fm_bylines_force_user_avatar_display', '__return_true', 20 );
		}
		public function remove_force_user_avatar() {
			remove_filter( 'fm_bylines_force_user_avatar_display', '__return_true', 20 );
		}

		/**
		 * Get the featured image of a byline
		 * @param mixed. $byline_id int or post object
		 * @param mixed. $size string or array
		 * @param array. $args. get_avatar args
		 * @return string. HTML img string
		 */
		public function get_byline_avatar( $byline_id, $size, $args ) {
			$avatar = '';
			// Bylines will not get avatar by email.
			if ( $this->is_byline_object( $byline_id ) ) {
				$byline = get_post( $byline_id );

				// If you want to show default avatars for comments and users but not bylines, you can override the options with this hook
				$display_default = apply_filters( 'fm_bylines_display_avatar_default' , ( empty( $args['force_default'] ) ) ? false : $args['force_default'] );

				// Set our avatar args
				$params = array(
					'class' => 'avatar avatar-' . (string) $size . ' photo',
				);
				if ( ! empty( $args['alt'] ) ) {
					$params['alt'] = $args['alt'];
				}

				if ( $display_default ) {
					$args['force_default'] = 'y';
					$avatar = sprintf(
						"<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
						esc_attr( $args['alt'] ),
						esc_url( get_avatar_url( $byline_id, array_merge( $args, array( 'force_default' => 'y' ) ) ) ),
						esc_attr( get_avatar_url( $byline_id, array_merge( $args, array( 'force_default' => 'y', 'size' => (int) $size * 2 ) ) ) . ' 2x' ),
						esc_attr( $params['class'] . ' avatar-default' ),
						(int) $size,
						(int) $size,
						$args['extra_attr']
			        );
				} elseif ( has_post_thumbnail( $byline->ID ) ) {

					if ( is_numeric( $size ) ) {
						$size = array( $size, $size );
					}
					$avatar = get_the_post_thumbnail( $byline->ID, $size, $params );

				}
			}

			return $avatar;
		}

		/**
		 * Get the avatar associated with a byline
		 * Some trickey stuff is going on here since avatars are used for users as well on the back-end so we don't want to necessarily override this functionailty for comments or for user display on the backend
		 * Best thing to do is to use fm_get_byline_avatar() instead of trying to use the core get_avatar function when you can control when it is called. For any legacy code, this hooks should do the trick.
		 */
		public function get_avatar( $avatar, $id_or_email, $size, $default, $alt, $args ) {
			// Force display here is acting as a legacy param.  So if you force the display, it will force the display of user avatars and not bylines.  You can use this hook to control user avatar display on a granular level.
			$args['force_display'] = apply_filters( 'fm_bylines_force_user_avatar_display', ( empty( $args['force_display'] ) ) ? false : $args['force_display'] );
			// If the id returns a byline and a user, then return the user id.
			if ( ! ( $args['force_display'] ) &&  $this->is_byline_object( $id_or_email ) ) {
				return $this->get_byline_avatar( $id_or_email, $size, $alt, $args );
			} else {
				return $avatar;
			}
		}

		/**
		 * Is the current id a byline object?  Work around for when user ids and byline ids overlap.
		 * @param mixed. $byline can be an int id, an email string or a comment object.
		 */
		public function is_byline_object( $byline ) {
			// Comment avatars in core are always called with the comment object.
			// get_avatar only uses user ids in the admin area.
			if ( ( is_object( $byline ) && ! empty( $byline->post_type ) && $byline->post_type == $this->name ) ||
				( is_numeric( $byline ) && get_post_type( $byline ) === $this->name ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Get associated post_ids of a byline
		 * @param int $byline_id
		 * @return array.  An associate array with post_id as the key and an array of types as the values.
		 */
		public function get_byline_associated_posts( $byline_id ) {
			global $wpdb;
			// meta_keys are indexed so this should be speedy.
			$meta_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT A.post_id, A.meta_key
				FROM $wpdb->postmeta A
				WHERE A.meta_key LIKE %s",
				'fm_bylines_%_' . $byline_id
			) );
			$associated_posts = array();
			if ( ! empty( $meta_rows ) ) {
				foreach ( $meta_rows as $meta_row ) {
					$pattern = '/^fm_bylines_(.*)_' . $byline_id . '$/';
					preg_match( $pattern, $meta_row->meta_key, $matches );
					if ( ! empty( $matches[1] ) ) {
						$type = $matches[1];
						if ( empty( $associated_posts[ $meta_row->post_id ] ) || ! in_array( $type, $associated_posts[ $meta_row->post_id ] ) ) {
							$associated_posts[ $meta_row->post_id ][] = $type;
						}
					}
				}
			}
			return $associated_posts;
		}

		/**
		 * Get a list of byline objects for a post.
		 * @param int $post_id.
		 * @param string $type. Defaults to author.
		 * @return array()
		 */
		public function get_byline( $post_id = null, $type = 'author', $params = array() ) {
			if ( empty( $post_id ) ) {
				$post_id = get_the_ID();
			}
			$byline_ids = $this->get_byline_ids( $post_id, $type );
			$defaults = array(
				'posts_per_page' => 50,
				'post_type' => $this->name,
				'post_status' => 'publish',
				'suppress_filters' => 'false',
				'include' => $byline_ids,
			);
			$args = wp_parse_args( $params, $defaults );

			return ! empty( $byline_ids ) ? get_posts( $args ) : array();
		}

		/**
		 * Get a list of byline ids for a post.
		 * @param int $post_id.
		 * @param string $type. Defaults to author.
		 * @return array
		 */
		public function get_byline_ids( $post_id = null, $type = 'author' ) {
			if ( empty( $post_id ) ) {
				$post_id = get_the_ID();
			}
			$post_meta = get_post_meta( $post_id, 'fm_bylines_' . sanitize_title_with_dashes( $type ), true );

			if ( ! empty( $post_meta ) && is_array( $post_meta ) ) {
				return wp_list_pluck( $post_meta, 'byline_id' );
			}
			return array();
		}

		/**
		 * Get all the posts for a single byline.
		 * @param int. $byline_id
		 * @param string. $byline_type
		 * @param array. $params
		 * @return array.
		 */
		public function get_byline_posts( $byline_id, $type = 'author', $params = array() ) {
			$defaults = array(
				'post_type' => array( 'post' ),
				'post_status' => 'publish',
				'meta_key' => 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_' . $byline_id,
				'suppress_filters' => false,
			);
			$args = wp_parse_args( $params, $defaults );
			return get_posts( $args );
		}

		/**
		 * Get WP Query for the posts for a single byline.
		 * Use to create loops on single byline pages.
		 * @param int. $byline_id
		 * @param string. $byline_type
		 * @param array. $params
		 * @return object. WP_Query object.
		 */
		public function get_byline_posts_query( $byline_id = null, $type = null, $params = array() ) {
			$byline_id = ( empty( $byline_id ) ) ? get_the_ID() : $byline_id;
			$type = ( empty( $type ) ) ? $this->get_byline_type() : $type;

			if ( ! empty( $byline_id ) && ! empty( $type ) ) {
				$defaults = array(
					'post_type' => array( 'post' ),
					'post_status' => 'publish',
					'meta_key' => 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_' . $byline_id,
					'suppress_filters' => false,
				);
				$args = wp_parse_args( $params, $defaults );
				return new WP_Query( $args );
			}
			return new WP_Query();
		}

		/**
		 * Are we currently on a byline archive page.  Equivalent to is_author().
		 *
		 */
		public function is_byline( $type = 'author' ) {
			$post_type = get_post_type();

			if ( $this->name != $post_type ) {
				return false;
			}

			$byline_type = $this->get_byline_type();
			if ( $byline_type && $type == $byline_type ) {
				return true;
			}

			return false;
		}
		/**
		 * Get the current byline type when on a byline single or archive page.
		 */
		public function get_byline_type() {
			$byline_type = get_query_var( 'byline_type' );
			return empty( $byline_type ) ? false : $byline_type;
		}

		/**
		 * Get Byline meta data. Equivalent of get_the_author_meta().
		 * @param int. byline id.
		 * @return mixed
		 */
		public function get_the_byline_meta( $field, $byline_id = null ) {
			if ( empty( $field ) ) {
				return;
			}

			if ( empty( $byline_id ) ) {
				// Some core calls to get_author_meta don't pass an id.  Not sure of the best solution here functions like get_the_author_link don't have a hook available
				// We will just pull off the first byline author of a post.
				$byline_ids = $this->get_byline_ids();
				$byline_id = reset( $byline_ids );
			}
			if ( ! empty( $byline_id ) ) {
				$byline = get_post( $byline_id );

				if ( $byline->post_type == $this->name ) {
					$fields = $this->byline_meta_keys();
					if ( ! empty( $fields[ $field ] ) ) {
						$field_key = $fields[ $field ];
					} else {
						// This allows you to retrieve any meta key.
						$field_key = $field;
					}

					if ( 'fm_error_key' == $field_key ) {
						return;
					}
					if ( in_array( $field_key, array( 'ID', 'post_name', 'post_title' ) ) ) {
						return $byline->$field_key;
					}

					$byline_meta = get_post_meta( $byline_id );
					foreach ( $byline_meta as $key => $value ) {
						if ( $field_key == $key ) {
							return maybe_unserialize( $value[0] );
						} elseif ( is_serialized( $value[0] ) ) {
							$meta_values = maybe_unserialize( $value[0] );
							if ( array_key_exists( $field_key, $meta_values ) ) {
								return $meta_values[ $field_key ];
							}
						}
					}
				}
			}
			return;
		}

		/**
		 * Get the html byline url for all bylines of a given type for a single post.
		 * @param int, post_id.
		 * @param string. type.
		 * @return string.
		 */
		public function get_bylines_posts_links( $post_id = null, $type = 'author' ) {
			$byline_ids = $this->get_byline_ids( $post_id, $type );
			$urls = array();
			foreach ( $byline_ids as $byline_id ) {
				$urls[] = $this->get_byline_link( $byline_id, $type );
			}

			return $this->write_byline( $urls );
		}

		/**
		 * Get the html markup for a single byline link.
		 * Takes a single byline id.
		 * @param int. byline_id.
		 * @return string
		 */
		public function get_byline_link( $byline_id, $type = 'author' ) {
			return sprintf(
				'<a href="%1$s" rel="%2$s">%3$s</a>',
				esc_url( $this->get_byline_url( $byline_id ) ),
				esc_attr( $type ),
				esc_html( get_the_title( $byline_id ) )
			);
		}

		/**
		 * Get the byline url
		 * Takes a single byline id
		 * @param int. byline_id.
		 * @return string
		 */
		public function get_byline_url( $byline_id = null, $type = 'author' ) {
			if ( ! empty( $byline_id ) && get_post_type( $byline_id ) === $this->name ) {
				// It's possible this might be called before theme setup. So make sure we have the right slug here.
				$this->slug = sanitize_title_with_dashes( apply_filters( 'fm_bylines_filter_rewrite_slug', $this->slug ) );
				$url = get_permalink( $byline_id );
				$url = str_replace( '/' . $this->slug . '/', '/' . sanitize_title_with_dashes( $type ) . '/', $url );

				return $url;
			}
			return;
		}

		/**
		 * Get the byline id associated with a user id.
		 * @param int $user_id.
		 * @return mixed(int|boolean) byline post ID or false.
		 */
		public function get_byline_user_mapping( $user_id = null ) {
			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}

			$bylines = get_posts( array(
				'post_type' => $this->name,
				'meta_key' => 'fm_bylines_user_mapping',
				'meta_value' => absint( $user_id ),
				'suppress_filters' => false,
				'ignore_sticky_posts' => true,
				'no_found_rows' => true,
			) );

			if ( ! empty( $bylines ) ) {
				return $bylines[0]->ID;
			}
			return false;
		}

		/**
		 * Write a byline
		 * @param array. An array of bylines to write.
		 * @return string
		 */
		public function write_byline( $bylines, $before = null, $separator = null, $final_separator = null, $after = null ) {
			if ( empty( $bylines ) ) {
				return;
			}
			// Allow these to be filtered in case folks want to change the way the authors are handled.
			$before = ( empty( $before ) ) ? __( 'By', 'fm_bylines' ) : $before;
			$before = apply_filters( 'fm_bylines_write_byline_before', $before );
			$separator = ( empty( $separator ) ) ? ',' : $separator;
			$separator = apply_filters( 'fm_bylines_write_byline_separator', $separator );
			$final_separator = ( empty( $final_separator ) ) ? __( 'and', 'fm_bylines' ) : $final_separator;
			$final_separator = apply_filters( 'fm_bylines_write_byline_final_separator', $final_separator );
			$after = ( empty( $after ) ) ? '' : $after;
			$after = apply_filters( 'fm_bylines_write_byline_after', $after );
			$last = array_slice( $bylines, -1 );
			$first = implode( esc_html( $separator ) . ' ', array_slice( $bylines, 0, -1 ) );
			$both = array_filter( array_merge( array( $first ), $last ) );
			$byline = wp_kses_post( $before ) . ' ' . implode( esc_html( ' ' . $final_separator . ' ' ), $both );
			$byline .= ( empty( $after ) ) ? '' : ' ' . wp_kses_post( $after );
			return  $byline;
		}
	}
}

function FM_Bylines() {
	return FM_Bylines::instance();
}
add_action( 'after_setup_theme', 'FM_Bylines' );
