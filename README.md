# Fieldmanager Bylines

A WordPress plugin that allows for multiple authors on a single post and allows for multiple byline types.  This plugin is a lightweight alternative to Co-Authors plus and requires [Fieldmanager](http://fieldmanager.org).

## Custom Byline Types

With this plugin, you can offer more than one type of byline. To do so:

1. Filter `fm_bylines_filter_types` to add your custom byline types

    ```php
    add_filter( 'fm_bylines_filter_types', function( $types ) {
    	return array_merge( $types, array( 'illustrator', 'content-editor' ) );
    } );
    ```
2. Add support for the byline type to the post type, similar to adding in author support

    ```php
    add_post_type_support( $post_type, array( 'author', 'illustrator', 'content-editor' ) );
    ```
