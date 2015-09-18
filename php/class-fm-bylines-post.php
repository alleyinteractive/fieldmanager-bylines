<?php
/**
 * Setup Post Byline Type Support
 * Will add metaboxes to each post type that supports a byline type.
 */
if ( ! class_exists( 'FM_Bylines_Post' ) ) {

	class FM_Bylines_Post extends FM_Bylines {
		private static $instance;

		// Default byline types supported by the theme
		public $byline_types;

		private $context;

		private function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new FM_Bylines_Post();
				self::$instance->setup();
			}
			return self::$instance;
		}

		public function setup() {
			$this->context = fm_get_context();

			// Support byline types by default
			$this->byline_types = apply_filters( 'fm_bylines_filter_types', array( 'author' ) );

			add_action( 'after_setup_theme', array( $this, 'theme_setup' ), 20 );

			if ( is_admin() ) {
				if ( 'post' === $this->context[0] ) {
					add_action( 'do_meta_boxes', array( $this, 'remove_meta_boxes' ) );
					add_action( "fm_{$this->context[0]}_{$this->context[1]}", array( $this, 'add_meta_boxes' ) );
				}
				// Set the column super early so other plugins can manipulate it using the same hook
				add_filter( "manage_{$this->context[1]}_posts_columns", array( $this, 'set_posts_columns' ), 2, 2 );
				add_action( "manage_{$this->context[1]}_posts_custom_column", array( $this, 'display_byline_type_column' ), 10, 2 );

			}

			add_filter( 'template_include', array( $this, 'set_byline_type_template') );
			add_filter( 'author_rewrite_rules', array( $this, 'set_author_rewrite_rules') );

		}

		/**
		 * Does the post type support this byline type?
		 */
		public function post_type_supports_byline( $post_type, $type ) {
			if ( current_theme_supports( 'bylines' ) ) {
				$byline_types = get_theme_support( 'bylines' );
				if ( in_array( $type, reset( $byline_types ) ) ) {
					$post_type_supports = get_all_post_type_supports( $post_type );
					if ( in_array( $type, array_keys( $post_type_supports ) ) ) {
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
			foreach ( $this->byline_types  as $type ) {
				if ( $this->post_type_supports_byline( $this->context[1], $type ) ) {
					fm_add_byline_meta_box( $type );
				}
			}
		}

		/**
		 * Set the author posts column label
		 */
		public function set_posts_columns( $columns ) {
			$new_cols = array(
				'cb' => $columns['cb'],
				'title' => $columns['title'],
			);
			foreach ( $this->byline_types  as $type ) {
				if ( $this->post_type_supports_byline( $this->context[1], $type ) ) {
					$new_cols[ 'fm_byline_' . sanitize_title_with_dashes( $type ) ] = esc_html( fm_bylines_wordify_slug( $type ) );
				}
			}
			$new_cols['categories'] = $columns['categories'];
			$new_cols['tags'] = $columns['tags'];
			$new_cols['comments'] = $columns['comments'];
			$new_cols['date'] = $columns['date'];

			return $new_cols;
		}

		/**
		 * Display the byline links
		 * @param string $column_name
		 * @param int $post_id
		 * @return void
		 */
		public function display_byline_type_column( $column_name, $post_id ) {
			if ( preg_match( '/^fm_byline_(.*)$/', $column_name, $matches ) ) {
				$bylines = $this->get_byline( $post_id, $matches[1] );
				$byline_links = array();
				foreach ( $bylines as $byline ) {
					$byline_links[] = '<a href="' . esc_url( get_edit_post_link( $byline->ID ) ) . '">' . esc_html( $byline->post_title ) . '</a>';
				}
				echo implode( ', ',  $byline_links );
			}
		}

		public function theme_setup() {
			add_theme_support( 'bylines', $this->byline_types );
		}

		/**
		 * Use the author template for any fm bylines of type author
		 */
		public function set_byline_type_template( $template ) {
			$object = get_queried_object();

			if ( ! empty( $object->ID ) && $this->is_byline( 'author' ) ) {
				$templates = array();
				$templates[] = "author-{$object->post_name}.php";
				$templates[] = "author-{$object->ID}.php";
				$templates[] = "author.php";
				$templates[] = "single-byline.php";
				$templates[] = "single-{$object->post_type}.php";
				$templates[] = "single.php";

				return get_query_template( 'author', $templates );
			}

			return $template;
		}

		/**
		 * Set the author rewrite rules to use FM Bylines
		 */
		public function set_author_rewrite_rules( $author_rewrite ) {
			$author_rewrite = array(
				'author/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?' . $this->name . '=$matches[1]&feed=$matches[2]',
				'author/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?' . $this->name . '=$matches[1]&feed=$matches[2]',
				'author/([^/]+)/page/?([0-9]{1,})/?$' => 'index.php?' . $this->name . '=$matches[1]&paged=$matches[2]',
				'author/([^/]+)/?$' => 'index.php?' . $this->name . '=$matches[1]',
				'author/?$' => 'index.php?post_type=' . $this->name . '',
				'author/feed/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?post_type=' . $this->name . '&feed=$matches[1]',
				'author/(feed|rdf|rss|rss2|atom)/?$' => 'index.php?post_type=' . $this->name . '&feed=$matches[1]	other',
				'author/page/([0-9]{1,})/?$' => 'index.php?post_type=' . $this->name . '&paged=$matches[1]',
			);

			return $author_rewrite;
		}
	}
}

function FM_Bylines_Post() {
	return FM_Bylines_Post::instance();
}
add_action( 'after_setup_theme', 'FM_Bylines_Post' );