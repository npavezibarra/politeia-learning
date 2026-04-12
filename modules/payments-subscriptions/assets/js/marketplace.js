(function () {
	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var existing = document.querySelector('script[src="' + src + '"]');
			if (existing) return resolve();
			var s = document.createElement('script');
			s.src = src;
			s.async = true;
			s.onload = function () { resolve(); };
			s.onerror = function () { reject(new Error('failed to load ' + src)); };
			document.head.appendChild(s);
		});
	}

	function setStatus(message, isError) {
		var el = document.querySelector('.politeia-pps__marketplace-status');
		if (!el) return;
		el.style.display = message ? 'block' : 'none';
		el.className = 'politeia-pps__marketplace-status ' + (isError ? 'politeia-pps__error' : 'politeia-pps__notice');
		el.textContent = message || '';
	}

	function extractErrorMessage(data) {
		if (!data) return null;
		if (typeof data === 'string') return data;

		// Our REST error format: { error, message, data: { status, body, url } }
		if (data.data) {
			if (data.data.body) {
				var body = data.data.body;
				if (body.message) {
					var parts = [body.message];
					if (body.code) parts.push('code=' + body.code);
					if (body.blocked_by) parts.push('blocked_by=' + body.blocked_by);
					return parts.join(' ');
				}
				if (body.error) return body.error;
				if (body.cause && Array.isArray(body.cause) && body.cause[0] && body.cause[0].description) {
					return body.cause[0].description;
				}
				try {
					return JSON.stringify(body);
				} catch (e) {}
			}
			if (data.data.url) return data.message + ' (' + data.data.url + ')';
		}

		// Fall back to generic fields.
		if (data.message && typeof data.message === 'string') return data.message;
		if (data.error_description && typeof data.error_description === 'string') return data.error_description;

		return null;
	}

	function renderCardForm(container) {
		if (!container) return;
		if (container.querySelector('.politeia-pps__card-form')) return;

		var wrap = document.createElement('div');
		wrap.className = 'politeia-pps__card-form';
		wrap.innerHTML = [
			'<h3>Pago (Direct)</h3>',
			'<div class="politeia-pps__grid">',
			'  <div class="politeia-pps__full"><label>Email</label><input type="email" name="cardholderEmail" autocomplete="email" placeholder="tu@email.com" /></div>',
			'  <div class="politeia-pps__full"><label>Número de tarjeta</label><input type="text" name="cardNumber" inputmode="numeric" autocomplete="cc-number" placeholder="5416 7526 0258 2580" /></div>',
			'  <div><label>Mes</label><input type="text" name="cardExpirationMonth" inputmode="numeric" autocomplete="cc-exp-month" placeholder="11" /></div>',
			'  <div><label>Año</label><input type="text" name="cardExpirationYear" inputmode="numeric" autocomplete="cc-exp-year" placeholder="2030" /></div>',
			'  <div><label>CVV</label><input type="text" name="securityCode" inputmode="numeric" autocomplete="cc-csc" placeholder="123" /></div>',
			'  <div><label>Nombre</label><input type="text" name="cardholderName" autocomplete="cc-name" placeholder="Nombre Apellido" /></div>',
			'  <div><label>Tipo doc</label><select name="identificationType"><option value="">(opcional)</option><option value="RUT">RUT</option><option value="DNI">DNI</option></select></div>',
			'  <div><label>Número doc</label><input type="text" name="identificationNumber" autocomplete="off" placeholder="12.345.678-9" /></div>',
			'</div>',
			'<div class="politeia-pps__hint">Se tokeniza en el navegador usando Mercado Pago JS v2 y se envía al servidor como <code>card_token_id</code>.</div>'
		].join('');

		container.insertBefore(wrap, container.firstChild);
	}

	function getFormValues(container) {
		var form = container && container.querySelector ? container.querySelector('.politeia-pps__card-form') : null;
		if (!form) return null;
		function val(name) {
			var el = form.querySelector('[name="' + name + '"]');
			return el ? String(el.value || '').trim() : '';
		}

		var expMonth = val('cardExpirationMonth');
		var expYear = val('cardExpirationYear');
		if (expYear && expYear.length === 2) expYear = '20' + expYear;
		if (expMonth && expMonth.length === 1) expMonth = '0' + expMonth;

		return {
			cardholderEmail: val('cardholderEmail'),
			cardNumber: val('cardNumber').replace(/\s+/g, ''),
			cardExpirationMonth: expMonth,
			cardExpirationYear: expYear,
			securityCode: val('securityCode'),
			cardholderName: val('cardholderName'),
			identificationType: val('identificationType'),
			identificationNumber: val('identificationNumber').replace(/[^\d]/g, '')
		};
	}

	async function createCardToken(values) {
		var publicKey = (window.PoliteiaPPSMarketplace && PoliteiaPPSMarketplace.publicKey) ? String(PoliteiaPPSMarketplace.publicKey) : '';
		if (!publicKey) throw new Error('Missing Mercado Pago public key.');

		// Load MP SDK (v2). If it fails, user can still paste card_token_id manually.
		await loadScript('https://sdk.mercadopago.com/js/v2');
		if (typeof window.MercadoPago !== 'function') throw new Error('MercadoPago SDK not available.');

		var mp = new window.MercadoPago(publicKey, { locale: 'es-CL' });

		var body = {
			cardNumber: values.cardNumber,
			cardExpirationMonth: values.cardExpirationMonth,
			cardExpirationYear: values.cardExpirationYear,
			securityCode: values.securityCode,
			cardholderName: values.cardholderName
		};

		if (values.cardholderEmail) body.cardholderEmail = values.cardholderEmail;
		if (values.identificationType && values.identificationNumber) {
			body.identificationType = values.identificationType;
			body.identificationNumber = values.identificationNumber;
		}

		var token = await mp.createCardToken(body);
		// token is typically { id, ... } in v2
		if (!token || !token.id) throw new Error('Card tokenization failed.');
		return token;
	}

	async function subscribe(tierId) {
		if (!window.PoliteiaPPSMarketplace) return;
		if (!PoliteiaPPSMarketplace.loggedIn) {
			setStatus(PoliteiaPPSMarketplace.i18n.loginRequired, true);
			return;
		}

		var flow = PoliteiaPPSMarketplace.flow || 'hosted';
		var payload = { tier_id: Number(tierId) };
		if (flow === 'direct') {
			var container = document.querySelector('.politeia-pps--marketplace');
			renderCardForm(container);
			var values = getFormValues(container);
			if (!values) {
				setStatus('No se encontró el formulario de tarjeta.', true);
				return;
			}
			if (values.cardholderEmail) {
				payload.payer_email = values.cardholderEmail;
			}

			setStatus('Tokenizando tarjeta…', false);
			try {
				var token = await createCardToken(values);
				payload.card_token_id = String(token.id);
				if (token.payment_method_id) payload.payment_method_id = String(token.payment_method_id);
				if (token.issuer && token.issuer.id) payload.issuer_id = String(token.issuer.id);
			} catch (eTok) {
				// Fallback: allow manual paste to keep debugging unblocked.
				var manual = window.prompt('No se pudo tokenizar automáticamente. Pega card_token_id manual (o cancelar).');
				if (!manual) {
					setStatus((eTok && eTok.message) ? eTok.message : 'No se pudo tokenizar la tarjeta.', true);
					return;
				}
				payload.card_token_id = String(manual);
			}
		}

		setStatus(PoliteiaPPSMarketplace.i18n.processing, false);

		var res;
		try {
			res = await fetch(PoliteiaPPSMarketplace.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': PoliteiaPPSMarketplace.nonce
				},
				body: JSON.stringify(payload)
			});
		} catch (e) {
			setStatus(PoliteiaPPSMarketplace.i18n.error, true);
			return;
		}

		var data = null;
		try {
			data = await res.json();
		} catch (e2) {}

		if (!res.ok) {
			setStatus(extractErrorMessage(data) || PoliteiaPPSMarketplace.i18n.error, true);
			return;
		}

		// Safety: never show the direct card form when hosted flow is selected.
		if (flow === 'hosted') {
			var form = document.querySelector('.politeia-pps__card-form');
			if (form) form.remove();
		}

		if (flow === 'direct') {
			if (data && data.mp_preapproval_id) {
				setStatus('Suscripción creada (direct). mp_preapproval_id=' + data.mp_preapproval_id + ' status=' + (data.status || 'unknown'), false);
				return;
			}
			setStatus('Direct: creado, pero no se recibió mp_preapproval_id.', true);
			return;
		}

		var redirectUrl = data && data.redirect_url ? data.redirect_url : (data && (data.sandbox_init_point || data.init_point));
		if (redirectUrl) {
			setStatus('Opening Mercado Pago checkout…', false);
			// Keep marketplace open for debugging; open checkout in a new tab.
			window.open(redirectUrl, '_blank', 'noopener,noreferrer');
			setStatus('Checkout opened in a new tab. If you see a fatal page, copy the URL here and we’ll match it against init_point/sandbox_init_point.', false);
			return;
		}

		setStatus('Subscribed, but no redirect URL was provided by Mercado Pago.', false);
	}

	document.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('[data-pps-subscribe]') : null;
		if (!btn) return;
		e.preventDefault();
		subscribe(btn.getAttribute('data-tier-id'));
	});

	// Auto-render the card form when direct flow is enabled.
	document.addEventListener('DOMContentLoaded', function () {
		if (!window.PoliteiaPPSMarketplace) return;
		if ((PoliteiaPPSMarketplace.flow || 'hosted') !== 'direct') return;
		renderCardForm(document.querySelector('.politeia-pps--marketplace'));
	});
})();
