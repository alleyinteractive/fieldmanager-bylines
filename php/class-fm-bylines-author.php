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
			if ( is_admin() ) {
				$this->context = fm_get_context();
				if ( 'post' === $this->context[0] ) {
					add_action( 'do_meta_boxes', array( $this, 'remove_meta_boxes' ) );
					add_action( "fm_{$this->context[0]}_{$this->context[1]}", array( $this, 'add_meta_boxes' ) );
				}
				add_filter( "manage_{$this->context[1]}_posts_columns", array( $this, 'set_posts_columns' ) );
				add_action( "manage_{$this->context[1]}_posts_custom_column", array( $this, 'display_author_column' ), 10, 2 );

			}
			add_filter('the_author', array( $this, 'get_the_author' ) );
			add_filter( 'is_multi_author', array( $this, 'is_multi_author' ) );

			add_action( 'transition_post_status', array( $this, 'early_transition_post_status' ), 1, 3 );
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
		 */
		public function get_author_link( $link, $author_id, $author_nicename ) {
			return $link;
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