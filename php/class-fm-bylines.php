<?php
if ( !class_exists( 'FM_Bylines' ) ) {

	class FM_Bylines {

		private static $instance;

		public $name = 'fm_byline';

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
			add_action( 'init', array( $this, 'setup_data_structure' ), 20 );

			//add_filter( 'the_author', array( $this, 'get_author_byline' ), 20, 1 );
			// Add custom meta boxes
			if ( is_admin() ) {
				add_action( 'fm_post_' . $this->name, array( $this, 'add_meta_boxes' ) );
				add_filter( 'enter_title_here', array( $this, 'set_display_name_description' ), 10, 2 );
				add_filter( 'fm_presave_alter_values', array( $this, 'save_byline_meta' ), 10, 3 );
			}
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
				'rewrite' => apply_filters( 'fm_bylines_filter_rewrite_slug', array( 'slug' => 'byline' ) ),
				'hierarchical' => false,
				'supports' => array( 'title', 'thumbnail' ),
				'show_in_menu' => 'users.php',
				'show_in_nav_menus' => false,
				'menu_icon' => '',
			);

			register_post_type( $this->name, $args );
		}

		/**
		 * Adds custom meta boxes for this post type.
		 *
		 * @access public
		 * @return void
		 */
		public function add_meta_boxes() {

			// Names
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

			// Contact Info
			$contact_types = array(
				'email' => __( 'E-mail', 'fm_bylines' ),
				'website' => __( 'Website', 'fm_bylines' ),
				'twitter' => __( 'Twitter', 'fm_bylines' ),
			);
			$fm_contact_children = array();
			foreach ( $contact_types as $slug => $label ) {
				$fm_contact_children[ $slug ] = new Fieldmanager_Textfield( $label, array() );
			}

			$fm_contact_children = apply_filters( 'fm_bylines_filter_contact_fields', $fm_contact_children );

			$fm_contact_info = new Fieldmanager_Group( array(
				'name' => 'fm_bylines_contact_info',
				'children' => $fm_contact_children,
			) );
			$fm_contact_info->add_meta_box( __( 'Contact Info', 'fm_bylines' ), array( $this->name ) );

			// About
			$fm_about_children = array();
			$fm_about_children[ 'bio' ] = new Fieldmanager_Textarea( __( 'Bio', 'fm_bylines' ), array() );

			$fm_about_children = apply_filters( 'fm_bylines_filter_about_fields', $fm_about_children );

			$fm_about = new Fieldmanager_Group( array(
				'name' => 'fm_bylines_about',
				'children' => $fm_about_children,
			) );
			$fm_about->add_meta_box( __( 'About', 'fm_bylines' ), array( $this->name ) );
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

				$label = empty( $label ) ? ucwords( $type ) : $label;
				$defaults = array(
					'name' => 'fm_bylines_' . sanitize_title_with_dashes( $type ),
					'limit' => 0,
					'add_more_label' => __( 'Add another', 'fm_bylines' ),
					'sortable' => true,
					'label' => __( 'Name', 'fm_bylines' ),
					'children' => array(
						'byline_id' => new Fieldmanager_Autocomplete( array(
							'datasource' => new Fieldmanager_Datasource_Post( array(
								'query_args' => array(
									'post_type' => $this->name,
									'no_found_rows' => true,
									'ignore_sticky_posts' => true,
									'post_status' => 'publish',
									'suppress_filters' => false,
								)
							) )
						) ),
						'fm_byline_type' => new Fieldmanager_Hidden( array( 'default_value' => sanitize_title_with_dashes( $type ) ) ),
					)
				);

				$fm_byline_box = new Fieldmanager_Group( wp_parse_args( $args, $defaults ) );

				if ( 'post' == $fm_context ) {
					$fm_byline_box->add_meta_box( $label, $fm_context_type );
				} elseif ( 'term' == $fm_context ) {
					$fm_byline_box->add_term_form( $label, $fm_context_type );
				} elseif ( 'submenu' == $fm_context ) {
					fm_register_submenu_page( 'fm_bylines_' . sanitize_title_with_dashes( $type ), apply_filter( 'fm_bylines_filter_metabox_submenu', 'tools.php' ), $label );
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
			if (  preg_match( '/fm_bylines_/', $object->name ) ) {
				$post_id = get_the_ID();
				foreach ( $current_values as $current_value ) {
					if ( ! empty( $current_value['byline_id'] ) ) {
						$current_type = $current_value['fm_byline_type'];
						delete_post_meta( $post_id, 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_' . $value['byline_id'] );
					}
				}
				foreach ( $values as $i => $value ) {
					$type = $value['fm_byline_type'];
					if ( empty( $value['byline_id'] ) ) {
						unset( $values[ $i ] );
					} else {
						update_post_meta( $post_id, 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_' . $value['byline_id'], true );
					}
				}
			}
			return $values;
		}

		/**
		 * Get a list of byline objects for a post
		 * @param int $post_id
		 * @param string $type. Defaults to author.
		 * @return $
		 */
		public function get_byline( $post_id = null, $type = 'author' ) {
			if ( empty( $post_id ) ) {
				$post_id = get_the_ID();
			}
			$byline_ids = $this->get_byline_ids( $post_id, $type );
			$args = apply_filters( 'fm_bylines_filter_get_byline_args', array(
				'posts_per_page'   => 50,
				'post_type' => $this->name,
				'post_status' => 'publish',
				'suppress_filters' => 'false',
				'include' => $byline_ids
			) );

			return ! empty( $byline_ids ) ? get_posts( $args ) : array();
		}

		/**
		 * Get a list of byline ids for a post
		 * @param int $post_id
		 * @param string $type. Defaults to author.
		 * @return $
		 */
		public function get_byline_ids( $post_id = null, $type = 'author' ) {
			if ( empty( $post_id ) ) {
				$post_id = get_the_ID();
			}
			$post_meta = get_post_meta( $post_id, 'fm_bylines_' . sanitize_title_with_dashes( $type ), true );

			if ( ! empty( $post_meta ) && is_array( $post_meta ) ) {
				return wp_list_pluck( $post_meta, 'byline_id' );
			}
			return;
		}

		/**
		 * Get all the posts for a single byline
		 */
		public function get_byline_posts( $byline_id, $type = 'author' ) {
			if ( ! empty( $byline_id ) ) {
				$args = apply_filters( 'fm_bylines_filter_get_byline_posts_args', array(
					'post_type' => array( 'post' ),
					'post_status' => 'publish',
					'meta_key' => 'fm_bylines_' . sanitize_title_with_dashes( $type ) . '_' . $byline_id,
					'suppress_filters' => false,
				) );

				return get_posts( $args );
			}
			return false;
		}

	}
}

function FM_Bylines() {
	return FM_Bylines::instance();
}
add_action( 'after_setup_theme', 'FM_Bylines' );