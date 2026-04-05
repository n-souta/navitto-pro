/**
 * Navitto Pro - メタボックス（DOM 拡張 + アイコンピッカー）
 *
 * 無料版のテンプレートにフックがなくても、#cp-h2-select-area 内を拡張する。
 *
 * @package Navitto_Pro
 */
(function() {
	'use strict';

	var pm = window.navittoProMetabox || {};
	var i18n = pm.i18n || {};
	var addIconText = i18n.addIcon || 'アイコンを追加';
	var removeIconText = i18n.removeIcon || 'アイコンを削除';
	var pickTitle = i18n.pickTitle || 'アイコンを選択';
	var closeLabel = i18n.close || '閉じる';
	var savedIcons = pm.h2Icons && typeof pm.h2Icons === 'object' ? pm.h2Icons : {};

	function escAttr(s) {
		return String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function getSavedIcon(idx) {
		var k = String(idx);
		return savedIcons[k] !== undefined && savedIcons[k] !== null ? String(savedIcons[k]) : '';
	}

	var iconRegistry = window.__NAVITTO_ICONS__;
	var pickerOverlay = null;
	var pickerModal = null;
	var currentPickerType = 'h2';
	var currentPickerIndex = null;

	function getIconNameFromValue(val) {
		if (!val || val === 'none') return '';
		return (val.indexOf(':') !== -1) ? val.split(':')[1] : val;
	}

	function getIconHtmlForValue(val) {
		if (!iconRegistry) return '';
		return iconRegistry.getIconHtml ? iconRegistry.getIconHtml(val) : (iconRegistry.getSvg ? iconRegistry.getSvg(val) : '');
	}

	function updateIconButtonState(type, index) {
		var sel = (type ? '[data-type="' + type + '"]' : '') + '[data-index="' + index + '"]';
		var btn = document.querySelector('.navitto-icon-picker-btn' + sel);
		var hiddenInput = document.querySelector('.navitto-icon-picker-value' + sel);
		if (!btn || !hiddenInput) return;
		var hasIcon = !!(hiddenInput.value && hiddenInput.value !== 'none' && hiddenInput.value.indexOf(':none') === -1);
		btn.textContent = hasIcon ? removeIconText : addIconText;
		btn.title = hasIcon ? removeIconText : addIconText;
		btn.classList.toggle('navitto-icon-picker-btn--remove', hasIcon);
	}

	function applyPreview(preview, value) {
		if (!preview) return;
		if (value && value !== 'none' && value.indexOf(':none') === -1) {
			preview.innerHTML = getIconHtmlForValue(value) || '';
		} else {
			preview.innerHTML = '';
		}
	}

	function enhanceItem(item) {
		if (item.getAttribute('data-navitto-pro-ready') === '1') return;

		var row = item.querySelector('.cp-h2-item-row');
		if (!row) return;
		var input = row.querySelector('.cp-h2-text-input');
		if (!input) return;
		var idx = input.getAttribute('data-index');
		if (idx === null || idx === '') return;

		if (row.querySelector('.navitto-icon-picker-preview') || item.querySelector('.navitto-icon-picker-value')) {
			item.setAttribute('data-navitto-pro-ready', '1');
			return;
		}

		item.setAttribute('data-navitto-pro-ready', '1');

		var preview = document.createElement('span');
		preview.className = 'navitto-icon-picker-preview';
		preview.setAttribute('data-type', 'h2');
		preview.setAttribute('data-index', idx);
		row.insertBefore(preview, input);

		var saved = getSavedIcon(idx);

		var btnRow = document.createElement('div');
		btnRow.className = 'cp-h2-item-row cp-h2-item-row--icon-btn';

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'navitto-icon-picker-btn button button-small';
		btn.setAttribute('data-type', 'h2');
		btn.setAttribute('data-index', idx);
		btn.title = addIconText;
		btn.textContent = addIconText;
		if (input.disabled) btn.disabled = true;

		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'navitto_h2_icon_' + idx;
		hidden.className = 'navitto-icon-picker-value';
		hidden.setAttribute('data-type', 'h2');
		hidden.setAttribute('data-index', idx);
		hidden.value = saved;

		btnRow.appendChild(btn);
		btnRow.appendChild(hidden);
		item.appendChild(btnRow);

		applyPreview(preview, saved);
		if (iconRegistry) updateIconButtonState('h2', idx);
	}

	function runEnhance() {
		var h2Area = document.getElementById('cp-h2-select-area');
		if (!h2Area) return;
		h2Area.querySelectorAll('.cp-h2-item').forEach(enhanceItem);
	}

	function initPlaceholderPreviews() {
		if (!iconRegistry) return;
		document.querySelectorAll('.navitto-icon-picker-placeholder').forEach(function(el) {
			var val = el.getAttribute('data-icon-value');
			if (val) {
				var iconHtml = getIconHtmlForValue(val);
				if (iconHtml) {
					el.innerHTML = iconHtml;
					el.classList.remove('navitto-icon-picker-placeholder');
				}
			}
		});
		document.querySelectorAll('.navitto-icon-picker-value').forEach(function(input) {
			var type = input.getAttribute('data-type') || 'h2';
			var idx = input.getAttribute('data-index');
			if (idx !== null) updateIconButtonState(type, idx);
		});
	}

	var h2Area = document.getElementById('cp-h2-select-area');
	if (h2Area) {
		h2Area.addEventListener('change', function(e) {
			if (!e.target || !e.target.classList.contains('cp-h2-checkbox')) return;
			var idx = e.target.getAttribute('data-index');
			var textInput = document.querySelector('.cp-h2-text-input[data-index="' + idx + '"]');
			var iconBtn = document.querySelector('.navitto-icon-picker-btn[data-index="' + idx + '"]');
			if (textInput) textInput.disabled = !e.target.checked;
			if (iconBtn) iconBtn.disabled = !e.target.checked;
		});

		var obs = new MutationObserver(function() {
			runEnhance();
			initPlaceholderPreviews();
		});
		obs.observe(h2Area, { childList: true, subtree: true });
	}

	runEnhance();
	initPlaceholderPreviews();

	function buildPickerModal() {
		if (pickerModal || !iconRegistry || !iconRegistry.iconNames) return;

		pickerOverlay = document.createElement('div');
		pickerOverlay.className = 'navitto-icon-picker-overlay';
		pickerOverlay.setAttribute('aria-hidden', 'true');
		pickerOverlay.addEventListener('click', closeIconPicker);

		pickerModal = document.createElement('div');
		pickerModal.className = 'navitto-icon-picker-modal';
		pickerModal.setAttribute('role', 'dialog');
		pickerModal.setAttribute('aria-modal', 'true');
		pickerModal.setAttribute('aria-label', pickTitle);

		var header = document.createElement('div');
		header.className = 'navitto-icon-picker-header';
		header.innerHTML = '<span class="navitto-icon-picker-title">' + escAttr(pickTitle) + '</span>';
		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'navitto-icon-picker-close';
		closeBtn.innerHTML = '&times;';
		closeBtn.setAttribute('aria-label', closeLabel);
		closeBtn.addEventListener('click', closeIconPicker);
		header.appendChild(closeBtn);
		pickerModal.appendChild(header);

		var gridContainer = document.createElement('div');
		gridContainer.className = 'navitto-icon-picker-grid-container';
		var grid = document.createElement('div');
		grid.className = 'navitto-icon-picker-grid';

		iconRegistry.iconNames.forEach(function(iconName) {
			var cell = document.createElement('button');
			cell.type = 'button';
			cell.className = 'navitto-icon-picker-cell';
			cell.setAttribute('data-icon-name', iconName);
			cell.setAttribute('title', iconName);
			cell.setAttribute('aria-label', iconName);
			var iconHtml = iconRegistry.getIconHtml ? iconRegistry.getIconHtml(iconName) : iconRegistry.getSvg(iconName);
			cell.innerHTML = iconHtml || '';
			cell.addEventListener('click', function() { selectIcon(iconName); });
			grid.appendChild(cell);
		});
		gridContainer.appendChild(grid);
		pickerModal.appendChild(gridContainer);

		document.body.appendChild(pickerOverlay);
		document.body.appendChild(pickerModal);
	}

	function openIconPicker(type, index) {
		if (index === undefined) { index = type; type = 'h2'; }
		buildPickerModal();
		if (!pickerModal) return;
		currentPickerType = type || 'h2';
		currentPickerIndex = index;
		var sel = (currentPickerType ? '[data-type="' + currentPickerType + '"]' : '') + '[data-index="' + currentPickerIndex + '"]';
		var hiddenInput = document.querySelector('.navitto-icon-picker-value' + sel);
		var currentVal = hiddenInput ? hiddenInput.value : '';
		var currentName = getIconNameFromValue(currentVal) || '';

		pickerModal.querySelectorAll('.navitto-icon-picker-cell').forEach(function(cell) {
			cell.classList.toggle('navitto-icon-picker-cell-selected', cell.getAttribute('data-icon-name') === currentName);
		});
		pickerOverlay.classList.add('navitto-icon-picker-overlay-visible');
		pickerModal.classList.add('navitto-icon-picker-modal-visible');
	}

	function closeIconPicker() {
		if (pickerOverlay) pickerOverlay.classList.remove('navitto-icon-picker-overlay-visible');
		if (pickerModal) pickerModal.classList.remove('navitto-icon-picker-modal-visible');
		currentPickerIndex = null;
		currentPickerType = 'h2';
	}

	function selectIcon(iconName) {
		if (currentPickerIndex === null) return;
		var value = (!iconName || iconName === 'none') ? '' : iconName;
		var sel = (currentPickerType ? '[data-type="' + currentPickerType + '"]' : '') + '[data-index="' + currentPickerIndex + '"]';
		var hiddenInput = document.querySelector('.navitto-icon-picker-value' + sel);
		var preview = document.querySelector('.navitto-icon-picker-preview' + sel);
		if (hiddenInput) hiddenInput.value = value;
		applyPreview(preview, value);
		updateIconButtonState(currentPickerType, currentPickerIndex);
		closeIconPicker();
	}

	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.navitto-icon-picker-btn');
		if (!btn || btn.disabled) return;
		e.preventDefault();
		var type = btn.getAttribute('data-type') || 'h2';
		var index = btn.getAttribute('data-index');
		var sel = (type ? '[data-type="' + type + '"]' : '') + '[data-index="' + index + '"]';
		var hiddenInput = document.querySelector('.navitto-icon-picker-value' + sel);
		var hasIcon = hiddenInput && hiddenInput.value && hiddenInput.value !== 'none' && hiddenInput.value.indexOf(':none') === -1;
		if (hasIcon) {
			currentPickerType = type;
			currentPickerIndex = index;
			selectIcon('none');
		} else {
			openIconPicker(type, index);
		}
	});
})();
