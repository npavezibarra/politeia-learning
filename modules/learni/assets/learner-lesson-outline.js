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
