<?php
/**
 * ライセンス設定・検証
 *
 * @package Navitto_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Navitto_Pro_License {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
	}

	public static function is_valid() {
		return get_option( 'navitto_pro_license_status', '' ) === 'valid';
	}

	public function register_settings() {
		register_setting(
			'navitto_pro_license',
			'navitto_pro_license_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_license_key' ),
				'default'           => '',
			)
		);
	}

	/**
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_license_key( $value ) {
		$key = is_string( $value ) ? sanitize_text_field( $value ) : '';
		$this->verify_and_set_status( $key );
		return $key;
	}

	/**
	 * @param string $key License key (may be empty).
	 */
	public function verify_and_set_status( $key ) {
		if ( '' === $key ) {
			update_option( 'navitto_pro_license_status', '' );
			return;
		}

		if ( defined( 'NAVITTO_PRO_LICENSE_SKIP_VERIFY' ) && NAVITTO_PRO_LICENSE_SKIP_VERIFY ) {
			update_option( 'navitto_pro_license_status', 'valid' );
			return;
		}

		$url = apply_filters( 'navitto_pro_license_api_url', '' );
		if ( ! is_string( $url ) || '' === $url ) {
			update_option( 'navitto_pro_license_status', 'invalid' );
			return;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'license_key' => $key,
					'site_url'    => home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			update_option( 'navitto_pro_license_status', 'error' );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok   = ( 200 === $code && is_array( $body ) && ! empty( $body['valid'] ) );
		$ok   = (bool) apply_filters( 'navitto_pro_license_is_valid', $ok, $body, $response, $key );

		update_option( 'navitto_pro_license_status', $ok ? 'valid' : 'invalid' );
	}

	public function add_settings_page() {
		add_options_page(
			'Navitto Pro',
			'Navitto Pro',
			'manage_options',
			'navitto-pro',
			array( $this, 'render_settings_page' )
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$key    = get_option( 'navitto_pro_license_key', '' );
		$status = get_option( 'navitto_pro_license_status', '' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'navitto_pro_license' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="navitto_pro_license_key"><?php esc_html_e( 'ライセンスキー', 'navitto-pro' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="navitto_pro_license_key"
								name="navitto_pro_license_key" value="<?php echo esc_attr( $key ); ?>"
								autocomplete="off" />
							<p class="description">
								<?php esc_html_e( 'ライセンスキーを入力して保存すると、ライセンスサーバーで検証します。', 'navitto-pro' ); ?>
							</p>
							<?php if ( 'valid' === $status ) : ?>
								<p class="notice notice-success inline" style="padding:8px 12px;">
									<?php esc_html_e( 'ライセンスは有効です。', 'navitto-pro' ); ?>
								</p>
							<?php elseif ( 'invalid' === $status ) : ?>
								<p class="notice notice-error inline" style="padding:8px 12px;">
									<?php esc_html_e( 'ライセンスを確認できませんでした。', 'navitto-pro' ); ?>
								</p>
							<?php elseif ( 'error' === $status ) : ?>
								<p class="notice notice-warning inline" style="padding:8px 12px;">
									<?php esc_html_e( 'ライセンスサーバーに接続できませんでした。時間をおいて再度お試しください。', 'navitto-pro' ); ?>
								</p>
							<?php endif; ?>
							<p class="description">
								<?php
								esc_html_e(
									'開発用: wp-config.php で NAVITTO_PRO_LICENSE_SKIP_VERIFY を true にするとリモート検証を省略できます。本番では navitto_pro_license_api_url フィルターでエンドポイントを指定し、POST の license_key / site_url を受け取り JSON で valid（真偽）を返してください。',
									'navitto-pro'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( '保存して検証', 'navitto-pro' ) ); ?>
			</form>
		</div>
		<?php
	}
}
