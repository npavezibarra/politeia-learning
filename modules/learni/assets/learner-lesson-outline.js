(() => {
	const root = document.querySelector('.learni-lesson-layout');
	const fab = document.querySelector('.learni-outline-fab');
	const overlay = document.getElementById('learni-lesson-outline-overlay');
	if (!root || !fab || !overlay) return;

	const backdrop = overlay.querySelector('.learni-outline-overlay-backdrop');
	const panel = overlay.querySelector('.learni-outline-overlay-panel');

	const setExpanded = (isOpen) => {
		fab.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		overlay.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
		root.classList.toggle('is-outline-open', isOpen);
		// Ensure the FAB disappears whenever the overlay is on-screen.
		fab.style.display = isOpen ? 'none' : '';
	};

	const isOpen = () => root.classList.contains('is-outline-open');

	const open = () => {
		if (isOpen()) return;
		setExpanded(true);
		if (panel) panel.focus?.();
	};

	const close = () => {
		if (!isOpen()) return;
		setExpanded(false);
		fab.focus?.();
	};

	fab.addEventListener('click', () => {
		isOpen() ? close() : open();
	});

	backdrop?.addEventListener('click', close);

	overlay.addEventListener('click', (e) => {
		const target = e.target;
		if (!(target instanceof HTMLElement)) return;
		const link = target.closest('a');
		if (link) close();
	});

	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape') close();
	});

	// Ensure a clean initial state.
	setExpanded(false);
})();

(() => {
	const courseId = document.querySelector('[data-learni-course-id]')?.getAttribute('data-learni-course-id') || '';
	if (!courseId) return;

	const apiFetch = window?.LearniQuiz?.apiFetch;
	const startQuiz = window?.LearniQuiz?.startBinomialQuiz;
	if (typeof apiFetch !== 'function') return;

	const escapeHtml = (s) =>
		String(s || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');

	const ensureFinalOverlay = () => {
		const existing = document.getElementById('learni-final-overlay');
		if (existing) return existing;

		const el = document.createElement('div');
		el.id = 'learni-final-overlay';
		el.className = 'learni-final-overlay';
		el.innerHTML =
			'<div class="learni-final-overlay__backdrop" data-learni-final-close="1"></div>' +
			'<div class="learni-final-overlay__panel" role="dialog" aria-modal="true" aria-label="Course completed">' +
			'<div class="learni-final-overlay__body" id="learni-final-overlay-body"></div>' +
			'</div>';
		document.body.appendChild(el);

		el.addEventListener('click', (e) => {
			const t = e.target;
			if (!(t instanceof HTMLElement)) return;
			const close = t.getAttribute('data-learni-final-close');
			if (close) hideFinalOverlay();
		});

		return el;
	};

	const showFinalOverlay = (html) => {
		const o = ensureFinalOverlay();
		const body = document.getElementById('learni-final-overlay-body');
		if (body) body.innerHTML = html || '';
		o.classList.add('is-open');
		document.body.style.overflow = 'hidden';
	};

	const hideFinalOverlay = () => {
		const o = ensureFinalOverlay();
		o.classList.remove('is-open');
		document.body.style.overflow = '';
		const body = document.getElementById('learni-final-overlay-body');
		if (body) body.innerHTML = '';
	};

	const maybeShowFinalQuizOverlay = () => {
		apiFetch(`/learni/v1/courses/${courseId}/binomial`, { method: 'GET' })
			.then((res) => res.json().then((data) => (res.ok ? data : null)))
			.then((data) => {
				if (!data || !data.quizId || !data.ui) return;
				// If the course has a partner, final quiz should run via "Test Partner" instead of self-evaluation.
				if (data.partner && data.partner.hasPartner) return;
				if (!data.ui.needsFinal) return;
				if (!(data.progress && Number(data.progress.lessonsPercent || 0) >= 100)) return;
				if (!data.ui.canTakeFinal) return;

				const titleNode = document.getElementById('learni-lesson-course-title');
				let courseTitle = titleNode ? titleNode.textContent : '';
				courseTitle = (courseTitle || '').trim() || 'este curso';

				const html =
					'<div class="learni-final-overlay__title">Felicitaciones</div>' +
					'<div class="learni-final-overlay__text">Haz completado todas las lecciones del curso <strong>' +
					escapeHtml(courseTitle) +
					'</strong>. Para obtener el certificado de finalización tienes que tomar el Final Quiz.</div>' +
					'<div class="learni-final-overlay__actions">' +
					'<button type="button" class="learni-btn learni-course-primary-btn" id="learni-final-overlay-take">TAKE FINAL QUIZ</button>' +
					'<button type="button" class="learni-btn secondary learni-course-primary-btn" data-learni-final-close="1">OTRO DÍA</button>' +
					'</div>';

				showFinalOverlay(html);

				const take = document.getElementById('learni-final-overlay-take');
				if (take) {
					take.addEventListener('click', () => {
						hideFinalOverlay();
						if (typeof startQuiz === 'function') startQuiz(courseId, 'final');
					});
				}
			})
			.catch(() => {});
	};

	const setupYoutubeCompletionGate = () => {
		const videoBox = document.getElementById('learni-lesson-video');
		const btn = document.querySelector('.learni-lesson-complete-btn');
		if (!videoBox || !btn) return;

		const provider = videoBox.getAttribute('data-learni-video-provider') || '';
		const requiresVideo = btn.getAttribute('data-learni-requires-video') === '1';
		if (!requiresVideo || provider !== 'youtube') return;

		const iframe = document.getElementById('learni-youtube-player');
		if (!iframe) return;

		const unlock = () => {
			if (btn.disabled) btn.disabled = false;
		};

		const loadYouTubeApi = () => {
			if (window.YT && window.YT.Player) return Promise.resolve();

			return new Promise((resolve, reject) => {
				const existing = document.querySelector('script[data-learni-youtube-api="1"]');
				if (existing) {
					const maxWait = 10000;
					const started = Date.now();
					(function poll() {
						if (window.YT && window.YT.Player) return resolve();
						if (Date.now() - started > maxWait) return reject(new Error('YT API timeout'));
						window.setTimeout(poll, 200);
					})();
					return;
				}

				const tag = document.createElement('script');
				tag.src = 'https://www.youtube.com/iframe_api';
				tag.async = true;
				tag.setAttribute('data-learni-youtube-api', '1');
				tag.onerror = () => reject(new Error('YT API load failed'));

				const prev = window.onYouTubeIframeAPIReady;
				window.onYouTubeIframeAPIReady = () => {
					if (typeof prev === 'function') prev();
					resolve();
				};

				document.head.appendChild(tag);
			});
		};

		loadYouTubeApi()
			.then(() => {
				if (!window.YT || !window.YT.Player) return;
				// eslint-disable-next-line no-new
				new window.YT.Player(iframe, {
					events: {
						onStateChange: (event) => {
							if (event && event.data === 0) unlock();
						},
					},
				});
			})
			.catch(() => {});
	};

	// Run on load: if lessons are 100% and final quiz is pending, show overlay.
	maybeShowFinalQuizOverlay();
	setupYoutubeCompletionGate();
})();
