(function () {
    if (typeof window === "undefined") return;

    const I18N = window.PRS_NOTES_I18N || {};
    const t = (key, fallback) => (I18N && I18N[key]) ? I18N[key] : fallback;

    const modal = document.getElementById("prs-note-modal");
    const rowsWrap = document.getElementById("prs-note-rows");
    const resetBtn = document.getElementById("prs-note-reset");
    const saveBtn = document.getElementById("prs-note-save");
    const noteButtons = document.querySelectorAll(".prs-note__rate-button");
    const editButtons = document.querySelectorAll(".prs-note__edit-button");
    const deleteButtons = document.querySelectorAll(".prs-note__delete-button");
    const visibilityToggles = document.querySelectorAll(".prs-note__visibility-toggle");

    if (!modal || !rowsWrap || !resetBtn || !saveBtn) return;

    const EMOTIONS = [
        { id: "joy", label: t("emotion_joy", "Joy"), emoji: "😄", color: "joy" },
        { id: "sorrow", label: t("emotion_sorrow", "Sorrow"), emoji: "😢", color: "sorrow" },
        { id: "fear", label: t("emotion_fear", "Fear"), emoji: "😱", color: "fear" },
        { id: "fascination", label: t("emotion_fascination", "Fascination"), emoji: "🤯", color: "fascination" },
        { id: "anger", label: t("emotion_anger", "Anger"), emoji: "😡", color: "anger" },
        { id: "serenity", label: t("emotion_serenity", "Serenity"), emoji: "😌", color: "serenity" },
        { id: "enlightenment", label: t("emotion_enlightenment", "Enlightenment"), emoji: "✨", color: "enlightenment" },
    ];

    let activeNoteId = null;
    let ratings = {};
    let submitted = false;
    const savingNoteIds = new Set();
    const deletingNoteIds = new Set();
    const visibilityBusy = new Set();

    function resolveAjaxUrl() {
        return window.ajaxurl || (window.PRS_BOOK && window.PRS_BOOK.ajax_url) || "";
    }

    function resolveReadingNonce() {
        return (window.PRS_BOOK && window.PRS_BOOK.reading_nonce) || (window.PRS_SR && window.PRS_SR.nonce) || "";
    }

    function htmlFromPlainText(value) {
        const text = (typeof value === "string") ? value : "";
        const escaped = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
        const withBreaks = escaped.replace(/\r\n|\r|\n/g, "<br>");
        return `<p>${withBreaks}</p>`;
    }

    function setButtonsDisabled(root, disabled) {
        if (!root) return;
        root.querySelectorAll("button, input[type=\"checkbox\"]").forEach((el) => {
            if (disabled) {
                el.setAttribute("disabled", "disabled");
            } else {
                el.removeAttribute("disabled");
            }
        });
    }

    function resetRatings() {
        ratings = { joy: 0, sorrow: 0, fear: 0, fascination: 0, anger: 0, serenity: 0, enlightenment: 0 };
        submitted = false;
    }

    function totalIntensity() {
        return Object.values(ratings).reduce((a, b) => a + b, 0);
    }

    function buildRows() {
        rowsWrap.innerHTML = "";
        EMOTIONS.forEach(({ id, label, emoji, color }) => {
            const row = document.createElement("div");
            row.innerHTML = `
                <div class="prs-note-modal__row-header">
                    <div class="prs-note-modal__label"><span>${emoji}</span><span>${label}</span></div>
                    <span class="prs-note-modal__count" data-emotion="${id}"></span>
                </div>
                <div class="prs-note-modal__bar"></div>
            `;
            const bar = row.querySelector(".prs-note-modal__bar");
            for (let i = 1; i <= 5; i++) {
                const segment = document.createElement("button");
                segment.type = "button";
                segment.className = `prs-note-modal__segment prs-note-modal__segment--${color}`;
                segment.dataset.emotion = id;
                segment.dataset.value = String(i);
                segment.addEventListener("click", () => {
                    const value = parseInt(segment.dataset.value, 10);
                    ratings[id] = ratings[id] === value ? 0 : value;
                    submitted = false;
                    renderState();
                });
                bar.appendChild(segment);
            }
            rowsWrap.appendChild(row);
        });
    }

    function renderState() {
        document.querySelectorAll(".prs-note-modal__segment").forEach((el) => {
            const emotion = el.dataset.emotion;
            const value = parseInt(el.dataset.value, 10);
            el.classList.toggle("is-active", ratings[emotion] >= value);
        });
        document.querySelectorAll(".prs-note-modal__count").forEach((el) => {
            const val = ratings[el.dataset.emotion] || 0;
            el.textContent = val > 0 ? `${val}/5` : "";
        });

        const total = totalIntensity();
        saveBtn.classList.toggle("is-success", submitted);
        saveBtn.disabled = !submitted && total === 0;
        saveBtn.classList.toggle("is-disabled", !submitted && total === 0);
        saveBtn.textContent = submitted ? t("logged_impression", "Logged Impression") : t("save_rating", "Save Emotional Rating");
    }

    function openModal(noteId, currentEmotions) {
        activeNoteId = noteId;
        ratings = currentEmotions || { joy: 0, sorrow: 0, fear: 0, fascination: 0, anger: 0, serenity: 0, enlightenment: 0 };
        submitted = false;
        if (!rowsWrap.children.length) buildRows();
        renderState();
        modal.classList.add("is-active");
    }

    function closeModal() {
        modal.classList.remove("is-active");
        activeNoteId = null;
    }

    function renderComposition(noteId, emotionRatings) {
        const article = document.querySelector(`.prs-note[data-note-id="${noteId}"]`);
        if (!article) return;
        const compWrap = article.querySelector(".prs-note__composition");
        if (!compWrap) return;

        const total = Object.values(emotionRatings).reduce((a, b) => a + b, 0);
        compWrap.innerHTML = "";
        compWrap.classList.toggle("is-empty", total === 0);

        if (total > 0) {
            Object.entries(emotionRatings).forEach(([key, val]) => {
                if (val > 0) {
                    const seg = document.createElement("div");
                    seg.className = `prs-note__composition-seg--${key}`;
                    seg.style.width = `${((val / total) * 100).toFixed(2)}%`;
                    compWrap.appendChild(seg);
                }
            });
        }
    }

    // Events
    noteButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            const id = btn.dataset.noteId;
            let emotions = {};
            try { emotions = JSON.parse(btn.dataset.emotions || "{}"); } catch(e) {}
            openModal(id, emotions);
        });
    });

    modal.addEventListener("click", (e) => { if (e.target === modal) closeModal(); });
    resetBtn.addEventListener("click", () => { resetRatings(); renderState(); });

    saveBtn.addEventListener("click", async () => {
        if (saveBtn.disabled || !activeNoteId) return;
        const ajaxUrl = resolveAjaxUrl();
        const nonce = resolveReadingNonce();
        if (!ajaxUrl || !nonce) return;

        const payload = new FormData();
        payload.append("action", "politeia_save_note_emotions");
        payload.append("nonce", nonce);
        payload.append("note_id", String(activeNoteId));
        payload.append("emotions", JSON.stringify(ratings));

        try {
            const res = await fetch(ajaxUrl, { method: "POST", body: payload, credentials: "same-origin" });
            const json = await res.json();
            if (json?.success) {
                submitted = true;
                renderState();
                const btn = document.querySelector(`.prs-note__rate-button[data-note-id="${activeNoteId}"]`);
                if (btn) btn.dataset.emotions = JSON.stringify(ratings);
                renderComposition(activeNoteId, ratings);
                setTimeout(closeModal, 800);
            }
        } catch (e) { console.error(e); }
    });

    // Auto-resize textareas
    document.querySelectorAll(".prs-note__text").forEach(textarea => {
        textarea.style.height = "auto";
        textarea.style.height = `${textarea.scrollHeight}px`;
    });

    // Edit note (inline)
    editButtons.forEach((btn) => {
        btn.addEventListener("click", async () => {
            const article = btn.closest(".prs-note");
            if (!article) return;
            const noteId = article.dataset.noteId || "";
            const rsId = article.dataset.rsId || "";

            const content = article.querySelector(".prs-note__content");
            const textarea = article.querySelector(".prs-note__text");
            if (!content || !textarea || !noteId || !rsId) return;

            const isEditing = article.classList.contains("is-editing");
            if (!isEditing) {
                article.classList.add("is-editing");
                textarea.hidden = false;
                textarea.readOnly = false;
                textarea.value = textarea.value || content.textContent || "";
                content.style.display = "none";
                btn.textContent = t("save", "Save");
                textarea.focus();
                textarea.style.height = "auto";
                textarea.style.height = `${textarea.scrollHeight}px`;
                return;
            }

            if (savingNoteIds.has(noteId)) return;
            savingNoteIds.add(noteId);

            const ajaxUrl = resolveAjaxUrl();
            const nonce = resolveReadingNonce();
            if (!ajaxUrl || !nonce) {
                savingNoteIds.delete(noteId);
                return;
            }

            btn.setAttribute("aria-busy", "true");
            btn.setAttribute("disabled", "disabled");

            const payload = new FormData();
            payload.append("action", "politeia_save_session_note");
            payload.append("nonce", nonce);
            payload.append("rs_id", String(rsId));
            payload.append("note", htmlFromPlainText(textarea.value || ""));
            if (window.PRS_BOOK && window.PRS_BOOK.user_book_id) {
                payload.append("user_book_id", String(window.PRS_BOOK.user_book_id));
            }
            if (window.PRS_BOOK && window.PRS_BOOK.book_id) {
                payload.append("book_id", String(window.PRS_BOOK.book_id));
            }
            if (window.PRS_BOOK && window.PRS_BOOK.user_id) {
                payload.append("user_id", String(window.PRS_BOOK.user_id));
            }

            try {
                const res = await fetch(ajaxUrl, { method: "POST", body: payload, credentials: "same-origin" });
                const json = await res.json();
                if (json?.success) {
                    content.innerHTML = htmlFromPlainText(textarea.value || "");
                    content.style.display = "";
                    textarea.hidden = true;
                    textarea.readOnly = true;
                    article.classList.remove("is-editing");
                    btn.textContent = t("edit", "Edit");
                }
            } catch (e) {
                console.error(e);
            } finally {
                savingNoteIds.delete(noteId);
                btn.removeAttribute("aria-busy");
                btn.removeAttribute("disabled");
            }
        });
    });

    // Delete note
    deleteButtons.forEach((btn) => {
        btn.addEventListener("click", async () => {
            const noteId = btn.dataset.noteId || "";
            const article = btn.closest(".prs-note");
            if (!noteId || !article) return;
            if (deletingNoteIds.has(noteId)) return;

            if (!window.confirm(t("confirm_delete", "Delete this note?"))) {
                return;
            }

            const ajaxUrl = resolveAjaxUrl();
            const nonce = resolveReadingNonce();
            if (!ajaxUrl || !nonce) return;

            deletingNoteIds.add(noteId);
            setButtonsDisabled(article, true);

            const payload = new FormData();
            payload.append("action", "politeia_delete_session_note");
            payload.append("nonce", nonce);
            payload.append("note_id", String(noteId));

            try {
                const res = await fetch(ajaxUrl, { method: "POST", body: payload, credentials: "same-origin" });
                const json = await res.json();
                if (json?.success) {
                    article.remove();
                }
            } catch (e) {
                console.error(e);
            } finally {
                deletingNoteIds.delete(noteId);
                // If not removed, re-enable
                if (article && article.isConnected) {
                    setButtonsDisabled(article, false);
                }
            }
        });
    });

    // Visibility toggle (private/public)
    visibilityToggles.forEach((toggle) => {
        toggle.addEventListener("change", async () => {
            const noteId = toggle.dataset.noteId || "";
            const article = toggle.closest(".prs-note");
            if (!noteId || !article) return;
            if (visibilityBusy.has(noteId)) return;

            const ajaxUrl = resolveAjaxUrl();
            const nonce = resolveReadingNonce();
            if (!ajaxUrl || !nonce) return;

            const nextVisibility = toggle.checked ? "private" : "public";

            visibilityBusy.add(noteId);
            toggle.setAttribute("disabled", "disabled");

            const payload = new FormData();
            payload.append("action", "politeia_update_note_visibility");
            payload.append("nonce", nonce);
            payload.append("note_id", String(noteId));
            payload.append("visibility", nextVisibility);

            try {
                const res = await fetch(ajaxUrl, { method: "POST", body: payload, credentials: "same-origin" });
                const json = await res.json();
                if (json?.success) {
                    article.dataset.noteVisibility = nextVisibility;
                } else {
                    toggle.checked = !toggle.checked;
                }
            } catch (e) {
                console.error(e);
                toggle.checked = !toggle.checked;
            } finally {
                visibilityBusy.delete(noteId);
                toggle.removeAttribute("disabled");
            }
        });
    });

})();
