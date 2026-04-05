<?php
/**
 * Navitto Pro ブートストラップ
 *
 * @package Navitto_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Navitto_Pro {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		Navitto_Pro_License::instance()->init();
		Navitto_Pro_Features::instance()->init();
	}
}
