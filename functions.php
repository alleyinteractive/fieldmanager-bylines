<?php
/**
 * Get a list of byline ids for a post
 * @param int $post_id
 * @param string $type. Defaults to author.
 * @return array
 */
function fm_get_byline_ids( $post_id = null, $type = 'author' ) {
	$fm_bylines = FM_Bylines();
	return $fm_bylines->get_byline_ids( $post_id, $type );
}

/**
 * Get a list of byline objects for a post
 * @param int $post_id
 * @param string $type. Defaults to author.
 * @return string
 */
function fm_get_byline( $post_id = null, $type = 'author' ) {
	$fm_bylines = FM_Bylines();
	return $fm_bylines->get_byline( $post_id, $type );
}

/**
 * Get the posts for a byline user
 * @param int $byline_id.  The byline guest user id.
 * @param string $type.
 * @return mixed.  array of posts or false
 */
function fm_get_byline_posts( $byline_id, $type = 'author' ) {
	if ( ! empty( $byline_id ) ) {
		$fm_bylines = FM_Bylines();
		return $fm_bylines->get_byline_posts( $byline_id, $type );
	}
	return false;
}

/**
 * Add in a FM_Byline meta box w/ all it's bells and whistles
 * @param string $type
 * @param string. Optional label
 * @param array
 */
function fm_add_byline_meta_box( $type = 'author', $label = null, $args = array() ) {
	$fm_bylines = FM_Bylines();
	return $fm_bylines->add_byline_meta_box( $type, $label, $args );
}

/**
 * Check if we are on a byline archive page.  Equivalent of is_author().
 * @param string $type
 * @param int. byline id
 * @return boolean
 */
function fm_is_byline( $type = 'author', $byline_id = null ) {
	$fm_bylines = FM_Bylines();
	return $fm_bylines->is_byline( $type, $byline_id );
}

/**
 * Get Byline meta data.  Equivalent of get_the_author_meta().
 * @param string $field
 * @param int. byline id
 * @return mixed
 */
function fm_get_the_byline_meta( $field, $byline_id ) {
	$fm_bylines = FM_Bylines();
	if ( in_array( $field, array( 'about', 'contact_info', 'names' ) ) ) {
		$meta_field = 'fm_bylines_' . $field;
	} else {
		$meta_field = $field;
	}
	return $fm_bylines->get_the_byline_meta( $meta_field, $byline_id );
}