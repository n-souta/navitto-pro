<?php
/**
 * ライセンス設定・検証（アーカイブ版に準拠: Lemon Squeezy + Ajax）
 *
 * @package Navitto_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Navitto_Pro_License {

	/**
	 * Lemon Squeezy licenses/validate（navitto-pro-archive と同一）
	 *
	 * @var string
	 */
	private const LEMONSQUEEZY_VALIDATE_URL = 'https://api.lemonsqueezy.com/v1/licenses/validate';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'admin_init', array( $this, 'maybe_migrate_legacy_license_options' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'wp_ajax_navitto_pro_activate_license', array( $this, 'ajax_activate_license' ) );
		add_action( 'wp_ajax_navitto_pro_clear_license', array( $this, 'ajax_clear_license' ) );
	}

	/**
	 * アーカイブ版プラグインのオプション名からの移行（1 回のみ）
	 */
	public function maybe_migrate_legacy_license_options() {
		if ( '1' === get_option( 'navitto_pro_license_migrated', '' ) ) {
			return;
		}
		$old_key = get_option( 'navitto_license_key', '' );
		if ( is_string( $old_key ) && '' !== $old_key && '' === get_option( 'navitto_pro_license_key', '' ) ) {
			update_option( 'navitto_pro_license_key', $old_key );
			$old_status = get_option( 'navitto_license_status', '' );
			if ( 'valid' === $old_status ) {
				update_option( 'navitto_pro_license_status', 'valid' );
			}
			$old_email = get_option( 'navitto_license_email', '' );
			if ( is_string( $old_email ) && '' !== $old_email ) {
				update_option( 'navitto_pro_license_email', $old_email );
			}
		}
		update_option( 'navitto_pro_license_migrated', '1' );
	}

	public static function is_valid() {
		return get_option( 'navitto_pro_license_status', '' ) === 'valid';
	}

	public function add_settings_page() {
		$hook = add_options_page(
			'Navitto Pro',
			'Navitto Pro',
			'manage_options',
			'navitto-pro',
			array( $this, 'render_settings_page' )
		);
		add_action( 'load-' . $hook, array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_license_script' ) );
	}

	/**
	 * @param string $hook_suffix 現在の管理画面フック名.
	 */
	public function enqueue_license_script( $hook_suffix ) {
		if ( 'settings_page_navitto-pro' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script(
			'navitto-pro-admin-license',
			NAVITTO_PRO_URL . 'assets/js/admin-license.js',
			array( 'jquery' ),
			NAVITTO_PRO_VERSION,
			true
		);
		wp_localize_script(
			'navitto-pro-admin-license',
			'navittoProLicense',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'licenseNonce' => wp_create_nonce( 'navitto_pro_license_activate' ),
				'i18n'         => array(
					'licenseActivate'     => __( '有効化', 'navitto-pro' ),
					'licenseActivating'   => __( '確認中...', 'navitto-pro' ),
					'licenseValid'        => __( 'ライセンスは有効です。', 'navitto-pro' ),
					'licenseInvalid'      => __( 'ライセンスが無効です。', 'navitto-pro' ),
					'licenseEmpty'        => __( 'ライセンスキーを入力してください。', 'navitto-pro' ),
					'licenseClear'        => __( 'ライセンスをクリア', 'navitto-pro' ),
					'confirmClearLicense' => __( '保存したライセンスをクリアします。未入力状態で検証できます。', 'navitto-pro' ),
					'error'               => __( 'エラーが発生しました。', 'navitto-pro' ),
				),
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$license_key    = get_option( 'navitto_pro_license_key', '' );
		$license_status = get_option( 'navitto_pro_license_status', '' );
		$is_valid       = ( 'valid' === $license_status );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p>
				<?php
				esc_html_e( 'Navitto Pro ライセンスを購入すると、アイコンやカスタム色が利用できます。ライセンスキーを入力し、「有効化」をクリックしてください。', 'navitto-pro' );
				?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="navitto_pro_license_key"><?php esc_html_e( 'ライセンスキー', 'navitto-pro' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="navitto_pro_license_key"
								class="regular-text"
								name="navitto_pro_license_key"
								value="<?php echo esc_attr( $license_key ); ?>"
								placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
								autocomplete="off" />
							<button type="button"
								id="navitto_pro_activate_license"
								class="button button-secondary">
								<?php esc_html_e( '有効化', 'navitto-pro' ); ?>
							</button>
							<span id="navitto_pro_license_result" class="navitto-license-result" aria-live="polite"></span>
							<?php if ( $is_valid ) : ?>
								<p class="description navitto-pro-license-status navitto-license-status-valid">
									<?php esc_html_e( 'ライセンスは有効です。', 'navitto-pro' ); ?>
								</p>
								<p class="description" style="margin-top: 6px;">
									<button type="button" id="navitto_pro_clear_license" class="button button-link-delete">
										<?php esc_html_e( 'ライセンスをクリア', 'navitto-pro' ); ?>
									</button>
									<?php esc_html_e( '（未入力状態の検証用）', 'navitto-pro' ); ?>
								</p>
							<?php elseif ( $license_key && ! $is_valid ) : ?>
								<p class="description navitto-pro-license-status navitto-license-status-invalid">
									<?php esc_html_e( 'ライセンスが無効です。キーを確認するか、再度有効化してください。', 'navitto-pro' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( defined( 'NAVITTO_PRO_LICENSE_SKIP_VERIFY' ) && NAVITTO_PRO_LICENSE_SKIP_VERIFY ) : ?>
				<p class="description">
					<?php esc_html_e( '開発モード: NAVITTO_PRO_LICENSE_SKIP_VERIFY が有効です。有効化 Ajax は Lemon Squeezy を呼ばず、キーが空でなければ有効として保存します。', 'navitto-pro' ); ?>
				</p>
			<?php endif; ?>

			<style>
				.navitto-license-result {
					display: inline-block;
					margin-left: 10px;
					font-weight: 600;
				}
				.navitto-license-result.success { color: #00a32a; }
				.navitto-license-result.error   { color: #d63638; }
			</style>
		</div>
		<?php
	}

	/**
	 * Ajax: ライセンス有効化
	 */
	public function ajax_activate_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'navitto-pro' ) ) );
		}
		if ( ! check_ajax_referer( 'navitto_pro_license_activate', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'セキュリティ検証に失敗しました。', 'navitto-pro' ) ) );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( '' === $license_key ) {
			wp_send_json_error( array( 'message' => __( 'ライセンスキーを入力してください。', 'navitto-pro' ) ) );
		}

		if ( defined( 'NAVITTO_PRO_LICENSE_SKIP_VERIFY' ) && NAVITTO_PRO_LICENSE_SKIP_VERIFY ) {
			update_option( 'navitto_pro_license_key', $license_key );
			update_option( 'navitto_pro_license_status', 'valid' );
			wp_send_json_success( array( 'message' => __( 'ライセンスは有効です。', 'navitto-pro' ) ) );
		}

		$custom_url = apply_filters( 'navitto_pro_license_api_url', '' );
		if ( is_string( $custom_url ) && '' !== $custom_url ) {
			$result = $this->validate_license_remote_generic( $license_key, $custom_url );
		} else {
			$result = $this->validate_license_with_lemonsqueezy( $license_key );
		}

		if ( is_wp_error( $result ) ) {
			update_option( 'navitto_pro_license_status', '' );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$valid = ! empty( $result['valid'] );
		$valid = (bool) apply_filters( 'navitto_pro_license_is_valid', $valid, $result, null, $license_key );

		if ( ! $valid ) {
			update_option( 'navitto_pro_license_status', '' );
			$error_msg = ! empty( $result['error'] ) ? $result['error'] : __( 'ライセンスが無効です。', 'navitto-pro' );
			$error_msg = apply_filters( 'navitto_pro_license_error_message', $error_msg, $result, $license_key );
			wp_send_json_error( array( 'message' => $error_msg ) );
		}

		update_option( 'navitto_pro_license_key', $license_key );
		update_option( 'navitto_pro_license_status', 'valid' );
		if ( ! empty( $result['meta']['customer_email'] ) ) {
			update_option( 'navitto_pro_license_email', sanitize_email( $result['meta']['customer_email'] ) );
		} else {
			delete_option( 'navitto_pro_license_email' );
		}

		wp_send_json_success( array( 'message' => __( 'ライセンスは有効です。', 'navitto-pro' ) ) );
	}

	/**
	 * Ajax: ライセンスクリア
	 */
	public function ajax_clear_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'navitto-pro' ) ) );
		}
		if ( ! check_ajax_referer( 'navitto_pro_license_activate', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'セキュリティ検証に失敗しました。', 'navitto-pro' ) ) );
		}

		delete_option( 'navitto_pro_license_key' );
		delete_option( 'navitto_pro_license_status' );
		delete_option( 'navitto_pro_license_email' );

		wp_send_json_success( array( 'message' => __( 'ライセンスをクリアしました。', 'navitto-pro' ) ) );
	}

	/**
	 * Lemon Squeezy API（navitto-pro-archive の validate_license_with_lemonsqueezy と同等）
	 *
	 * @param string $license_key ライセンスキー.
	 * @return array|WP_Error
	 */
	private function validate_license_with_lemonsqueezy( $license_key ) {
		$url  = self::LEMONSQUEEZY_VALIDATE_URL;
		$body = array( 'license_key' => $license_key );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_raw, true );

		if ( 200 !== $code ) {
			$msg = isset( $data['error'] ) ? $data['error'] : sprintf(
				/* translators: %d: HTTP status code */
				__( 'ライセンスの確認に失敗しました。（HTTP %d）', 'navitto-pro' ),
				$code
			);
			return new WP_Error( 'navitto_pro_license_http_error', $msg );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'navitto_pro_license_invalid_response', __( 'ライセンスの確認に失敗しました。', 'navitto-pro' ) );
		}

		return $data;
	}

	/**
	 * カスタム検証 URL（フィルター navitto_pro_license_api_url 指定時）
	 *
	 * POST: license_key, site_url。JSON: valid（真偽）, 任意で error, meta.customer_email
	 *
	 * @param string $license_key キー.
	 * @param string $url         エンドポイント.
	 * @return array|WP_Error レスポンス配列（valid キーを含む想定）.
	 */
	private function validate_license_remote_generic( $license_key, $url ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'license_key' => $license_key,
					'site_url'    => home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			return new WP_Error(
				'navitto_pro_license_http_error',
				__( 'ライセンスの確認に失敗しました。', 'navitto-pro' )
			);
		}

		return $data;
	}
}
