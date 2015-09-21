# Fieldmanager Bylines

A developer focused plugin that allows for multiple authors on a single post and allows for multiple byline types.  This plugin is a lightweight alternative to Co-Authors plus.

This plugin requires Fieldmanager 1.0-alpha or higher.

To get this to work, simply add a filter hook in functions.php

```php
function fm_add_byline_types( $types ) {
	return array( 'author', 'illustrator', 'content-editor' );
}
add_filter( 'fm_bylines_filter_types', 'fm_add_byline_types' );
```

Then simply add support for the byline type to the post type, similar to adding in author support.

```php
add_post_type_support( $post_type, array( 'author', 'illustrator', 'content-editor' ) );
```
