(function () {
  "use strict";

  var cfg = window.LearniCrossEval || {};
  if (!cfg || !cfg.restUrl || !cfg.restNonce) return;

  var i18n = (cfg && cfg.i18n) || {};
  function t(key, fallback) {
    if (i18n && i18n[key]) return String(i18n[key]);
    return String(fallback || "");
  }

  function restUrl(path) {
    var base = String(cfg.restUrl || "");
    if (!base) return path;
    if (base[base.length - 1] === "/") base = base.slice(0, -1);
    if (path && path[0] !== "/") path = "/" + path;
    return base + path;
  }

  function apiFetch(path, opts) {
    opts = opts || {};
    var headers = opts.headers || {};
    headers["X-WP-Nonce"] = String(cfg.restNonce);
    if (!headers["Content-Type"]) headers["Content-Type"] = "application/json";
    opts.headers = headers;
    return fetch(restUrl(path), opts);
  }

  function fetchJson(res, fallback) {
    return res
      .json()
      .catch(function () {
        return {};
      })
      .then(function (data) {
        if (!res.ok) {
          var msg = (data && data.message) || fallback || "Request failed";
          throw new Error(msg);
        }
        return data;
      });
  }

  var state = {
    open: false,
    busy: false,
    activeId: 0,
    activeCourseId: 0,
  };

  var WATCH_KEY = "learni_cross_eval_watch";

  function loadWatch() {
    try {
      var raw = window.localStorage ? window.localStorage.getItem(WATCH_KEY) : "";
      if (!raw) return null;
      var obj = JSON.parse(raw);
      if (!obj || typeof obj !== "object") return null;
      var sid = Number(obj.sessionId || 0);
      var cid = Number(obj.courseId || 0);
      if (!sid || !cid) return null;
      return { sessionId: sid, courseId: cid };
    } catch (e) {
      return null;
    }
  }

  function saveWatch(watch) {
    try {
      if (!window.localStorage) return;
      if (!watch) {
        window.localStorage.removeItem(WATCH_KEY);
        return;
      }
      window.localStorage.setItem(WATCH_KEY, JSON.stringify(watch));
    } catch (e) {}
  }

  var watchState = loadWatch();

  function format(template, vars) {
    var out = String(template || "");
    if (!vars) return out;
    Object.keys(vars).forEach(function (key) {
      var val = vars[key];
      out = out.split("{" + key + "}").join(String(val == null ? "" : val));
    });
    return out;
  }

  function ensureModal() {
    var existing = document.getElementById("learni-cross-eval-popup");
    if (existing) return existing;

    var el = document.createElement("div");
    el.id = "learni-cross-eval-popup";
    el.className = "learni-cross-eval-popup";
    el.innerHTML =
      '<div class="learni-cross-eval-popup__backdrop" data-learni-cross-eval-close="1"></div>' +
      '<div class="learni-cross-eval-popup__panel" role="dialog" aria-modal="true" aria-label="' +
      String(t("title", "Test Partner")) +
      '">' +
      '<div class="learni-cross-eval-popup__head">' +
      '<div class="learni-cross-eval-popup__title">' +
      String(t("title", "Test Partner")) +
      "</div>" +
      '<button type="button" class="learni-cross-eval-popup__close" data-learni-cross-eval-close="1" aria-label="Close">×</button>' +
      "</div>" +
      '<div class="learni-cross-eval-popup__body" id="learni-cross-eval-popup-body"></div>' +
      '<div class="learni-cross-eval-popup__actions">' +
      '<button type="button" class="learni-cross-eval-popup__btn" id="learni-cross-eval-decline">' +
      String(t("decline", "Decline")) +
      "</button>" +
      '<button type="button" class="learni-cross-eval-popup__btn primary" id="learni-cross-eval-accept">' +
      String(t("accept", "Accept")) +
      "</button>" +
      "</div>" +
      "</div>";
    document.body.appendChild(el);

    el.addEventListener("click", function (e) {
      var close =
        e &&
        e.target &&
        e.target.getAttribute &&
        e.target.getAttribute("data-learni-cross-eval-close");
      if (close) hideModal();
    });

    return el;
  }

  function setModalBody(html) {
    var body = document.getElementById("learni-cross-eval-popup-body");
    if (body) body.innerHTML = html || "";
  }

  function showModal(session) {
    var el = ensureModal();
    state.open = true;
    state.activeId = session && session.id ? Number(session.id) : 0;
    state.activeCourseId = session && session.courseId ? Number(session.courseId) : 0;
    el.classList.add("is-open");

    var initiatorName = (session && session.initiatorName) ? String(session.initiatorName) : "";
    var courseTitle = (session && session.courseTitle) ? String(session.courseTitle) : "";

    var onlineLine = format(String(t("online", "{name} está online")), {
      name: initiatorName || "Tu partner",
    });
    var questionLine = format(String(t("question", "¿Aceptas tomar la Evaluación Final de {course} ahora?")), {
      course: courseTitle || "este curso",
    });

    var avatar = "";
    if (session && session.initiatorAvatarUrl) {
      avatar =
        '<img class="learni-cross-eval-popup__avatar" alt="" src="' +
        escapeHtml(String(session.initiatorAvatarUrl)) +
        '"/>';
    }

    setModalBody(
      '<div class="learni-cross-eval-popup__center">' +
        avatar +
        '<div class="learni-cross-eval-popup__online">' +
        escapeHtml(onlineLine) +
        "</div>" +
        '<div class="learni-cross-eval-popup__question">' +
        escapeHtml(questionLine) +
        "</div>" +
      "</div>"
    );

    var acceptBtn = document.getElementById("learni-cross-eval-accept");
    var declineBtn = document.getElementById("learni-cross-eval-decline");

    function setBusy(b) {
      state.busy = !!b;
      if (acceptBtn) acceptBtn.disabled = state.busy;
      if (declineBtn) declineBtn.disabled = state.busy;
    }

    function respond(decision) {
      if (!state.activeId || state.busy) return;
      setBusy(true);
      apiFetch("/learni/v1/cross-eval/sessions/" + String(state.activeId) + "/respond", {
        method: "POST",
        body: JSON.stringify({ decision: decision }),
      })
        .then(function (res) {
          return fetchJson(res, "Failed to respond");
        })
        .then(function () {
          if (decision === "accept" && state.activeId && state.activeCourseId) {
            watchState = { sessionId: Number(state.activeId), courseId: Number(state.activeCourseId) };
            saveWatch(watchState);
          }
          hideModal();
        })
        .catch(function (err) {
          setBusy(false);
          alert((err && err.message) || "Failed to respond");
        });
    }

    if (acceptBtn) acceptBtn.onclick = function () { respond("accept"); };
    if (declineBtn) declineBtn.onclick = function () { respond("decline"); };
  }

  function hideModal() {
    var el = ensureModal();
    el.classList.remove("is-open");
    state.open = false;
    state.busy = false;
    state.activeId = 0;
    state.activeCourseId = 0;
  }

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function maybeShowResults(courseId) {
    try {
      if (window.LearniQuiz && typeof window.LearniQuiz.showBinomialComparisonInQuiz === "function") {
        window.LearniQuiz.showBinomialComparisonInQuiz(courseId, {
          subject: "self",
          title: String(t("resultsTitle", "Listo")),
          kicker: String(t("title", "Test Partner")),
        });
        return true;
      }
    } catch (e) {}
    return false;
  }

  function checkWatch() {
    if (!watchState || !watchState.sessionId || !watchState.courseId) return;
    apiFetch("/learni/v1/cross-eval/sessions/" + String(watchState.sessionId), { method: "GET" })
      .then(function (res) { return fetchJson(res, "Failed to load status"); })
      .then(function (data) {
        var st = data && data.session && data.session.status ? String(data.session.status) : "";
        if (!st) return;
        if (st === "completed") {
          var cid = Number(watchState.courseId);
          watchState = null;
          saveWatch(null);
          // Show the same quiz overlay with Initial vs Final comparison for the tested user.
          maybeShowResults(cid);
          return;
        }
        if (st === "expired" || st === "canceled" || st === "declined") {
          watchState = null;
          saveWatch(null);
        }
      })
      .catch(function () {});
  }

  function poll() {
    if (state.open || state.busy) return;
    checkWatch();
    apiFetch("/learni/v1/cross-eval/pending", { method: "GET" })
      .then(function (res) {
        return fetchJson(res, "Failed to load pending sessions");
      })
      .then(function (data) {
        var sessions = (data && data.sessions) || [];
        if (!Array.isArray(sessions) || sessions.length === 0) return;
        var first = sessions[0];
        if (!first || !first.id) return;
        showModal(first);
      })
      .catch(function () {});
  }

  // Start polling after DOM is ready.
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      poll();
      window.setInterval(poll, 3500);
    });
  } else {
    poll();
    window.setInterval(poll, 3500);
  }
})();
