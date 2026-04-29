(function () {
	function q(sel, root) {
		return (root || document).querySelector(sel);
	}

	function getQueryParam(name) {
		try {
			var u = new URL(window.location.href);
			return u.searchParams.get(name);
		} catch (e) {
			return null;
		}
	}

	function clearSubscribeErrorFromUrl() {
		try {
			var u = new URL(window.location.href);
			if (!u.searchParams.has('pl_subscribe_error')) return;
			u.searchParams.delete('pl_subscribe_error');
			window.history.replaceState({}, '', u.toString());
		} catch (e) {}
	}

	function hideInlineSubscribeErrorBanner() {
		var params = getQueryParam('pl_subscribe_error');
		if (!params) return;
		var nodes = document.querySelectorAll('body *');
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			if (!el || !el.textContent) continue;
			if (el.textContent.indexOf('Mercado Pago exige que payer y collector') !== -1) {
				el.style.display = 'none';
				break;
			}
		}
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
			'    <h3 class="politeia-pps__modal-title">Pago seguro</h3>',
			'    <button class="politeia-pps__modal-close" type="button" aria-label="Cerrar">×</button>',
			'  </div>',
			'  <div class="politeia-pps__modal-body">',
			'    <div class="politeia-pps__modal-status" aria-live="polite"></div>',
			'    <div class="politeia-pps__modal-step politeia-pps__modal-step--choices">',
			'      <p class="politeia-pps__muted" style="margin-top:0">Si tu email de Politeia no coincide con tu cuenta Mercado Pago, elige “Pagar con tarjeta” o inicia sesión en Mercado Pago al continuar.</p>',
			'      <div class="politeia-pps__modal-actions">',
			'        <button type="button" class="politeia-pps__btn politeia-pps__btn--primary" data-pps-choice="card">Pagar con tarjeta (recomendado)</button>',
			'        <button type="button" class="politeia-pps__btn politeia-pps__btn--secondary" data-pps-choice="mp">Mercado Pago (redirigir)</button>',
			'      </div>',
			'    </div>',
			'    <div class="politeia-pps__modal-step politeia-pps__modal-step--card" style="display:none">',
			'      <div class="politeia-pps__hint" style="margin-top:0">El formulario de tarjeta es provisto por Mercado Pago (PCI). Politeia recibe solo un token (<code>card_token_id</code>), nunca el número o CVV.</div>',
			'      <div id="pps_cardPaymentBrick_container"></div>',
			'      <div style="margin-top:12px" class="politeia-pps__modal-actions">',
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
		unmountBrick();
		overlay.style.display = 'none';
		setStatus('', false);
		showStep('choices');
		overlay.removeAttribute('data-tier-id');
		overlay.removeAttribute('data-hosted-url');
		overlay.removeAttribute('data-creator-id');
		clearSubscribeErrorFromUrl();
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

	function extractErrorMessage(data) {
		if (!data) return null;
		if (typeof data === 'string') return data;
		if (data.data && data.data.body && data.data.body.message) return String(data.data.body.message);
		if (data.message) return String(data.message);
		return null;
	}

	var brickController = null;
	var brickKey = '';
	var cachedTiersByCreator = {};

	function unmountBrick() {
		try {
			if (brickController && typeof brickController.unmount === 'function') {
				brickController.unmount();
			}
		} catch (e) {}
		brickController = null;
		brickKey = '';
		var c = q('#pps_cardPaymentBrick_container');
		if (c) c.innerHTML = '';
	}

	async function getTierAmount(creatorId, tierId) {
		var cfg = window.PoliteiaPPSProfileSubscribe || {};
		if (!cfg.tiersUrl) throw new Error('Missing tiersUrl.');
		creatorId = Number(creatorId || 0);
		tierId = Number(tierId || 0);
		if (!creatorId || !tierId) throw new Error('Missing creator/tier id.');

		if (!cachedTiersByCreator[creatorId]) {
			var url = String(cfg.tiersUrl) + (String(cfg.tiersUrl).indexOf('?') === -1 ? '?' : '&') + 'creator_id=' + encodeURIComponent(String(creatorId));
			var res = await fetch(url, { method: 'GET' });
			var data = await res.json();
			cachedTiersByCreator[creatorId] = (data && data.items) ? data.items : [];
		}

		var tiers = cachedTiersByCreator[creatorId] || [];
		for (var i = 0; i < tiers.length; i++) {
			if (Number(tiers[i].id) === tierId) {
				return Number(tiers[i].amount_minor || tiers[i].amount || 0);
			}
		}
		throw new Error('Tier not found.');
	}

	async function renderCardPaymentBrick(overlay) {
		var cfg = window.PoliteiaPPSProfileSubscribe || {};
		var publicKey = cfg.publicKey ? String(cfg.publicKey) : '';
		if (!publicKey) throw new Error('Missing Mercado Pago public key.');
		if (!cfg.restUrl || !cfg.nonce) throw new Error('Missing REST config.');

		var tierId = Number(overlay.getAttribute('data-tier-id') || '0');
		var creatorId = Number(overlay.getAttribute('data-creator-id') || '0');
		if (!tierId || !creatorId) throw new Error('Missing creator/tier id.');

		var amount = await getTierAmount(creatorId, tierId);
		if (!amount || amount <= 0) throw new Error('Invalid amount.');

		var key = String(creatorId) + ':' + String(tierId) + ':' + String(amount);
		if (brickController && brickKey === key) {
			return;
		}

		unmountBrick();
		brickKey = key;

		await loadScript('https://sdk.mercadopago.com/js/v2');
		if (typeof window.MercadoPago !== 'function') throw new Error('MercadoPago SDK not available.');

		var mp = new window.MercadoPago(publicKey, { locale: 'es-CL' });
		var bricksBuilder = mp.bricks();
		var settings = {
			initialization: {
				amount: amount,
			},
			customization: {
				visual: {
					style: {
						theme: 'default'
					}
				}
			},
			callbacks: {
				onReady: function () {
					setStatus('', false);
				},
				onError: function (error) {
					try { console.error(error); } catch (e) {}
					setStatus('No se pudo cargar el formulario de Mercado Pago. Intenta de nuevo.', true);
				},
				onSubmit: function (formData) {
					return new Promise(function (resolve, reject) {
						setStatus('Creando suscripción…', false);

						var payload = {
							tier_id: tierId,
							payer_email: formData && formData.payer && formData.payer.email ? String(formData.payer.email) : '',
							card_token_id: formData && formData.token ? String(formData.token) : '',
							payment_method_id: formData && (formData.payment_method_id || formData.paymentMethodId) ? String(formData.payment_method_id || formData.paymentMethodId) : '',
							issuer_id: formData && (formData.issuer_id || formData.issuerId) ? String(formData.issuer_id || formData.issuerId) : ''
						};

						fetch(String(cfg.restUrl), {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': String(cfg.nonce)
							},
							body: JSON.stringify(payload)
						})
							.then(function (resp) {
								return resp
									.json()
									.catch(function () { return null; })
									.then(function (data) { return { ok: resp.ok, status: resp.status, data: data }; });
							})
							.then(function (result) {
								if (!result.ok) {
									setStatus(extractErrorMessage(result.data) || 'No se pudo iniciar la suscripción.', true);
									reject();
									return;
								}
								setStatus('Suscripción creada. Puedes cerrar esta ventana.', false);
								resolve();
							})
							.catch(function () {
								setStatus('No se pudo conectar. Intenta de nuevo.', true);
								reject();
							});
					});
				}
			}
		};

		brickController = await bricksBuilder.create('cardPayment', 'pps_cardPaymentBrick_container', settings);
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
		var creatorId = url ? (url.searchParams.get('creator_user_id') || '') : '';
		overlay.setAttribute('data-tier-id', String(tierId || ''));
		overlay.setAttribute('data-creator-id', String(creatorId || ''));
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
				setStatus('Cargando Mercado Pago…', false);
				renderCardPaymentBrick(overlay).catch(function (err) {
					setStatus((err && err.message) ? err.message : 'No se pudo cargar Mercado Pago.', true);
				});
				return;
			}
		}

		var btnBack = e.target && e.target.closest ? e.target.closest('[data-pps-back]') : null;
		if (btnBack) {
			setStatus('', false);
			unmountBrick();
			showStep('choices');
			return;
		}
	});

	// If we landed here via hosted redirect error, hide the inline banner and show the overlay instead.
	document.addEventListener('DOMContentLoaded', function () {
		var err = getQueryParam('pl_subscribe_error');
		if (!err) return;
		hideInlineSubscribeErrorBanner();
		var overlay = ensureOverlay();
		overlay.style.display = 'flex';
		showStep('choices');
		setStatus('No se pudo iniciar la suscripción. Puedes pagar con tarjeta o intentar con Mercado Pago.', true);
	});
})();
