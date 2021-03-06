<?php
/**
 * Setup Post Byline Type Support
 * Will add metaboxes to each post type that supports a byline type.
 *
 * @package Fieldmanager_Bylines
 */

if ( ! class_exists( 'FM_Bylines_Post' ) ) {
	/**
	 * Class FM_Bylines_Post
	 */
	class FM_Bylines_Post extends FM_Bylines {
		/**
		 * The one true object
		 *
		 * @var FM_Bylines_Post
		 */
		private static $instance;

		/**
		 * Default byline types supported by the theme
		 *
		 * @var array
		 */
		public $byline_types;

		/**
		 * Silence is golden
		 */
		private function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		/**
		 * Defer to FM for context, deprecating internal handling
		 *
		 * @param string $property Property name.
		 * @return mixed
		 */
		public function __get( $property ) {
			if ( 'context' === $property ) {
				return fm_get_context();
			}
		}

		/**
		 * Instantiate singleton
		 *
		 * @return FM_Bylines_Post
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new FM_Bylines_Post();
				self::$instance->setup();
			}
			return self::$instance;
		}

		/**
		 * Register plugin hooks
		 */
		public function setup() {
			// Support byline types by default.
			$this->byline_types = apply_filters( 'fm_bylines_filter_types', array( 'author' ) );

			add_action( 'init', array( $this, 'add_type_query_var' ) );
			add_action( 'after_setup_theme', array( $this, 'theme_setup' ), 20 );

			if ( is_admin() ) {
				if ( ! empty( $this->context[0] ) && 'post' === $this->context[0] ) {
					// Disable bylines on attachments by default.
					if ( 'attachment' !== $this->context[1] || apply_filters( 'fm_bylines_on_attachments', false ) ) {
						add_action( 'do_meta_boxes', array( $this, 'remove_meta_boxes' ) );
						add_action( "fm_{$this->context[0]}_{$this->context[1]}", array( $this, 'add_meta_boxes' ) );
					}

					// Set the column super early so other plugins can manipulate it using the same hook.
					add_filter( "manage_{$this->context[1]}_posts_columns", array( $this, 'set_posts_columns' ), 2, 2 );
					add_action( "manage_{$this->context[1]}_posts_custom_column", array( $this, 'display_byline_type_column' ), 10, 2 );
				}
			}

			add_filter( 'template_include', array( $this, 'set_byline_type_template' ) );
			add_action( 'init', array( $this, 'set_byline_rewrite_rules' ) );

			// Archive titles should display type.
			add_filter( 'wp_title_parts', array( $this, 'set_archive_title' ) );
		}

		/**
		 * Add in a byline type query var
		 */
		public function add_type_query_var() {
			global $wp;
			$wp->add_query_var( 'byline_type' );
		}

		/**
		 * Does the post type support this byline type?
		 *
		 * @param string $post_type Post type support being checked for.
		 * @param string $type Byline type.
		 * @return bool
		 */
		public function post_type_supports_byline( $post_type, $type ) {
			if ( current_theme_supports( 'bylines' ) ) {
				$byline_types = get_theme_support( 'bylines' );
				if ( in_array( $type, reset( $byline_types ), true ) ) {
					$post_type_supports = get_all_post_type_supports( $post_type );
					if ( in_array( $type, array_keys( $post_type_supports ), true ) ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * Remove Post Meta boxes for Authors
		 *
		 * @access public
		 * @return void
		 */
		public function remove_meta_boxes() {
			remove_meta_box( 'authordiv', $this->context[1], 'normal' );
		}

		/**
		 * Handle Post Meta boxes for Authors
		 *
		 * @access public
		 * @return void
		 */
		public function add_meta_boxes() {
			foreach ( $this->byline_types as $type ) {
				if ( $this->post_type_supports_byline( $this->context[1], $type ) ) {
					fm_add_byline_meta_box( $type );
				}
			}
		}

		/**
		 * Set the byline posts column label
		 *
		 * @param array $columns List table columns.
		 * @return array
		 */
		public function set_posts_columns( $columns ) {
			$new_cols = array(
				'cb'    => $columns['cb'],
				'title' => $columns['title'],
			);
			foreach ( $this->byline_types  as $type ) {
				if ( $this->post_type_supports_byline( $this->context[1], $type ) ) {
					$new_cols[ 'fm_byline_' . sanitize_title_with_dashes( $type ) ] = esc_html( fm_bylines_wordify_slug( $type ) );
				}
			}
			if ( ! empty( $columns['categories'] ) ) {
				$new_cols['categories'] = $columns['categories'];
			}
			if ( ! empty( $columns['tags'] ) ) {
				$new_cols['tags'] = $columns['tags'];
			}
			if ( ! empty( $columns['comments'] ) ) {
				$new_cols['comments'] = $columns['comments'];
			}
			$new_cols['date'] = $columns['date'];

			return $new_cols;
		}

		/**
		 * Display the byline links
		 *
		 * @param string $column_name List Table column name.
		 * @param int    $post_id Post ID.
		 * @return mixed
		 */
		public function display_byline_type_column( $column_name, $post_id ) {
			if ( preg_match( '/^fm_byline_(.*)$/', $column_name, $matches ) ) {
				$bylines      = $this->get_byline( $post_id, $matches[1] );
				$byline_links = array();
				foreach ( $bylines as $byline ) {
					$byline_links[] = '<a href="' . esc_url( get_edit_post_link( $byline->ID ) ) . '">' . esc_html( $byline->post_title ) . '</a>';
				}
				echo implode( ', ', $byline_links ); // WPCS: XSS ok.
			}
		}

		/**
		 * Register theme support for bylines
		 */
		public function theme_setup() {
			add_theme_support( 'bylines', $this->byline_types );
		}

		/**
		 * Use the author template for any fm bylines of type author
		 *
		 * @param string $template Template path to load.
		 * @return string
		 */
		public function set_byline_type_template( $template ) {
			$object = get_queried_object();

			if ( is_single() && ! empty( $object->ID ) && $this->name === $object->post_type ) {
				$type = $this->get_byline_type();
				if ( ! empty( $type ) ) {
					if ( 'author' === $type ) {
						$templates = array(
							"author-{$object->post_name}.php",
							"author-{$object->ID}.php",
							'author.php',
							"single-{$object->post_type}-{$type}.php",
							"single-{$object->post_type}.php",
							'single.php',
						);
						return get_query_template( 'author', $templates );
					} else {
						$templates = array(
							"single-{$object->post_type}-{$type}.php",
							"single-{$object->post_type}.php",
							'single.php',
						);
						return get_query_template( 'single', $templates );
					}
				}
			} elseif ( is_post_type_archive( $this->name ) ) {
				$type = $this->get_byline_type();
				if ( ! empty( $type ) ) {
					$post_type = empty( $object->post_type ) ? $this->name : $object->post_type;
					$templates = array(
						"archive-{$post_type}-{$type}.php",
						"archive-{$post_type}.php",
						'archive.php',
					);
					return get_query_template( 'archive', $templates );
				}
			}

			return $template;
		}

		/**
		 * If we are on a byline type archive, set the title correctly
		 *
		 * @param array $title_parts Page title components.
		 * @return array
		 */
		public function set_archive_title( $title_parts ) {
			if ( is_post_type_archive( $this->name ) ) {
				$object = get_queried_object();
				if ( ! empty( $object->label ) && $title_parts[0] === $object->label ) {
					$type = $this->get_byline_type();
					if ( ! empty( $type ) ) {
						$title = fm_bylines_wordify_slug( $type );
					} else {
						$title = fm_bylines_wordify_slug( apply_filters( 'fm_bylines_filter_rewrite_slug', $this->slug ) );
					}
					$title_parts[0] = apply_filters( 'fm_bylines_filter_archive_title', $title );
				}
			}
			return $title_parts;
		}

		/**
		 * Set the byline rewrite rules to use FM Bylines
		 */
		public function set_byline_rewrite_rules() {
			foreach ( $this->byline_types as $type ) {
				$type = sanitize_title_with_dashes( $type );
				if ( $type !== $this->slug ) {
					$type_rewrites = array(
						$type . '/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?' . $this->name . '=$matches[1]&byline_type=' . $type . '&feed=$matches[2]',
						$type . '/([^/]+)/(feed|rdf|rss|rss2|atom)/?$'      => 'index.php?' . $this->name . '=$matches[1]&byline_type=' . $type . '&feed=$matches[2]',
						$type . '/([^/]+)/page/?([0-9]{1,})/?$'             => 'index.php?' . $this->name . '=$matches[1]&byline_type=' . $type . '&paged=$matches[2]',
						$type . '/([^/]+)/?$'                               => 'index.php?' . $this->name . '=$matches[1]&byline_type=' . $type,
						$type . '/?$'                                       => 'index.php?post_type=' . $this->name . '&byline_type=' . $type . '',
						$type . '/feed/(feed|rdf|rss|rss2|atom)/?$'         => 'index.php?post_type=' . $this->name . '&byline_type=' . $type . '&feed=$matches[1]',
						$type . '/(feed|rdf|rss|rss2|atom)/?$'              => 'index.php?post_type=' . $this->name . '&byline_type=' . $type . '&feed=$matches[1]',
						$type . '/page/([0-9]{1,})/?$'                      => 'index.php?post_type=' . $this->name . '&byline_type=' . $type . '&paged=$matches[1]',
					);
					foreach ( $type_rewrites as $rule => $rewrite ) {
						add_rewrite_rule( $rule, $rewrite, 'top' );
					}
				}
			};
		}

		/**
		 * Sets the current admin context relevant to this plugin
		 *
		 * @deprecated
		 */
		public function set_context() {
			_deprecated_function( __METHOD__, 'fieldmaanger-bylines-0.3.0', 'fm_get_context' );
			return $this->context;
		}
	}
}

/**
 * FM Bylines Post instance
 *
 * @return FM_Bylines_Post
 */
function FM_Bylines_Post() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid, Generic.Functions.OpeningFunctionBraceKernighanRitchie.ContentAfterBrace
	return FM_Bylines_Post::instance();
}
add_action( 'after_setup_theme', 'FM_Bylines_Post' );
