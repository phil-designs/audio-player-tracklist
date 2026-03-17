<?php
/**
 * Plugin Name: Audio Player with Tracklist
 * Plugin URI:  http://www.phildesigns.com
 * Description: An audio player with tracklist functionality, built via a custom post type and inserted via shortcode. Requires Advanced Custom Fields (ACF).
 * Version:     1.0.0
 * Author:      phil.designs | Phillip De Vita
 * Author URI:  http://www.phildesigns.com
 * License:     GPL-2.0+
 * Text Domain: audio-player-tracklist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAP_VERSION',     '1.0.0' );
define( 'CAP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CAP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Bail early if ACF is not active.
add_action( 'admin_notices', function () {
	if ( ! class_exists( 'ACF' ) ) {
		echo '<div class="notice notice-error"><p><strong>Circular Audio Player</strong> requires the <strong>Advanced Custom Fields</strong> plugin to be installed and activated.</p></div>';
	}
} );

require_once CAP_PLUGIN_DIR . 'includes/class-cpt.php';
require_once CAP_PLUGIN_DIR . 'includes/class-acf-fields.php';
require_once CAP_PLUGIN_DIR . 'includes/class-shortcode.php';

new CAP_CPT();
new CAP_ACF_Fields();
new CAP_Shortcode();
