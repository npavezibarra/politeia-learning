(function () {
  const cfg = window.PLCoursePartner || {};

  const overlay = document.getElementById('plPartnerOverlay');
  if (!overlay) return;

  const modal = overlay.querySelector('.pl-partner-modal');
  const closeBtn = overlay.querySelector('.pl-partner-modal__close');
  const toggle = overlay.querySelector('.pl-partner-toggle');
  const toggleButtons = overlay.querySelectorAll('.pl-partner-toggle__option');
  const membersField = overlay.querySelector('[data-mode="members"]');
  const nonMembersField = overlay.querySelector('[data-mode="non-members"]');

  const form = document.getElementById('plPartnerForm');
  const submitBtn = document.getElementById('plPartnerSubmit');
  const errorEl = document.getElementById('plPartnerError');
  const memberErrorEl = document.getElementById('plPartnerMemberError');

  const memberInput = document.getElementById('plPartnerMemberInput');
  const memberResults = document.getElementById('plPartnerMemberResults');
  const firstNameInput = document.getElementById('plPartnerFirstName');
  const lastNameInput = document.getElementById('plPartnerLastName');
  const emailInput = document.getElementById('plPartnerEmail');

  const formContent = document.getElementById('plPartnerFormContent');
  const successContent = document.getElementById('plPartnerSuccessContent');
  const successMsg = document.getElementById('plPartnerSuccessMessage');

  let mode = 'members';
  let selectedUserId = null;
  let lastQuery = '';
  let timer = null;

  function isValidEmail(s) {
    const v = String(s || '').trim();
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  function setHidden(el, hidden) {
    if (!el) return;
    el.hidden = !!hidden;
  }

  function setError(msg) {
    if (!errorEl) return;
    errorEl.textContent = msg || '';
    setHidden(errorEl, !msg);
  }

  function setMemberError(show) {
    if (!memberErrorEl) return;
    setHidden(memberErrorEl, !show);
  }

  function clearResults() {
    if (!memberResults) return;
    memberResults.innerHTML = '';
    memberResults.style.display = 'none';
  }

  function renderResults(users) {
    if (!memberResults) return;
    memberResults.innerHTML = '';

    const items = Array.isArray(users) ? users : [];
    items.forEach((u) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = u.name || '';
      btn.addEventListener('click', () => {
        memberInput.value = u.name || '';
        selectedUserId = Number(u.id) || null;
        if (submitBtn) submitBtn.disabled = !selectedUserId;
        clearResults();
        setMemberError(false);
      });
      memberResults.appendChild(btn);
    });

    memberResults.style.display = items.length ? 'block' : 'none';
  }

  async function searchFriends(q) {
    if (!cfg.friendsSearchUrl) return;

    const url = new URL(cfg.friendsSearchUrl, window.location.origin);
    url.searchParams.set('q', q);

    const res = await fetch(url.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': cfg.nonce || '' },
    });

    if (!res.ok) {
      clearResults();
      return;
    }

    const data = await res.json();
    renderResults(data);
  }

  function resetModal() {
    // Default mode on open.
    try {
      setMode('members');
    } catch (e) {
      // ignore
    }

    selectedUserId = null;
    lastQuery = '';
    setError('');
    setMemberError(false);
    clearResults();
    if (memberInput) memberInput.value = '';
    if (firstNameInput) firstNameInput.value = '';
    if (lastNameInput) lastNameInput.value = '';
    if (emailInput) emailInput.value = '';
    if (submitBtn) submitBtn.disabled = true;
    setHidden(formContent, false);
    setHidden(successContent, true);
  }

  function openModal() {
    try {
      resetModal();
    } catch (e) {
      // Still open the overlay even if some optional elements are missing.
    }
    overlay.hidden = false;
    overlay.classList.add('is-open');
  }

  function closeModal() {
    overlay.classList.remove('is-open');
    overlay.hidden = true;
  }

  function setMode(nextMode) {
    mode = nextMode === 'non-members' ? 'non-members' : 'members';
    if (toggle) toggle.setAttribute('data-state', mode);

    toggleButtons.forEach((b) => {
      const opt = b.getAttribute('data-option');
      b.classList.toggle('is-active', opt === mode);
    });

    setHidden(membersField, mode !== 'members');
    setHidden(nonMembersField, mode !== 'non-members');

    if (submitBtn) submitBtn.disabled = true;
    if (submitBtn && cfg.i18n) {
      submitBtn.textContent =
        mode === 'non-members' ? cfg.i18n.submitInvite || submitBtn.textContent : cfg.i18n.submitMembers || submitBtn.textContent;
    }
    setError('');
    setMemberError(false);
    clearResults();
    selectedUserId = null;
  }

  // Capture phase to avoid other scripts stopping propagation on the button.
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.addPartnerBtn') : null;
    if (!btn) return;
    e.preventDefault();
    openModal();
  }, true);

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', (e) => {
    if (!overlay.hidden && e.key === 'Escape') closeModal();
  });

  toggleButtons.forEach((b) => {
    b.addEventListener('click', () => setMode(b.getAttribute('data-option')));
  });

  if (memberInput) memberInput.addEventListener('input', (e) => {
    if (mode !== 'members') return;
    const q = String(e.target.value || '').trim();

    selectedUserId = null;
    if (submitBtn) submitBtn.disabled = true;
    setError('');
    setMemberError(false);

    if (q.length < 2) {
      clearResults();
      return;
    }

    if (q === lastQuery) return;
    lastQuery = q;

    if (timer) window.clearTimeout(timer);
    timer = window.setTimeout(() => searchFriends(q), 200);
  });

  function updateNonMemberSubmitState() {
    if (mode !== 'non-members') return;
    const first = String(firstNameInput ? firstNameInput.value : '').trim();
    const last = String(lastNameInput ? lastNameInput.value : '').trim();
    const email = String(emailInput ? emailInput.value : '').trim();
    const ok = first.length > 0 && last.length > 0 && isValidEmail(email);
    if (submitBtn) submitBtn.disabled = !ok;
  }

  if (firstNameInput) firstNameInput.addEventListener('input', () => {
    setError('');
    updateNonMemberSubmitState();
  });
  if (lastNameInput) lastNameInput.addEventListener('input', () => {
    setError('');
    updateNonMemberSubmitState();
  });
  if (emailInput) {
    emailInput.addEventListener('input', () => {
      setError('');
      updateNonMemberSubmitState();
    });
  }

  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setError('');

    if (!cfg.courseId) return;
    if (submitBtn) submitBtn.disabled = true;

    try {
      if (mode === 'members') {
        if (!selectedUserId) {
          setMemberError(true);
          if (submitBtn) submitBtn.disabled = false;
          return;
        }

        const res = await fetch(cfg.addPartnerUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce || '',
          },
          body: JSON.stringify({
            object_type: 'course',
            object_id: Number(cfg.courseId),
            user_id: Number(selectedUserId),
          }),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data || !data.success) {
          setError('Could not add partner.');
          if (submitBtn) submitBtn.disabled = false;
          return;
        }

        successMsg.textContent = 'Partner assigned successfully';
      } else {
        const first_name = String(firstNameInput ? firstNameInput.value : '').trim();
        const last_name = String(lastNameInput ? lastNameInput.value : '').trim();
        const email = String(emailInput ? emailInput.value : '').trim();

        if (!first_name || !last_name) {
          setError('Please enter first and last name.');
          if (submitBtn) submitBtn.disabled = false;
          return;
        }

        if (!isValidEmail(email)) {
          setError('Invalid email.');
          if (submitBtn) submitBtn.disabled = false;
          return;
        }

        const res = await fetch(cfg.invitePartnerUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce || '',
          },
          body: JSON.stringify({
            object_type: 'course',
            object_id: Number(cfg.courseId),
            email,
            first_name,
            last_name,
          }),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data || !data.success) {
          setError('Could not send invitation.');
          if (submitBtn) submitBtn.disabled = false;
          return;
        }

        successMsg.textContent = 'Invitation sent.';
      }

      setHidden(formContent, true);
      setHidden(successContent, false);
      window.setTimeout(closeModal, 1500);
    } catch (err) {
      setError('Something went wrong.');
      if (submitBtn) submitBtn.disabled = false;
    }
  });
})();
