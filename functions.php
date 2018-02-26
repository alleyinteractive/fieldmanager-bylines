<?php
/**
 * Get a list of byline ids for a post
 *
 * @param int    $post_id
 * @param string $type. Defaults to author.
 * @return array
 */
function fm_get_byline_ids( $post_id = null, $type = 'author' ) {
	if ( empty( $post_id ) ) {
		$post_id = get_the_ID();
	}
	return FM_Bylines()->get_byline_ids( $post_id, $type );
}

/**
 * Get a list of byline objects for a post
 *
 * @param int    $post_id
 * @param string $type. Defaults to author.
 * @param array  $args.
 * @return string
 */
function fm_get_byline( $post_id = null, $type = 'author', $args = array() ) {
	if ( empty( $post_id ) ) {
		$post_id = get_the_ID();
	}
	return FM_Bylines()->get_byline( $post_id, $type, $args );
}

/**
 * Get the posts for a byline user
 *
 * @param int    $byline_id.  The byline guest user id.
 * @param string $type.
 * @param array  $args.
 * @return array.
 */
function fm_get_byline_posts( $byline_id, $type = 'author', $args = array() ) {
	return FM_Bylines()->get_byline_posts( $byline_id, $type, $args );
}

/**
 * Get the WP Posts Query for a byline user.
 *
 * @param int    $byline_id. Defaults to current displayed byline id.
 * @param string $type. Defaults to current display byline type.
 * @param array  $args.
 * @return mixed. WP Query object.
 */
function fm_get_byline_posts_query( $byline_id = null, $type = null, $args = array() ) {
	return FM_Bylines()->get_byline_posts_query( $byline_id, $type, $args );
}

/**
 * Get the html byline url for all bylines of a given type for a single post
 *
 * @param int, post_id
 * @param string. type
 * @return string
 */
function fm_get_bylines_posts_links( $post_id = null, $type = 'author' ) {
	if ( empty( $post_id ) ) {
		$post_id = get_the_ID();
	}
	return FM_Bylines()->get_bylines_posts_links( $post_id, $type );
}

/**
 * Get the html markup for a single byline link
 * Takes a single byline id
 *
 * @param int. byline_id
 * @return string
 */
function fm_get_byline_link( $byline_id, $type = 'author' ) {
	return FM_Bylines()->get_byline_link( $byline_id, $type );
}

/**
 * Get the byline url
 * Takes a single byline id
 *
 * @param int. byline_id
 * @return string
 */
function fm_get_byline_url( $byline_id = null, $type = 'author' ) {
	if ( ! empty( $byline_id ) ) {
		return FM_Bylines()->get_byline_url( $byline_id, $type );
	}
	return;
}

/**
 * Add in a FM_Byline meta box w/ all it's bells and whistles
 *
 * @param string $type
 * @param string. Optional label
 * @param array
 */
function fm_add_byline_meta_box( $type = 'author', $label = null, $args = array() ) {
	return FM_Bylines()->add_byline_meta_box( $type, $label, $args );
}

/**
 * Check if we are on a byline archive page.  Equivalent of is_author().
 *
 * @param string $type
 * @param int. byline id
 * @return boolean
 */
function fm_is_byline( $type = 'author', $byline_id = null ) {
	return FM_Bylines()->is_byline( $type, $byline_id );
}

/**
 * Get Byline meta data.  Equivalent of get_the_author_meta().
 *
 * @param string $field
 * @param int    $byline id
 * @return mixed
 */
function fm_get_the_byline_meta( $field, $byline_id ) {
	if ( in_array( $field, array( 'about', 'contact_info', 'names' ) ) ) {
		$meta_field = 'fm_bylines_' . $field;
	} else {
		$meta_field = $field;
	}
	return FM_Bylines()->get_the_byline_meta( $meta_field, $byline_id );
}

function fm_get_byline_avatar( $byline_id, $size, $args ) {
	return FM_Bylines()->get_byline_avatar( $byline_id, $size, $args );
}

/**
 * This function exists to attempt to create a label from a slug.
 * If the label is complex you can use the filter hook to create a label lookup or modify single slugs.
 *
 * @param string $slug
 * @param string $callback Callback function to perform on wordified slug
 * @return string
 */
function fm_bylines_wordify_slug( $slug, $callback = 'ucwords' ) {
	$label = str_replace( array( '-', '_' ), ' ', $slug );
	if ( function_exists( $callback ) ) {
		$label = $callback( $label );
	}
	return apply_filters( 'fm_bylines_slug_label', $label, $slug, $callback );
}
