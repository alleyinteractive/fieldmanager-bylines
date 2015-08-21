<?php
/**
 * Setup Post Authors using fieldmanager-bylines
 *
 */
if ( ! class_exists( 'FM_Bylines_Author' ) ) {

	class FM_Bylines_Author extends FM_Bylines {

		private static $instance;

		private $context;

		private function __construct() {
			/* Don't do anything, needs to be initialized via instance() method */
		}

		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new FM_Bylines_Author();
				self::$instance->setup();
			}
			return self::$instance;
		}

		public function setup() {
			// Set the global author data
			// add_action( 'the_post', array( $this, 'set_global_authordata' ) );

			if ( is_admin() ) {
				$this->context = fm_get_context();
				if ( 'post' === $this->context[0] ) {
					add_action( 'do_meta_boxes', array( $this, 'remove_meta_boxes' ) );
					add_action( "fm_{$this->context[0]}_{$this->context[1]}", array( $this, 'add_meta_boxes' ) );
				}
				// Set the column super early so other plugins can manipulate it using the same hook
				add_filter( "manage_{$this->context[1]}_posts_columns", array( $this, 'set_posts_columns' ), 2, 2 );
				add_action( "manage_{$this->context[1]}_posts_custom_column", array( $this, 'display_author_column' ), 10, 2 );

			}
			add_filter('the_author', array( $this, 'get_the_author' ) );
			add_filter( 'is_multi_author', array( $this, 'is_multi_author' ) );

			$keys = $this->byline_meta_keys();
			foreach ( array_keys( $keys ) as $key ) {
				add_filter( "get_the_author_{$key}", array( $this, 'get_the_byline_meta' ), 10, 2 );
			}

			add_filter( 'the_author_posts_link', array( $this, 'get_author_posts_link' ) );
			add_filter( 'author_link', array( $this, 'get_author_link' ), 10, 3 );

			add_filter( 'author_rewrite_rules', array( $this, 'set_author_rewrite_rules') );
			add_filter( 'template_include', array( $this, 'set_author_template') );

			add_action( 'transition_post_status', array( $this, 'early_transition_post_status' ), 1, 3 );
		}

		/**
		 * There are a number of functions that are not hookable
		 * Set the author data object to just use the first author byline if there is more than one for the un-hookable functions
		 * This feels a little dirty.  Would love thoughts on if this even needs to be done.
		 */
		public function set_global_authordata( $post ) {
			if ( is_array( $post ) ) {
				$post = reset( $post );
			}
			global $authordata;

			$byline_ids = $this->get_byline_ids( $post->ID, 'author' );
			$author_data_object = new stdClass();

			if ( ! empty( $byline_ids ) ) {
				$byline_id = reset( $byline_ids );
				$byline = get_post( $byline_id );
				$author_data_object->ID = $byline_id;
				$author_data_object->user_nicename = $byline->post_name;
				$author_data_object->user_displayname = $byline->post_title;
				$author_data_object->user_email = $this->get_the_byline_meta( 'email', $byline_id );
			} else {
				$byline_id = null;
			}
			$authordata->data = $author_data_object;
			$authordata->ID = $byline_id;
			$authordata->caps = array();
			$authordata->cap_key = array();
			$authordata->allcaps = array();
			$authordata->roles = array();
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

		/**
		 * Use the author template for any fm bylines of type author
		 */
		public function set_author_template( $template ) {
			$object = get_queried_object();

			if ( ! empty( $object->ID ) && fm_is_byline( 'author' ) ) {
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
		 * Handle Post Meta boxes for Authors
		 *
		 * @access public
		 * @return void
		 */
		public function add_meta_boxes() {
			if ( post_type_supports( $this->context[1], 'author' ) ) {
				fm_add_byline_meta_box( 'author' );
			}
		}

		/**
		 * Set the author posts column label
		 */
		public function set_posts_columns( $columns ) {
			if ( post_type_supports( $this->context[1], 'author' ) ) {
				return array(
					'cb' => $columns['cb'],
					'title' => $columns['title'],
					'fm_byline_author' => __( 'Authors', 'fm_bylines' ),
					'categories' => $columns['categories'],
					'tags' => $columns['tags'],
					'comments' => $columns['comments'],
					'date' => $columns['date'],
				);
			}
			return $columns;
		}

		/**
		 * Display the byline links
		 * @param string $column_name
		 * @param int $post_id
		 * @return void
		 */
		public function display_author_column( $column_name, $post_id ) {
			if ( 'fm_byline_author' === $column_name ) {
				$authors = $this->get_byline( $post_id );
				$author_links = array();
				foreach ( $authors as $author ) {
					$author_links[] = '<a href="' . esc_url( get_edit_post_link( $author->ID ) ) . '">' . esc_html( $author->post_title ) . '</a>';
				}
				echo implode( ', ',  $author_links );
			}
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
		 * [filter_is_multi_author description]
		 *
		 * @param boolean $is_multi_author
		 * @return boolean
		 */
		function is_multi_author( $is_multi_author ) {
			// Assume we need this plugin precisely because there are multiple authors.
			return true;
		}


		/**
		 * Get the FM Author
		 * @param string $display_name
		 * @return string
		 */
		public function get_the_author( $display_name ) {
			$authors = $this->get_byline();
			$display_name = '';
			if ( ! empty( $authors ) ) {
				$authors = wp_list_pluck( $authors, 'post_title'  );
				$display_name = implode( ', ', $authors );
			}
			return $display_name;
		}

		/**
		 * Get the author link
		 *
		 * @param string $link
		 * @param int $author_id (WP user id)
		 * @param $author_nicename (WP user nicename)
		 * @return string
		 */
		public function get_author_link( $link, $author_id, $author_nicename ) {
			$byline_ids = $this->get_byline_ids();
			return $link;
		}

		/**
		 * Get the author posts link html
		 *
		 * @param string $link
		 * @return string
		 */
		public function get_author_posts_link( $link ) {
			return $this->get_bylines_posts_links();
		}

		/**
		 * Fires before the default priority when a post transitions to a new status.
		 *
		 * @param string $new_status
		 * @param string $old_status
		 * @param object $post
		 * @return void
		 */
		public function early_transition_post_status( $new_status, $old_status, $post ) {
			// Don't bother clearing the multi-author transient when a post status changes.
			remove_action( 'transition_post_status', '__clear_multi_author_cache' );
		}
	}
}

function FM_Bylines_Author() {
	return FM_Bylines_Author::instance();
}
add_action( 'after_setup_theme', 'FM_Bylines_Author' );