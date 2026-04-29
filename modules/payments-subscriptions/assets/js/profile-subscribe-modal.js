(function () {
	function q(sel, root) {
		return (root || document).querySelector(sel);
	}

	function closestSubscribeLink(target) {
		if (!target || !target.closest) return null;
		var a = target.closest('a');
		if (!a) return null;
		var href = String(a.getAttribute('href') || '');
		if (!href) return null;
		if (href.indexOf('admin-post.php') === -1) return null;
		if (href.indexOf('action=pl_pps_subscribe_creator') === -1) return null;
		return a;
	}

	function ensureOverlay() {
		var existing = q('.politeia-pps__modal-overlay');
		if (existing) return existing;

		var overlay = document.createElement('div');
		overlay.className = 'politeia-pps__modal-overlay';
		overlay.innerHTML = [
			'<div class="politeia-pps__modal" role="dialog" aria-modal="true" aria-label="Suscripción">',
			'  <div class="politeia-pps__modal-header">',
			'    <h3 class="politeia-pps__modal-title">Suscribirme</h3>',
			'    <button class="politeia-pps__modal-close" type="button" aria-label="Cerrar">×</button>',
			'  </div>',
			'  <div class="politeia-pps__modal-body">',
			'    <div class="politeia-pps__modal-status" aria-live="polite"></div>',
			'    <div class="politeia-pps__modal-step politeia-pps__modal-step--choices">',
			'      <p class="politeia-pps__muted" style="margin-top:0">Elige cómo quieres pagar.</p>',
			'      <div class="politeia-pps__modal-actions">',
			'        <button type="button" class="politeia-pps__btn politeia-pps__btn--primary" data-pps-choice="card">Pagar con tarjeta</button>',
			'        <button type="button" class="politeia-pps__btn politeia-pps__btn--secondary" data-pps-choice="mp">Usar mi cuenta Mercado Pago</button>',
			'      </div>',
			'    </div>',
			'    <div class="politeia-pps__modal-step politeia-pps__modal-step--card" style="display:none">',
			'      <div class="politeia-pps__card-form" style="margin:0">',
			'        <div class="politeia-pps__grid">',
			'          <div class="politeia-pps__full"><label>Email</label><input type="email" name="cardholderEmail" autocomplete="email" placeholder="tu@email.com" /></div>',
			'          <div class="politeia-pps__full"><label>Número de tarjeta</label><input type="text" name="cardNumber" inputmode="numeric" autocomplete="cc-number" placeholder="4111 1111 1111 1111" /></div>',
			'          <div><label>Mes</label><input type="text" name="cardExpirationMonth" inputmode="numeric" autocomplete="cc-exp-month" placeholder="11" /></div>',
			'          <div><label>Año</label><input type="text" name="cardExpirationYear" inputmode="numeric" autocomplete="cc-exp-year" placeholder="2030" /></div>',
			'          <div><label>CVV</label><input type="text" name="securityCode" inputmode="numeric" autocomplete="cc-csc" placeholder="123" /></div>',
			'          <div><label>Nombre</label><input type="text" name="cardholderName" autocomplete="cc-name" placeholder="Nombre Apellido" /></div>',
			'          <div><label>Tipo doc</label><select name="identificationType"><option value="">(opcional)</option><option value="RUT">RUT</option><option value="DNI">DNI</option></select></div>',
			'          <div><label>Número doc</label><input type="text" name="identificationNumber" autocomplete="off" placeholder="12.345.678-9" /></div>',
			'        </div>',
			'        <div class="politeia-pps__hint">No almacenamos datos de tarjeta. Se tokeniza en el navegador usando Mercado Pago (se envía <code>card_token_id</code>).</div>',
			'      </div>',
			'      <div style="margin-top:12px" class="politeia-pps__modal-actions">',
			'        <button type="button" class="politeia-pps__btn politeia-pps__btn--primary" data-pps-card-submit>Confirmar pago</button>',
			'        <button type="button" class="politeia-pps__btn" data-pps-back>Volver</button>',
			'      </div>',
			'    </div>',
			'  </div>',
			'</div>'
		].join('');

		document.body.appendChild(overlay);

		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				hideOverlay();
			}
		});
		q('.politeia-pps__modal-close', overlay).addEventListener('click', hideOverlay);
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') hideOverlay();
		});

		return overlay;
	}

	function hideOverlay() {
		var overlay = q('.politeia-pps__modal-overlay');
		if (!overlay) return;
		overlay.style.display = 'none';
		setStatus('', false);
		showStep('choices');
		overlay.removeAttribute('data-tier-id');
		overlay.removeAttribute('data-hosted-url');
	}

	function setStatus(message, isError) {
		var overlay = q('.politeia-pps__modal-overlay');
		if (!overlay) return;
		var el = q('.politeia-pps__modal-status', overlay);
		if (!el) return;
		el.style.display = message ? 'block' : 'none';
		el.className = 'politeia-pps__modal-status ' + (isError ? 'politeia-pps__error' : 'politeia-pps__notice');
		el.textContent = message || '';
	}

	function showStep(step) {
		var overlay = q('.politeia-pps__modal-overlay');
		if (!overlay) return;
		var choices = q('.politeia-pps__modal-step--choices', overlay);
		var card = q('.politeia-pps__modal-step--card', overlay);
		if (!choices || !card) return;
		choices.style.display = step === 'choices' ? '' : 'none';
		card.style.display = step === 'card' ? '' : 'none';
	}

	function getCardValues(overlay) {
		function val(name) {
			var el = q('[name="' + name + '"]', overlay);
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

	async function createCardToken(values) {
		var cfg = window.PoliteiaPPSProfileSubscribe || {};
		var publicKey = cfg.publicKey ? String(cfg.publicKey) : '';
		if (!publicKey) throw new Error('Missing Mercado Pago public key.');

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
		if (!token || !token.id) throw new Error('Card tokenization failed.');
		return token;
	}

	function extractErrorMessage(data) {
		if (!data) return null;
		if (typeof data === 'string') return data;
		if (data.data && data.data.body && data.data.body.message) return String(data.data.body.message);
		if (data.message) return String(data.message);
		return null;
	}

	async function submitCard(overlay) {
		var cfg = window.PoliteiaPPSProfileSubscribe || {};
		if (!cfg.restUrl || !cfg.nonce) throw new Error('Missing REST config.');

		var tierId = Number(overlay.getAttribute('data-tier-id') || '0');
		if (!tierId) throw new Error('Missing tier id.');

		var values = getCardValues(overlay);
		if (!values.cardholderEmail) {
			setStatus('Email es requerido.', true);
			return;
		}
		if (!values.cardNumber || !values.cardExpirationMonth || !values.cardExpirationYear || !values.securityCode || !values.cardholderName) {
			setStatus('Completa los datos de la tarjeta.', true);
			return;
		}

		setStatus('Tokenizando tarjeta…', false);
		var token;
		try {
			token = await createCardToken(values);
		} catch (eTok) {
			setStatus((eTok && eTok.message) ? eTok.message : 'No se pudo tokenizar la tarjeta.', true);
			return;
		}

		setStatus('Creando suscripción…', false);
		var payload = {
			tier_id: tierId,
			payer_email: values.cardholderEmail,
			card_token_id: String(token.id)
		};
		if (token.payment_method_id) payload.payment_method_id = String(token.payment_method_id);
		if (token.issuer && token.issuer.id) payload.issuer_id = String(token.issuer.id);

		var res;
		try {
			res = await fetch(String(cfg.restUrl), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': String(cfg.nonce)
				},
				body: JSON.stringify(payload)
			});
		} catch (e) {
			setStatus('No se pudo conectar. Intenta de nuevo.', true);
			return;
		}

		var data = null;
		try { data = await res.json(); } catch (e2) {}
		if (!res.ok) {
			setStatus(extractErrorMessage(data) || 'No se pudo iniciar la suscripción.', true);
			return;
		}

		setStatus('Suscripción creada. Puedes cerrar esta ventana.', false);
	}

	function openOverlayForLink(link) {
		var overlay = ensureOverlay();
		overlay.style.display = 'flex';

		var href = String(link.getAttribute('href') || '');
		var url;
		try {
			url = new URL(href, window.location.origin);
		} catch (e) {
			url = null;
		}
		var tierId = url ? (url.searchParams.get('tier_id') || '') : '';
		overlay.setAttribute('data-tier-id', String(tierId || ''));
		overlay.setAttribute('data-hosted-url', href);

		showStep('choices');
		setStatus('', false);
	}

	document.addEventListener('click', function (e) {
		var link = closestSubscribeLink(e.target);
		if (!link) return;
		e.preventDefault();
		openOverlayForLink(link);
	});

	document.addEventListener('click', function (e) {
		var overlay = q('.politeia-pps__modal-overlay');
		if (!overlay || overlay.style.display === 'none') return;

		var btnChoice = e.target && e.target.closest ? e.target.closest('[data-pps-choice]') : null;
		if (btnChoice) {
			var choice = String(btnChoice.getAttribute('data-pps-choice') || '');
			if (choice === 'mp') {
				var hostedUrl = overlay.getAttribute('data-hosted-url');
				if (hostedUrl) window.location.href = hostedUrl;
				return;
			}
			if (choice === 'card') {
				showStep('card');
				return;
			}
		}

		var btnBack = e.target && e.target.closest ? e.target.closest('[data-pps-back]') : null;
		if (btnBack) {
			setStatus('', false);
			showStep('choices');
			return;
		}

		var btnSubmit = e.target && e.target.closest ? e.target.closest('[data-pps-card-submit]') : null;
		if (btnSubmit) {
			e.preventDefault();
			submitCard(overlay);
			return;
		}
	});
})();

