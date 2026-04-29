/* global leastudiosFormsAdmin */
(function () {
	'use strict';

	var fieldsData = [];
	var fieldsTextarea;
	var settingsTextarea;
	var builderContainer;

	function init() {
		fieldsTextarea = document.getElementById('leastudios-forms-fields-data');
		settingsTextarea = document.getElementById('leastudios-forms-settings-data');
		builderContainer = document.getElementById('leastudios-forms-fields-list');

		if (!fieldsTextarea || !builderContainer) {
			return;
		}

		try {
			fieldsData = JSON.parse(fieldsTextarea.value) || [];
		} catch (e) {
			fieldsData = [];
		}

		renderFields();
		bindPaletteButtons();
		bindSettingsEvents();
		bindShortcodeCopy();
	}

	function generateId() {
		return 'field_' + Math.random().toString(36).substring(2, 10);
	}

	function bindPaletteButtons() {
		var buttons = document.querySelectorAll('.leastudios-forms-add-field');
		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var type = btn.getAttribute('data-field-type');
				var label = btn.textContent.trim();

				var newField = {
					id: generateId(),
					type: type,
					label: label,
					name: label.toLowerCase().replace(/[^a-z0-9]+/g, '_'),
					required: false,
					placeholder: '',
					options: [],
					validation: { pattern: '' },
					width: 'full',
					order: fieldsData.length
				};

				if (type === 'address') {
					newField.show_line2 = true;
					newField.default_country = 'US';
				}

				fieldsData.push(newField);

				renderFields();
				syncData();
			});
		});
	}

	function renderFields() {
		var html = '';

		fieldsData.forEach(function (field, index) {
			html += '<div class="leastudios-forms-builder-field" data-index="' + index + '">';
			html += '<span class="field-drag-handle dashicons dashicons-menu"></span>';
			html += '<span class="field-info">';
			html += '<strong>' + escapeHtml(field.label) + '</strong>';
			html += '<span class="field-type-badge">' + escapeHtml(field.type) + '</span>';
			if (field.required) {
				html += '<span class="field-required-marker" style="color:#d63638;"> *</span>';
			}
			html += '</span>';
			html += '<span class="field-actions">';
			html += '<button type="button" class="button button-small field-edit-btn" data-index="' + index + '">Edit</button>';
			html += '<button type="button" class="button button-small field-move-up" data-index="' + index + '">&uarr;</button>';
			html += '<button type="button" class="button button-small field-move-down" data-index="' + index + '">&darr;</button>';
			html += '<button type="button" class="button button-small button-link-delete field-remove-btn" data-index="' + index + '">Remove</button>';
			html += '</span>';
			html += '</div>';
			html += '<div class="leastudios-forms-field-edit" id="field-edit-' + index + '" style="display:none;">';
			html += renderEditPanel(field, index);
			html += '</div>';
		});

		if (fieldsData.length === 0) {
			html = '<p style="color:#787c82;text-align:center;padding:30px 20px;margin:0;border:2px dashed #dcdcde;border-radius:4px;">No fields yet. Use the buttons above to start building your form.</p>';
		}

		builderContainer.innerHTML = html;
		bindFieldButtons();
	}

	function renderEditPanel(field, index) {
		var html = '';

		// Address fields get a specialized editor.
		if (field.type === 'address') {
			html += '<div class="field-edit-row"><label>Label</label>';
			html += '<input type="text" class="field-prop" data-index="' + index + '" data-prop="label" value="' + escapeAttr(field.label) + '" /></div>';

			html += '<div class="field-edit-row"><label>Name</label>';
			html += '<input type="text" class="field-prop" data-index="' + index + '" data-prop="name" value="' + escapeAttr(field.name) + '" /></div>';

			html += '<div class="field-edit-row field-edit-row--checkbox"><label>Show Line 2</label>';
			html += '<label class="field-checkbox-label"><input type="checkbox" class="field-prop-check" data-index="' + index + '" data-prop="show_line2"' + (field.show_line2 !== false ? ' checked' : '') + ' /> Include Address Line 2</label></div>';

			html += '<div class="field-edit-row field-edit-row--checkbox"><label>Required</label>';
			html += '<label class="field-checkbox-label"><input type="checkbox" class="field-prop-check" data-index="' + index + '" data-prop="required"' + (field.required ? ' checked' : '') + ' /> Mark this field as required</label></div>';

			html += '<div class="field-edit-row"><label>Default Country</label>';
			html += '<select class="field-prop" data-index="' + index + '" data-prop="default_country">';
			var addrCountries = [['US','United States'],['CA','Canada'],['GB','United Kingdom'],['AU','Australia'],['NZ','New Zealand'],['IE','Ireland'],['DE','Germany'],['FR','France']];
			addrCountries.forEach(function (c) {
				var sel = (field.default_country || 'US') === c[0] ? ' selected' : '';
				html += '<option value="' + c[0] + '"' + sel + '>' + c[1] + '</option>';
			});
			html += '</select></div>';

			return html;
		}

		// Standard fields.
		html += '<div class="field-edit-row"><label>Label</label>';
		html += '<input type="text" class="field-prop" data-index="' + index + '" data-prop="label" value="' + escapeAttr(field.label) + '" /></div>';

		html += '<div class="field-edit-row"><label>Name</label>';
		html += '<input type="text" class="field-prop" data-index="' + index + '" data-prop="name" value="' + escapeAttr(field.name) + '" /></div>';

		html += '<div class="field-edit-row"><label>Placeholder</label>';
		html += '<input type="text" class="field-prop" data-index="' + index + '" data-prop="placeholder" value="' + escapeAttr(field.placeholder) + '" /></div>';

		html += '<div class="field-edit-row field-edit-row--checkbox"><label>Required</label>';
		html += '<label class="field-checkbox-label"><input type="checkbox" class="field-prop-check" data-index="' + index + '" data-prop="required"' + (field.required ? ' checked' : '') + ' /> Mark this field as required</label></div>';

		if (['select', 'radio', 'checkbox'].indexOf(field.type) !== -1) {
			var optStr = (field.options || []).map(function (o) { return typeof o === 'string' ? o : o.label || o.value || ''; }).join('\n');
			html += '<div class="field-edit-row"><label>Options</label>';
			html += '<textarea class="field-prop" data-index="' + index + '" data-prop="options" rows="4" placeholder="One option per line">' + escapeHtml(optStr) + '</textarea></div>';
		}

		return html;
	}

	function bindFieldButtons() {
		document.querySelectorAll('.field-edit-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var idx = btn.getAttribute('data-index');
				var panel = document.getElementById('field-edit-' + idx);
				if (panel) {
					panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
				}
			});
		});

		document.querySelectorAll('.field-remove-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var idx = parseInt(btn.getAttribute('data-index'), 10);
				fieldsData.splice(idx, 1);
				renderFields();
				syncData();
			});
		});

		document.querySelectorAll('.field-move-up').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var idx = parseInt(btn.getAttribute('data-index'), 10);
				if (idx > 0) {
					var temp = fieldsData[idx];
					fieldsData[idx] = fieldsData[idx - 1];
					fieldsData[idx - 1] = temp;
					renderFields();
					syncData();
				}
			});
		});

		document.querySelectorAll('.field-move-down').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var idx = parseInt(btn.getAttribute('data-index'), 10);
				if (idx < fieldsData.length - 1) {
					var temp = fieldsData[idx];
					fieldsData[idx] = fieldsData[idx + 1];
					fieldsData[idx + 1] = temp;
					renderFields();
					syncData();
				}
			});
		});

		document.querySelectorAll('.field-prop').forEach(function (input) {
			var evt = input.tagName === 'SELECT' ? 'change' : 'input';
			input.addEventListener(evt, function () {
				var idx = parseInt(input.getAttribute('data-index'), 10);
				var prop = input.getAttribute('data-prop');

				if (prop === 'options') {
					fieldsData[idx].options = input.value.split('\n').filter(function (v) { return v.trim() !== ''; });
				} else if (prop === 'amount') {
					fieldsData[idx][prop] = parseInt(input.value, 10) || 0;
				} else {
					fieldsData[idx][prop] = input.value;
				}

				if (prop === 'label') {
					updateFieldRowLabel(idx, input.value);
				}

				syncData();
			});
		});

		document.querySelectorAll('.field-prop-check').forEach(function (input) {
			input.addEventListener('change', function () {
				var idx = parseInt(input.getAttribute('data-index'), 10);
				var prop = input.getAttribute('data-prop');
				fieldsData[idx][prop] = input.checked;

				if (prop === 'required') {
					updateFieldRowRequired(idx, input.checked);
				}

				syncData();
			});
		});
	}

	function getFieldRow(idx) {
		return builderContainer.querySelector('.leastudios-forms-builder-field[data-index="' + idx + '"]');
	}

	function updateFieldRowLabel(idx, value) {
		var row = getFieldRow(idx);
		if (!row) return;
		var strong = row.querySelector('.field-info > strong');
		if (strong) {
			strong.textContent = value;
		}
	}

	function updateFieldRowRequired(idx, isRequired) {
		var row = getFieldRow(idx);
		if (!row) return;
		var info = row.querySelector('.field-info');
		if (!info) return;
		var marker = info.querySelector('.field-required-marker');
		if (isRequired) {
			if (!marker) {
				marker = document.createElement('span');
				marker.className = 'field-required-marker';
				marker.style.color = '#d63638';
				marker.textContent = ' *';
				info.appendChild(marker);
			}
		} else if (marker) {
			marker.remove();
		}
	}

	function bindSettingsEvents() {
		if (!settingsTextarea) {
			return;
		}

		var addNotificationBtn = document.getElementById('leastudios-forms-add-notification');
		if (addNotificationBtn) {
			addNotificationBtn.addEventListener('click', function () {
				addNotificationRow();
			});
		}

		// Bind remove buttons on existing notification rows.
		document.querySelectorAll('.notification-remove').forEach(function (btn) {
			btn.addEventListener('click', function () {
				btn.closest('.leastudios-forms-notification').remove();
				updateNotificationsWarning();
			});
		});

		// Sync settings on form submit.
		var form = settingsTextarea.closest('form');
		if (form) {
			form.addEventListener('submit', function () {
				syncSettings();
			});
		}
	}

	function addNotificationRow() {
		var container = document.getElementById('leastudios-forms-notifications');
		if (!container) return;

		var idx = container.querySelectorAll('.leastudios-forms-notification').length;
		var div = document.createElement('div');
		div.className = 'leastudios-forms-notification';
		div.innerHTML =
			'<div class="notification-header"><strong>Notification ' + (idx + 1) + '</strong>' +
			'<button type="button" class="button button-small button-link-delete notification-remove">Remove</button></div>' +
			'<p><label>To: <input type="text" class="widefat notification-to" value="{admin_email}" /></label></p>' +
			'<p><label>Subject: <input type="text" class="widefat notification-subject" value="New submission: {form_title}" /></label></p>' +
			'<p><label>Message: <textarea class="widefat notification-message" rows="3">{all_fields}</textarea></label></p>' +
			'<p><label>Reply-To: <input type="text" class="widefat notification-reply-to" /></label></p>';

		container.appendChild(div);

		div.querySelector('.notification-remove').addEventListener('click', function () {
			div.remove();
			updateNotificationsWarning();
		});

		updateNotificationsWarning();
	}

	function updateNotificationsWarning() {
		var warning = document.getElementById('leastudios-forms-notifications-warning');
		var container = document.getElementById('leastudios-forms-notifications');
		if (!warning || !container) return;
		var count = container.querySelectorAll('.leastudios-forms-notification').length;
		warning.style.display = count === 0 ? '' : 'none';
	}

	function syncData() {
		if (fieldsTextarea) {
			fieldsTextarea.value = JSON.stringify(fieldsData);
		}
	}

	function syncSettings() {
		if (!settingsTextarea) return;

		var settings = {
			success_message: getVal('#leastudios-forms-success-message') || 'Thank you for your submission.',
			redirect_url: getVal('#leastudios-forms-redirect-url') || '',
			submit_button_text: getVal('#leastudios-forms-submit-text') || 'Submit',
			spam_protection: {
				honeypot: isChecked('#leastudios-forms-honeypot'),
				rate_limit: parseInt(getVal('#leastudios-forms-rate-limit') || '5', 10),
				rate_limit_window: parseInt(getVal('#leastudios-forms-rate-window') || '60', 10)
			},
			notifications: []
		};

		document.querySelectorAll('.leastudios-forms-notification').forEach(function (el) {
			settings.notifications.push({
				to: el.querySelector('.notification-to') ? el.querySelector('.notification-to').value : '',
				subject: el.querySelector('.notification-subject') ? el.querySelector('.notification-subject').value : '',
				message: el.querySelector('.notification-message') ? el.querySelector('.notification-message').value : '',
				reply_to: el.querySelector('.notification-reply-to') ? el.querySelector('.notification-reply-to').value : ''
			});
		});

		settingsTextarea.value = JSON.stringify(settings);
	}

	function bindShortcodeCopy() {
		document.querySelectorAll('.leastudios-forms-shortcode').forEach(function (el) {
			el.addEventListener('click', function () {
				if (navigator.clipboard) {
					navigator.clipboard.writeText(el.textContent.trim());
					var original = el.textContent;
					el.textContent = 'Copied!';
					setTimeout(function () { el.textContent = original; }, 1500);
				}
			});
		});
	}

	function getVal(sel) {
		var el = document.querySelector(sel);
		return el ? el.value : '';
	}

	function isChecked(sel) {
		var el = document.querySelector(sel);
		return el ? el.checked : false;
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	function escapeAttr(str) {
		return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
