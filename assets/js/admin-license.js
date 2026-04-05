/**
 * Navitto Pro - ライセンス設定（Ajax 有効化・クリア）
 *
 * @package Navitto_Pro
 */
(function($) {
	'use strict';

	$(function() {
		var config = window.navittoProLicense || {};
		var i18n = config.i18n || {};

		$('#navitto_pro_activate_license').on('click', function() {
			var $btn = $(this);
			var $input = $('#navitto_pro_license_key');
			var $result = $('#navitto_pro_license_result');
			var key = $.trim($input.val());

			if (!key) {
				$result.removeClass('success error').addClass('error').text(i18n.licenseEmpty || '');
				return;
			}

			$result.removeClass('success error').text('');
			$('.navitto-pro-license-status').remove();
			$btn.prop('disabled', true).text(i18n.licenseActivating || '');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'navitto_pro_activate_license',
					nonce: config.licenseNonce,
					license_key: key
				},
				dataType: 'json'
			})
				.done(function(response) {
					if (response.success && response.data) {
						$result.addClass('success').text(response.data.message || i18n.licenseValid || '');
					} else {
						var msg = (response.data && response.data.message) ? response.data.message : (i18n.licenseInvalid || '');
						$result.addClass('error').text(msg);
					}
				})
				.fail(function() {
					$result.addClass('error').text(i18n.error || '');
				})
				.always(function() {
					$btn.prop('disabled', false).text(i18n.licenseActivate || '');
				});
		});

		$('#navitto_pro_clear_license').on('click', function() {
			if (!confirm(i18n.confirmClearLicense || '')) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'navitto_pro_clear_license',
					nonce: config.licenseNonce
				},
				dataType: 'json'
			})
				.done(function(response) {
					if (response.success) {
						window.location.reload();
					} else {
						$('#navitto_pro_license_result').removeClass('success').addClass('error')
							.text((response.data && response.data.message) || i18n.error || '');
					}
				})
				.fail(function() {
					$('#navitto_pro_license_result').removeClass('success').addClass('error').text(i18n.error || '');
				})
				.always(function() {
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
