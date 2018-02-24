<?php
/**
 * Setup Author specific hooks using fieldmanager-bylines
 */
if ( ! class_exists( 'FM_Bylines_Author' ) ) {

	class FM_Bylines_Author extends FM_Bylines_Post {

		private static $instance;

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
			add_filter( 'the_author', array( $this, 'get_the_author' ) );
			add_filter( 'is_multi_author', array( $this, 'is_multi_author' ) );

			$keys = $this->byline_meta_keys();
			foreach ( array_keys( $keys ) as $key ) {
				add_filter( "get_the_author_{$key}", array( $this, 'get_the_byline_meta' ), 10, 2 );
			}

			add_filter( 'the_author_posts_link', array( $this, 'get_author_posts_link' ) );
			add_filter( 'author_link', array( $this, 'get_author_link' ), 10, 3 );

			// Remove traditional author rewrite rules
			add_filter( 'author_rewrite_rules', array( $this, 'set_author_rewrite_rules' ) );

			add_action( 'transition_post_status', array( $this, 'early_transition_post_status' ), 1, 3 );
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
		 *
		 * @param string $display_name
		 * @return string
		 */
		public function get_the_author( $display_name ) {
			$authors      = $this->get_byline();
			$display_name = '';
			if ( ! empty( $authors ) ) {
				$authors      = wp_list_pluck( $authors, 'post_title' );
				$display_name = implode( ', ', $authors );
			}
			return $display_name;
		}

		/**
		 * Get the author link
		 *
		 * @param string                             $link
		 * @param int                                $author_id (WP user id)
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

		/**
		 * Remove default rewrite rules for authors
		 */
		public function set_author_rewrite_rules( $author_rewrite ) {
			return array();
		}
	}
}

function FM_Bylines_Author() {
	return FM_Bylines_Author::instance();
}
add_action( 'after_setup_theme', 'FM_Bylines_Author' );
