/* Admin entries page interactions. */
(function () {
	'use strict';

	function init() {
		// Copy shortcode to clipboard on click.
		document.querySelectorAll('.leastudios-forms-shortcode').forEach(function (el) {
			el.addEventListener('click', function () {
				var text = el.textContent;
				if (navigator.clipboard) {
					navigator.clipboard.writeText(text);
					var original = el.textContent;
					el.textContent = 'Copied!';
					setTimeout(function () { el.textContent = original; }, 1500);
				}
			});
		});
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
