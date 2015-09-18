# Fieldmanager Bylines

A developer focused plugin that allows for multiple authors on a single post and allows for multiple byline types.  This should serve as a lightweight alternative to Co-Authors plus which bloats its self tring to maintain a user-to-author relationship and constraining itself by using a single author taxonomy.  This plugin operates under the assumption that users will not be post authors and removes any association of user logins with authors.

This plugin requires Fieldmanager 1.0-alpha or higher.

To get this to work, simply add a filter hook in functions.php
```/**
 * Add in the byline types that are used in this theme
 */
function fm_add_byline_types( $types ) {
	return array( 'author', 'illustrator', 'content-editor' );
}
add_filter( 'fm_bylines_filter_types', 'fm_add_byline_types' );```

Then simply add the byline type to the post support similar to adding in author support.

```add_post_type_support( $post_type, array( 'author', 'illustrator', 'content-editor' ) )```

This will add you meta boxes to your specified post type.

## TODO

### Functions that need to be addressed

`get_the_author_link()` & `the_author_link()` currently have no hooks which would allow us to override their functionality.  It's possible we can override the `global $authordata` variable and force these functions to spit back just the first byline but this feels super dirty for something that isn't even working 100% correctly

`get_author_posts_url()` has a hook available to it, but I'm undecided on how to address returning 2 urls.  The function is called in cannonical redirects and link feeds, that we may be able to change it's return to mixed and handle it differently if it's an array in these functions.

`wp_list_authors()` Just may have to stay broken.  It's not used in core so it's a theme specific called function.

`get_author_feed_link()` All feed fixes need to be addressed which is not done yet.  It should be something that is do-able as we can just use the post feed functions in place of it and we'd just need to figure out the logic for determining what author is being queried.

`get_avatar()` Works well for legacy functionality but ideally when calling a byline avatar, you should use the fm_get_byline_avatar()

### Admin Area

Currently the post counts are inaccurate.

### Metabox

`fm_add_byline_meta_box` has not been tested w/ anything other than posts for Fieldmanager and needs work there.

It may be nice to add in a custom byline skin for the FM box that could bring in info like featured image or other useful metadata

### Rewrites

Along with feeds needing to be addressed, currently all bylines just operate off the `byline` slug. This of course can be overridden with the `fm_bylines_filter_rewrite_slug` filter hook, but there is no logic currentlty in place for rewrites for individual byline types but can be implemented with byline types now being supported by the theme.