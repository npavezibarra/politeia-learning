(function ($) {
	function setResult(key, html) {
		var $el = $('[data-pps-result-for="' + key + '"]');
		if (!$el.length) return;
		$el.html(html);
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	$(document).on('click', '.politeia-pps-test-token', function (e) {
		e.preventDefault();

		if (!window.PoliteiaPPSAdmin) return;
		var mode = $(this).data('pps-mode');
		var key = $(this).data('pps-key');

		var token = $('input[name="politeia_pps_settings[' + key + ']"]').val() || '';
		setResult(key, 'Testing…');

		$.post(PoliteiaPPSAdmin.ajaxUrl, {
			action: 'politeia_pps_test_mp_token',
			nonce: PoliteiaPPSAdmin.nonce,
			mode: mode,
			key: key,
			token: token
		})
			.done(function (resp) {
				if (!resp || !resp.success || !resp.data) {
					setResult(key, '<span style="color:#b91c1c">Failed</span>');
					return;
				}

				if (!resp.data.ok) {
					setResult(key, '<span style="color:#b91c1c">' + escapeHtml(resp.data.message || 'Error') + '</span>');
					return;
				}

				var msg = resp.data.message ? String(resp.data.message) : '';
				if (!msg) {
					msg = 'OK (user_id=' + escapeHtml(resp.data.user_id) + ', site_id=' + escapeHtml(resp.data.site_id) + ')';
				}
				if (resp.data.warnings && resp.data.warnings.length) {
					msg += ' <span style="color:#b45309">' + escapeHtml(resp.data.warnings.join(' | ')) + '</span>';
				} else {
					msg = '<span style="color:#166534">' + escapeHtml(msg) + '</span>';
				}
				setResult(key, msg);
			})
			.fail(function () {
				setResult(key, '<span style="color:#b91c1c">Request failed</span>');
			});
	});
})(jQuery);
