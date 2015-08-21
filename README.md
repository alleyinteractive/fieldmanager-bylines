# Fieldmanager Bylines

A developer focused plugin that allows for multiple authors on a single post and allows for multiple byline types.  This should serve as a lightweight alternative to Co-Authors plus which bloats its selft tring to maintain a user-to-author relationship and constraining itself by using a single author taxonomy.  This plugin operates under the assumption that users will not be post authors and removes any association of user logins with authors.

This plugin requires Fieldmanager 1.0-alpha or higher.

## TODO

### Functions that need to be addressed

`get_the_author_link()` & `the_author_link()` currently have no hooks which would allow us to override their functionality.  It's possible we can override the `global $authordata` variable and force these functions to spit back just the first byline but this feels super dirty for something that isn't even working 100% correctly

`get_author_posts_url()` has a hook available to it, but I'm undecided on how to address returning 2 urls.  The function is called in cannonical redirects and link feeds, that we may be able to change it's return to mixed and handle it differently if it's an array in these functions.

`wp_list_authors()` Just may have to stay broken.  It's not used in core so it's a theme specific called function.

`get_author_feed_link()` All feed fixes need to be addressed which is not done yet.  It should be something that is do-able as we can just use the post feed functions in place of it and we'd just need to figure out the logic for determining what author is being queried.

### Admin Area

Currently the post counts are inaccurate and it would be nice to add columns in that list the byline types associated with a theme with corresponding counts.  This might require a global variable but I haven't had a chance to dip into it yet.