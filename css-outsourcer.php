<?php
/**
 * Plugin Name: CSS Outsourcer
 * Plugin URI:  http://leaves-and-love.net
 * Description: Outsources CSS that is outputted in the <head> into external files for less clutter on your site.
 * Version:     0.1.0
 * Author:      Felix Arntz
 * Author URI:  http://leaves-and-love.net
 * License: GNU General Public License v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: css-outsourcer
 */

class CSS_Outsourcer {
	const QUERY_VAR = 'css_outsourcer_file';

	private static $initialized = false;
	private static $instances = array();

	private $generator_filename;
	private $generator_cb;
	private $original_hook_cb;
	private $original_hook_prio;
	private $original_hook_name;
	private $stylesheet_id;

	public static function instance( $generator_filename, $generator_cb = null, $original_hook_cb = null, $original_hook_prio = null, $original_hook_name = 'wp_head', $stylesheet_id = null ) {
		if ( ! isset( self::$instances[ $generator_filename ] ) ) {
			if ( ! $generator_cb ) {
				return new WP_Error( 'missing_generator_callback', __( 'Missing generator callback', 'css-outsourcer' ) );
			}

			self::$instances[ $generator_filename ] = new self( $generator_filename, $generator_cb, $original_hook_cb, $original_hook_prio, $original_hook_name, $stylesheet_id );
		}

		return self::$instances[ $generator_filename ];
	}

	private function __construct( $generator_filename, $generator_cb, $original_hook_cb = null, $original_hook_prio = null, $original_hook_name = 'wp_head', $stylesheet_id = null ) {
		$this->generator_filename = $generator_filename;
		$this->generator_cb = $generator_cb;
		$this->original_hook_cb = $original_hook_cb;
		$this->original_hook_prio = $original_hook_prio;
		$this->original_hook_name = $original_hook_name;
		$this->stylesheet_id = $stylesheet_id;

		add_action( 'wp_loaded', array( $this, 'outsource_css' ), 99 );
	}

	public function outsource_css() {
		if ( is_customize_preview() ) {
			// in Customizer, keep things as they are as some plugins / themes rely on it
			return;
		}

		if ( $this->original_hook_cb ) {
			$priority = $this->original_hook_prio ? intval( $this->original_hook_prio ) : 10;

			remove_action( $this->original_hook_name, $this->original_hook_cb, $priority );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_outsourced_css' ) );
	}

	public function enqueue_outsourced_css() {
		global $wp_rewrite;

		$id = $this->stylesheet_id;
		if ( ! $id ) {
			$id = explode( '/', $this->generator_filename );
			$id = $id[ count( $id ) - 1 ];
			$id = explode( '.', $id )[0];
		}

		$url = home_url( ( $wp_rewrite->using_index_permalinks() ? 'index.php/' : '/' ) . $this->generator_filename );

		wp_enqueue_style( $id, $url );
	}

	public function show_outsourced_css() {
		$filename = explode( '/', $this->generator_filename );
		$filename = $filename[ count( $filename ) - 1 ];

		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'Content-Type: text/css' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );

		$max_age = DAY_IN_SECONDS;

		header( 'Cache-Control: public,max-age=' . $max_age . ',s-maxage=' . $max_age );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + $max_age ) );

		$server_protocol = ( isset( $_SERVER['SERVER_PROTOCOL'] ) && '' !== $_SERVER['SERVER_PROTOCOL'] ) ? sanitize_text_field( $_SERVER['SERVER_PROTOCOL'] ) : 'HTTP/1.1';

		if ( is_callable( $this->generator_cb ) ) {
			header( $server_protocol . ' 200 OK', true, 200 );
		} else {
			header( $server_protocol . ' 404 Not Found', true, 404 );
			die();
		}

		call_user_func( $this->generator_cb );

		remove_all_actions( 'wp_footer' );
		die();
	}

	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		load_plugin_textdomain( 'css-outsourcer' );

		add_action( 'after_setup_theme', array( __CLASS__, 'maybe_reduce_query_load' ), 99, 0 );
		add_action( 'init', array( __CLASS__, 'add_query_var' ), 1, 0 );
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 1, 0 );
		add_action( 'pre_get_posts', array( __CLASS__, 'maybe_show_outsourced_css' ), 1, 1 );

		add_filter( 'redirect_canonical', array( __CLASS__, 'fix_canonical' ), 10, 1 );
	}

	public static function maybe_reduce_query_load() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		foreach ( self::$instances as $filename => $instance ) {
			if ( false !== stripos( $_SERVER['REQUEST_URI'], $filename ) ) {
				remove_all_actions( 'widgets_init' );
				break;
			}
		}
	}

	public static function add_query_var() {
		global $wp;

		if ( ! is_object( $wp ) ) {
			return;
		}

		$wp->add_query_var( self::QUERY_VAR );
	}

	public static function add_rewrite_rules() {
		foreach ( self::$instances as $filename => $instance ) {
			add_rewrite_rule( str_replace( array( '.', '/' ), array( '\.', '\/' ), $filename ) . '$', 'index.php?' . self::QUERY_VAR . '=' . $filename, 'top' );
		}
	}

	public static function maybe_show_outsourced_css( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$file = get_query_var( self::QUERY_VAR );
		if ( empty( $file ) || ! isset( self::$instances[ $file ] ) ) {
			return;
		}

		self::$instances[ $file ]->show_outsourced_css();
	}

	public static function fix_canonical( $redirect ) {
		$file = get_query_var( self::QUERY_VAR );
		if ( empty( $file ) ) {
			return $redirect;
		}

		return false;
	}

	public static function _flush_rewrite_rules_late( $network_wide = false ) {
		if ( $network_wide ) {
			add_action( 'shutdown', array( __CLASS__, '_flush_network_rewrite_rules' ) );
		} else {
			add_action( 'shutdown', 'flush_rewrite_rules' );
		}
	}

	public static function _flush_network_rewrite_rules() {
		global $_wp_switched_stack;

		$sites = wp_get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			flush_rewrite_rules();
		}

		if ( ! empty( $_wp_switched_stack ) ) {
			$_wp_switched_stack = array( $_wp_switched_stack[0] );
			restore_current_blog();
		}
	}
}

function css_outsourcer_register( $generator_filename, $generator_cb, $original_hook_cb = null, $original_hook_prio = null, $original_hook_name = 'wp_head', $stylesheet_id = null ) {
	if ( did_action( 'after_setup_theme' ) ) {
		return new WP_Error( 'outsourced_too_late', __( 'Outsourced CSS files must not be registered later than the "after_setup_theme" hook.', 'css-outsourcer' ) );
	}

	return CSS_Outsourcer::instance( $generator_filename, $generator_cb, $original_hook_cb, $original_hook_prio, $original_hook_name, $stylesheet_id );
}

add_action( 'plugins_loaded', array( 'CSS_Outsourcer', 'init' ) );

register_activation_hook( __FILE__, array( 'CSS_Outsourcer', '_flush_rewrite_rules_late' ) );
register_deactivation_hook( __FILE__, array( 'CSS_Outsourcer', '_flush_rewrite_rules_late' ) );
