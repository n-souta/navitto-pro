<?php
/**
 * Plugin Name:       Navitto Pro
 * Plugin URI:        https://github.com/n-souta/navitto-pro
 * Description:       Pro extension for Navitto: custom preset colors, heading icons, and license activation.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  navitto
 * Author:            nsouta
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       navitto-pro
 *
 * @package Navitto_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NAVITTO_PRO_VERSION', '1.0.1' );
define( 'NAVITTO_PRO_FILE', __FILE__ );
define( 'NAVITTO_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAVITTO_PRO_URL', plugin_dir_url( __FILE__ ) );

require_once NAVITTO_PRO_DIR . 'includes/class-navitto-pro-license.php';
require_once NAVITTO_PRO_DIR . 'includes/class-navitto-pro-features.php';
require_once NAVITTO_PRO_DIR . 'includes/class-navitto-pro.php';

/**
 * 起動
 */
function navitto_pro_boot() {
	if ( ! class_exists( 'Navitto_Main', false ) ) {
		add_action(
			'admin_notices',
			static function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Navitto Pro を利用するには、Navitto プラグインを有効化してください。', 'navitto-pro' );
				echo '</p></div>';
			}
		);
		return;
	}

	Navitto_Pro::instance()->init();
}
add_action( 'plugins_loaded', 'navitto_pro_boot', 20 );
