<?php
/**
 * Pro 機能（無料版の改変なしで動作）
 *
 * @package Navitto_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Navitto_Pro_Features {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_integrate' ), 25 );
		add_action( 'customize_register', array( $this, 'register_customizer' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_metabox_extras' ), 20 );
		add_action( 'save_post', array( $this, 'save_h2_icons_post' ), 15, 2 );
	}

	/**
	 * カスタム色を有効にしているか（Navitto の theme_mod とは別キー）
	 */
	private function is_custom_color_mode_active() {
		return Navitto_Pro_License::is_valid() && (bool) get_theme_mod( 'navitto_pro_custom_enabled', false );
	}

	/**
	 * フロント: FA / アイコン JS の依存差し込み、navittoData の追記、カスタム色 CSS
	 */
	public function frontend_integrate() {
		if ( ! Navitto_Pro_License::is_valid() ) {
			return;
		}
		if ( ! is_singular( array( 'post', 'page' ) ) ) {
			return;
		}
		if ( ! class_exists( 'Navitto_Main', false ) ) {
			return;
		}

		$post_id = get_the_ID();
		$main    = Navitto_Main::get_instance();
		if ( ! $main->should_display( $post_id ) ) {
			return;
		}

		wp_register_style(
			'navitto-pro-fa',
			NAVITTO_PRO_URL . 'assets/lib/fontawesome/all-nv.min.css',
			array(),
			NAVITTO_PRO_VERSION
		);
		wp_register_script(
			'navitto-pro-icons',
			NAVITTO_PRO_URL . 'assets/js/navitto-icons.js',
			array(),
			NAVITTO_PRO_VERSION,
			true
		);

		wp_enqueue_style( 'navitto-pro-fa' );
		wp_enqueue_script( 'navitto-pro-icons' );

		global $wp_styles, $wp_scripts;
		if ( isset( $wp_styles->registered['navitto-frontend'] ) ) {
			$d = $wp_styles->registered['navitto-frontend']->deps;
			if ( ! in_array( 'navitto-pro-fa', $d, true ) ) {
				$wp_styles->registered['navitto-frontend']->deps[] = 'navitto-pro-fa';
			}
		}
		if ( isset( $wp_scripts->registered['navitto-frontend'] ) ) {
			$d = $wp_scripts->registered['navitto-frontend']->deps;
			if ( ! in_array( 'navitto-pro-icons', $d, true ) ) {
				$wp_scripts->registered['navitto-frontend']->deps[] = 'navitto-pro-icons';
			}
		}

		$patch = array();

		$display_mode = get_post_meta( $post_id, '_navitto_display_mode', true );
		if ( '' === $display_mode ) {
			$display_mode = 'show_all';
		}
		if ( 'auto' === $display_mode ) {
			$display_mode = 'show_all';
		}
		if ( 'select' === $display_mode ) {
			$raw = get_post_meta( $post_id, '_navitto_h2_icons', true );
			if ( is_array( $raw ) ) {
				$js_icons = array();
				foreach ( $raw as $k => $v ) {
					if ( is_string( $v ) && '' !== $v ) {
						$js_icons[ strval( $k ) ] = $v;
					}
				}
				if ( ! empty( $js_icons ) ) {
					$patch['h2Icons'] = $js_icons;
				}
			}
		}

		if ( $this->is_custom_color_mode_active() ) {
			$patch['preset'] = 'custom';
		}

		if ( ! empty( $patch ) ) {
			wp_add_inline_script(
				'navitto-frontend',
				'Object.assign( navittoData, ' . wp_json_encode( $patch ) . ' );',
				'before'
			);
		}

		if ( $this->is_custom_color_mode_active() ) {
			$text_color      = get_theme_mod( 'navitto_pro_custom_color_text', '#333333' ) ?: '#333333';
			$bg_color        = get_theme_mod( 'navitto_pro_custom_color_bg', '#ffffff' ) ?: '#ffffff';
			$underline_color = get_theme_mod( 'navitto_pro_custom_color_underline', '#0073aa' ) ?: '#0073aa';

			$css  = '.navitto-nav.cp-preset-custom{';
			$css .= '--navitto-bg:' . esc_attr( $bg_color ) . ';';
			$css .= '--navitto-text:' . esc_attr( $text_color ) . ';';
			$css .= '--navitto-text-hover:' . esc_attr( $underline_color ) . ';';
			$css .= '--navitto-active-text:' . esc_attr( $underline_color ) . ';';
			$css .= '--navitto-active-bg:transparent;';
			$css .= '--navitto-border:' . esc_attr( $underline_color ) . ';';
			$css .= '}';

			wp_add_inline_style( 'navitto-frontend', $css );
		}
	}

	/**
	 * カスタマイザー: Navitto 本体とは別セクション・別 theme_mod
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer.
	 */
	public function register_customizer( $wp_customize ) {
		if ( ! Navitto_Pro_License::is_valid() ) {
			return;
		}

		$wp_customize->add_section(
			'navitto_pro',
			array(
				'title'    => __( 'Navitto Pro', 'navitto-pro' ),
				'priority' => 201,
			)
		);

		$wp_customize->add_setting(
			'navitto_pro_custom_enabled',
			array(
				'default'           => false,
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		$wp_customize->add_control(
			'navitto_pro_custom_enabled',
			array(
				'label'       => __( 'カスタム色でナビを表示', 'navitto-pro' ),
				'description' => __( '有効にすると、無料版のプリセット設定にかかわらずナビに独自の文字色・背景色を適用します（フロントの navittoData.preset も custom に上書きします）。', 'navitto-pro' ),
				'section'     => 'navitto_pro',
				'type'        => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'navitto_pro_custom_color_text',
			array(
				'default'           => '#333333',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'navitto_pro_custom_color_text',
				array(
					'label'           => __( '文字色', 'navitto-pro' ),
					'section'         => 'navitto_pro',
					'active_callback' => array( $this, 'is_customizer_pro_colors_active' ),
				)
			)
		);

		$wp_customize->add_setting(
			'navitto_pro_custom_color_bg',
			array(
				'default'           => '#ffffff',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'navitto_pro_custom_color_bg',
				array(
					'label'           => __( '背景色', 'navitto-pro' ),
					'section'         => 'navitto_pro',
					'active_callback' => array( $this, 'is_customizer_pro_colors_active' ),
				)
			)
		);

		$wp_customize->add_setting(
			'navitto_pro_custom_color_underline',
			array(
				'default'           => '#0073aa',
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'navitto_pro_custom_color_underline',
				array(
					'label'           => __( '選択中・アクセント色', 'navitto-pro' ),
					'section'         => 'navitto_pro',
					'active_callback' => array( $this, 'is_customizer_pro_colors_active' ),
				)
			)
		);
	}

	/**
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	/**
	 * @return bool
	 */
	public function is_customizer_pro_colors_active() {
		return Navitto_Pro_License::is_valid() && (bool) get_theme_mod( 'navitto_pro_custom_enabled', false );
	}

	/**
	 * @param string $hook_suffix 管理画面フック.
	 */
	public function enqueue_admin_metabox_extras( $hook_suffix ) {
		if ( ! Navitto_Pro_License::is_valid() ) {
			return;
		}
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		global $post;
		$h2_icons = array();
		if ( $post && $post->ID > 0 ) {
			$raw = get_post_meta( $post->ID, '_navitto_h2_icons', true );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $k => $v ) {
					if ( is_string( $v ) && '' !== $v ) {
						$h2_icons[ strval( $k ) ] = $v;
					}
				}
			}
		}

		wp_enqueue_style(
			'navitto-pro-fa',
			NAVITTO_PRO_URL . 'assets/lib/fontawesome/all-nv.min.css',
			array(),
			NAVITTO_PRO_VERSION
		);
		wp_enqueue_script(
			'navitto-pro-icons',
			NAVITTO_PRO_URL . 'assets/js/navitto-icons.js',
			array(),
			NAVITTO_PRO_VERSION,
			true
		);
		wp_enqueue_script(
			'navitto-pro-admin-metabox',
			NAVITTO_PRO_URL . 'assets/js/admin-metabox-pro.js',
			array( 'navitto-admin-metabox', 'navitto-pro-icons' ),
			NAVITTO_PRO_VERSION,
			true
		);
		wp_localize_script(
			'navitto-pro-admin-metabox',
			'navittoProMetabox',
			array(
				'h2Icons' => $h2_icons,
				'i18n'    => array(
					'addIcon'    => __( 'アイコンを追加', 'navitto-pro' ),
					'removeIcon' => __( 'アイコンを削除', 'navitto-pro' ),
					'pickTitle'  => __( 'アイコンを選択', 'navitto-pro' ),
					'close'      => __( '閉じる', 'navitto-pro' ),
				),
			)
		);
	}

	/**
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 */
	public function save_h2_icons_post( $post_id, $post ) {
		if ( ! Navitto_Pro_License::is_valid() ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['navitto_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['navitto_meta_nonce'] ) ), 'navitto_save_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$mode = isset( $_POST['navitto_display_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['navitto_display_mode'] ) ) : 'show_all';
		if ( 'select' !== $mode ) {
			delete_post_meta( $post_id, '_navitto_h2_icons' );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- array_map intval
		$selected = isset( $_POST['navitto_selected_h2'] ) ? array_map( 'intval', wp_unslash( $_POST['navitto_selected_h2'] ) ) : array();
		$icons    = array();
		foreach ( $selected as $idx ) {
			$icon_key = 'navitto_h2_icon_' . $idx;
			if ( isset( $_POST[ $icon_key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $icon_key ] ) );
				$val = $this->sanitize_icon_value( $val );
				if ( '' !== $val && 'none' !== $val ) {
					$icons[ $idx ] = $val;
				}
			}
		}
		update_post_meta( $post_id, '_navitto_h2_icons', $icons );
	}

	/**
	 * @param string $value Raw icon field.
	 * @return string
	 */
	private function sanitize_icon_value( $value ) {
		$value = preg_replace( '/[^a-zA-Z0-9_\-:]/', '', $value );
		return is_string( $value ) ? $value : '';
	}
}
