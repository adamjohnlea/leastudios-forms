/* global leastudiosForms */
(function () {
	'use strict';

	function init() {
		var forms = document.querySelectorAll('.leastudios-form');
		forms.forEach(bindForm);
	}

	function bindForm(form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();

			clearErrors(form);

			if (!clientValidate(form)) {
				return;
			}

			submitForm(form);
		});
	}

	function clientValidate(form) {
		var valid = true;
		var fields = form.querySelectorAll('[data-field-name]');

		fields.forEach(function (wrapper) {
			// Address fields — validate each required sub-input.
			var addressFieldset = wrapper.querySelector('.leastudios-forms-field--address');
			if (addressFieldset) {
				var addressValid = true;
				addressFieldset.querySelectorAll('input, select').forEach(function (sub) {
					// Skip address line 2.
					if (sub.id && sub.id.indexOf('-line2') !== -1) return;

					if (sub.value.trim() === '') {
						sub.style.borderColor = '#d63638';
						sub.style.boxShadow = '0 0 0 1px #d63638';
						addressValid = false;
						valid = false;
					} else {
						sub.style.borderColor = '';
						sub.style.boxShadow = '';
					}
				});
				if (!addressValid) {
					showFieldError(wrapper, null, 'Please complete all address fields.');
				}
				return;
			}

			var input = wrapper.querySelector('input, select, textarea');
			if (!input) return;

			var isRequired = input.hasAttribute('required') || input.getAttribute('aria-required') === 'true';
			var value = input.value.trim();

			if (isRequired && value === '') {
				showFieldError(wrapper, input, 'This field is required.');
				valid = false;
				return;
			}

			if (value && input.type === 'email' && !isValidEmail(value)) {
				showFieldError(wrapper, input, 'Please enter a valid email address.');
				valid = false;
			}

			if (value && input.type === 'url' && !isValidUrl(value)) {
				showFieldError(wrapper, input, 'Please enter a valid URL.');
				valid = false;
			}
		});

		return valid;
	}

	function submitForm(form) {
		doSubmit(form);
	}

	function doSubmit(form) {
		var formId = form.getAttribute('data-form-id');
		var submitBtn = form.querySelector('.leastudios-form-submit button');
		var fields = {};

		form.querySelectorAll('[data-field-name]').forEach(function (wrapper) {
			var name = wrapper.getAttribute('data-field-name');

			// Address fields have multiple sub-inputs.
			var addressFieldset = wrapper.querySelector('.leastudios-forms-field--address');
			if (addressFieldset) {
				fields[name] = {};
				addressFieldset.querySelectorAll('input, select').forEach(function (sub) {
					var match = sub.name.match(/\[([^\]]+)\]$/);
					if (match) {
						fields[name][match[1]] = sub.value;
					}
				});
				return;
			}

			var input = wrapper.querySelector('input, select, textarea');
			if (!input) return;

			if (input.type === 'checkbox') {
				var checked = wrapper.querySelectorAll('input[type="checkbox"]:checked');
				fields[name] = Array.from(checked).map(function (cb) { return cb.value; });
			} else {
				fields[name] = input.value;
			}
		});

		var nonce = form.querySelector('input[name="_wpnonce"]');
		var honeypot = form.querySelector('input[name="_leastudios_forms_hp"]');

		var payload = {
			form_id: parseInt(formId, 10),
			fields: fields,
			_wpnonce: nonce ? nonce.value : '',
			_leastudios_forms_hp: honeypot ? honeypot.value : ''
		};

		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting...';
		}

		fetch(leastudiosForms.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': leastudiosForms.restNonce
			},
			body: JSON.stringify(payload)
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.success) {
					if (data.redirect_url) {
						window.location.href = data.redirect_url;
						return;
					}

					form.innerHTML = '<div class="leastudios-form-success">' + escapeHtml(data.message || 'Thank you!') + '</div>';
				} else if (data.errors) {
					Object.keys(data.errors).forEach(function (fieldName) {
						var wrapper = form.querySelector('[data-field-name="' + fieldName + '"]');
						if (wrapper) {
							var input = wrapper.querySelector('input, select, textarea');
							showFieldError(wrapper, input, data.errors[fieldName]);
						}
					});
				} else {
					showFormError(form, data.message || 'An error occurred. Please try again.');
				}
			})
			.catch(function () {
				showFormError(form, 'A network error occurred. Please try again.');
			})
			.finally(function () {
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = submitBtn.getAttribute('data-original-text') || 'Submit';
				}
			});
	}

	function showFieldError(wrapper, input, message) {
		var errorEl = wrapper.querySelector('.field-error');
		if (errorEl) {
			errorEl.textContent = message;
			errorEl.classList.add('visible');
		}

		if (input) {
			input.setAttribute('aria-invalid', 'true');
		}
	}

	function showFormError(form, message) {
		var existing = form.querySelector('.leastudios-form-error');
		if (existing) {
			existing.remove();
		}

		var div = document.createElement('div');
		div.className = 'leastudios-form-error';
		div.style.cssText = 'padding:12px 15px;background:#fcf0f1;border-left:4px solid #d63638;border-radius:4px;margin-bottom:15px;';
		div.textContent = message;

		var firstField = form.querySelector('.leastudios-form-field');
		if (firstField) {
			form.insertBefore(div, firstField);
		} else {
			form.prepend(div);
		}
	}

	function clearErrors(form) {
		form.querySelectorAll('.field-error').forEach(function (el) {
			el.textContent = '';
			el.classList.remove('visible');
		});

		form.querySelectorAll('[aria-invalid]').forEach(function (el) {
			el.setAttribute('aria-invalid', 'false');
		});

		var formError = form.querySelector('.leastudios-form-error');
		if (formError) {
			formError.remove();
		}
	}

	function isValidEmail(email) {
		// Format: user@domain.tld — TLD must be 2-10 alpha chars, domain must have valid chars.
		// Catches obvious typos like .commmm while allowing real TLDs (.com, .co.uk, .photography).
		return /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,10}$/.test(email);
	}

	function isValidUrl(url) {
		try {
			new URL(url);
			return true;
		} catch (e) {
			return false;
		}
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
