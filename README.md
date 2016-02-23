CSS Outsourcer
==============

This small WordPress plugin can be used to outsource that CSS that many themes and some plugins place in the `<head>` of your site by automatically moving it into (virtual) CSS files and enqueuing these files - so that you have less clutter in the actual HTML code.

This can be particularly useful for themes and plugins that print a lot of styles directly into your site's source code (Customizer!) - for just a few styles, you might reconsider using this plugin though. It all depends on your use-case.

Features
--------

* you only need to use one API function to disable the original action hook and "create" the CSS file with the changes (you need to provide a callback function to print the actual styles; sometimes this can even be the original function of the theme/plugin)
* in the Customizer, CSS Outsourcer will automatically disable itself as some JS functionality relies on the CSS to be printed into the `<head>`
* CSS Outsourcer can be used as a plugin or a must-use plugin
* multisite/multinetwork-compatible

Usage
-----

To outsource CSS into a separate file, you need to call the function `css_outsourcer_register()`. This function takes the following arguments:

* _string_ `$generator_filename` _(required)_ the unique filename of the file to enqueue (this must not be an actual file on the server)
* _callable_ `$generator_cb` _(required)_ the method that should be called to print the CSS in the actual stylesheet
* _callable_ `$original_hook_cb` _(optional)_ the method that originally prints the CSS (the one hooked into a specific action, see `$original_hook_name` below)
* _integer_ `$original_hook_prio` _(optional)_ the priority that the original method is hooked in
* _string_ `$original_hook_name` _(optional)_ the hook name that the original method is hooked into (by default `wp_head`)
* _string_ `$stylesheet_id` _(optional)_ the ID that the new CSS file should be enqueued with (by default it is generated from the filename)

Note that you should register outsourced CSS as early as possible - you must not register it later than `after_setup_theme`.

The following is a code example that outsources the inline style created by the Twenty Fifteen theme (for its color schemes):

```php
<?php
function outsource_twentyfifteen_css() {
    css_outsourcer_register( 'twentyfifteen-color-scheme.css', 'outsource_twentyfifteen_css_print_callback', 'twentyfifteen_color_scheme_css', null, 'wp_enqueue_scripts' );
}
add_action( 'plugins_loaded', 'outsource_twentyfifteen_css' );

// this function is almost a copy of `twentyfifteen_color_scheme_css()`
function outsource_twentyfifteen_css_print_callback() {
    if ( ! function_exists( 'twentyfifteen_get_color_scheme' ) ) {
        return;
    }

    $color_scheme_option = get_theme_mod( 'color_scheme', 'default' );

    // Don't do anything if the default color scheme is selected.
    if ( 'default' === $color_scheme_option ) {
        return;
    }

    $color_scheme = twentyfifteen_get_color_scheme();

    // Convert main and sidebar text hex color to rgba.
    $color_textcolor_rgb         = twentyfifteen_hex2rgb( $color_scheme[3] );
    $color_sidebar_textcolor_rgb = twentyfifteen_hex2rgb( $color_scheme[4] );
    $colors = array(
        'background_color'            => $color_scheme[0],
        'header_background_color'     => $color_scheme[1],
        'box_background_color'        => $color_scheme[2],
        'textcolor'                   => $color_scheme[3],
        'secondary_textcolor'         => vsprintf( 'rgba( %1$s, %2$s, %3$s, 0.7)', $color_textcolor_rgb ),
        'border_color'                => vsprintf( 'rgba( %1$s, %2$s, %3$s, 0.1)', $color_textcolor_rgb ),
        'border_focus_color'          => vsprintf( 'rgba( %1$s, %2$s, %3$s, 0.3)', $color_textcolor_rgb ),
        'sidebar_textcolor'           => $color_scheme[4],
        'sidebar_border_color'        => vsprintf( 'rgba( %1$s, %2$s, %3$s, 0.1)', $color_sidebar_textcolor_rgb ),
        'sidebar_border_focus_color'  => vsprintf( 'rgba( %1$s, %2$s, %3$s, 0.3)', $color_sidebar_textcolor_rgb ),
        'secondary_sidebar_textcolor' => vsprintf( 'rgba( %1$s, %2$s, %3$s, 0.7)', $color_sidebar_textcolor_rgb ),
        'meta_box_background_color'   => $color_scheme[5],
    );

    echo twentyfifteen_get_color_scheme_css( $colors );
}
```

Known Issues
------------

If your site looks messed up after outsourcing the CSS, it's probably because you need to flush the rewrite rules. An easy way to do so is to go to "Settings > Permalinks" and just hit the "Save" button (you don't need to change anything).

Contributions and Bugs
----------------------

If you have ideas on how to improve the plugin or if you discover a bug, I would appreciate if you shared them with me, right here on Github. In either case, please open a new issue [here](https://github.com/felixarntz/css-outsourcer/issues/new)!
