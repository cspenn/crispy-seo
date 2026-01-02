/**
 * CrispySEO Admin JavaScript
 *
 * @package CrispySEO
 * @since 1.0.0
 */

(function($) {
	'use strict';

	// CrispySEO Admin namespace.
	window.CrispySEOAdmin = {

		/**
		 * Initialize admin functionality.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Media library optimize button.
			$(document).on('click', '.crispy-optimize-btn', this.handleOptimizeClick);

			// Character counter for meta fields.
			$('.crispy-seo-meta-field').on('input', this.updateCharCount);
		},

		/**
		 * Handle single image optimization click.
		 *
		 * @param {Event} e Click event.
		 */
		handleOptimizeClick: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var attachmentId = $btn.data('id');

			if (!attachmentId || !crispySeoAdmin) {
				return;
			}

			$btn.prop('disabled', true).text(crispySeoAdmin.i18n.optimizing || 'Optimizing...');

			$.post(crispySeoAdmin.ajaxUrl, {
				action: 'crispy_seo_optimize_image',
				nonce: crispySeoAdmin.nonce,
				attachment_id: attachmentId
			}, function(response) {
				if (response.success) {
					$btn.replaceWith('<span class="dashicons dashicons-yes" style="color: green;"></span> ' + response.data.savings_percent + '%');
				} else {
					$btn.prop('disabled', false).text(crispySeoAdmin.i18n.optimize || 'Optimize');
					alert(response.data.message || 'Optimization failed.');
				}
			}).fail(function() {
				$btn.prop('disabled', false).text(crispySeoAdmin.i18n.optimize || 'Optimize');
			});
		},

		/**
		 * Update character count display.
		 */
		updateCharCount: function() {
			var $field = $(this);
			var $counter = $field.siblings('.char-count');
			var maxLength = $field.data('max-length') || 160;
			var currentLength = $field.val().length;

			$counter.text(currentLength + '/' + maxLength);

			if (currentLength > maxLength) {
				$counter.addClass('over-limit');
			} else {
				$counter.removeClass('over-limit');
			}
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		CrispySEOAdmin.init();
	});

})(jQuery);
