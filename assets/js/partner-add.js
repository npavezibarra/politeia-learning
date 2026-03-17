(function () {
  const cfg = window.PLPartnerAdd || {};
  const form = document.getElementById('partnerForm');
  if (!form) return;

  const memberInput = document.getElementById('memberInput');
  const resultsEl = document.getElementById('memberResults');
  const successEl = document.getElementById('partnerSuccess');
  const submitBtn = document.getElementById('partnerSubmit');

  let selectedUserId = null;
  let lastQuery = '';
  let searchTimer = null;

  function isValidEmail(s) {
    const v = String(s || '').trim();
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  function setSuccess(msg) {
    if (!successEl) return;
    successEl.textContent = msg || '';
    successEl.style.display = msg ? 'block' : 'none';
  }

  function clearResults() {
    if (!resultsEl) return;
    resultsEl.innerHTML = '';
    resultsEl.style.display = 'none';
  }

  function renderResults(users) {
    if (!resultsEl) return;

    resultsEl.innerHTML = '';

    const items = Array.isArray(users) ? users : [];
    if (items.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'pl-partner-add__result pl-partner-add__result--empty';
      empty.textContent = (cfg.i18n && cfg.i18n.notFound) ? cfg.i18n.notFound : 'No friends found';
      resultsEl.appendChild(empty);
      resultsEl.style.display = 'block';
      return;
    }

    items.forEach((u) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'pl-partner-add__result';
      item.textContent = u.name || '';
      item.addEventListener('click', () => {
        memberInput.value = u.name || '';
        selectedUserId = Number(u.id) || null;
        submitBtn.disabled = !selectedUserId;
        clearResults();
      });
      resultsEl.appendChild(item);
    });

    resultsEl.style.display = 'block';
  }

  async function doSearch(q) {
    if (!cfg.friendsSearchUrl) return;

    const url = new URL(cfg.friendsSearchUrl, window.location.origin);
    url.searchParams.set('q', q);

    const res = await fetch(url.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': cfg.nonce || '',
      },
    });

    if (!res.ok) {
      clearResults();
      return;
    }

    const data = await res.json();
    renderResults(data);
  }

  memberInput.addEventListener('input', (e) => {
    const q = String(e.target.value || '').trim();

    selectedUserId = null;
    submitBtn.disabled = !isValidEmail(q);
    setSuccess('');

    if (q.includes('@')) {
      clearResults();
      return;
    }

    if (q.length < 2) {
      clearResults();
      return;
    }

    if (q === lastQuery) return;
    lastQuery = q;

    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => doSearch(q), 200);
  });

  document.addEventListener('click', (e) => {
    if (!resultsEl || resultsEl.style.display === 'none') return;
    if (e.target === memberInput || resultsEl.contains(e.target)) return;
    clearResults();
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!cfg.courseId) return;

    submitBtn.disabled = true;

    if (selectedUserId) {
      if (!cfg.addPartnerUrl) return;

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
      if (data && data.success) {
        setSuccess((cfg.i18n && cfg.i18n.added) ? cfg.i18n.added : 'Partner assigned successfully');
        form.style.display = 'none';
        return;
      }

      submitBtn.disabled = false;
      return;
    }

    const email = String(memberInput.value || '').trim();
    if (!isValidEmail(email)) {
      submitBtn.disabled = false;
      return;
    }

    if (!cfg.invitePartnerUrl) {
      submitBtn.disabled = false;
      return;
    }

    const inviteRes = await fetch(cfg.invitePartnerUrl, {
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
      }),
    });

    const inviteData = await inviteRes.json().catch(() => ({}));
    if (inviteData && inviteData.success) {
      setSuccess((cfg.i18n && cfg.i18n.invited) ? cfg.i18n.invited : 'Invitation sent');
      form.style.display = 'none';
      return;
    }

    submitBtn.disabled = false;
  });
})();
