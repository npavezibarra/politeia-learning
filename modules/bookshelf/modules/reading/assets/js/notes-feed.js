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
        const ajaxUrl = window.ajaxurl || (window.PRS_BOOK && PRS_BOOK.ajax_url) || "";
        const nonce = (window.PRS_SR && PRS_SR.nonce) || "";
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

})();
