(function () {
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts
  var getConfig = window.LearniQuiz.getConfig;
  var i18n = window.LearniQuiz.i18n;
  var apiFetch = window.LearniQuiz.apiFetch;
  var fetchJson = window.LearniQuiz.fetchJson;
  var getCourseIdFromDom = window.LearniQuiz.getCourseIdFromDom;
  var startBinomialQuiz = window.LearniQuiz.startBinomialQuiz;
  var startCrossEvalPartnerTest = window.LearniQuiz.startCrossEvalPartnerTest;
  var openCertificate = window.LearniQuiz.openCertificate;
  var openLoginRegister = window.LearniQuiz.openLoginRegister;

  function ensureEvalBlock(container, kind, title, percent) {
    if (!container) return null;
    var selector = '.learni-eval[data-learni-eval="' + String(kind) + '"]';
    var el = container.querySelector ? container.querySelector(selector) : null;

    if (percent === null || percent === undefined) {
      if (el && el.parentNode) el.parentNode.removeChild(el);
      return null;
    }

    var pct = typeof percent === "number" ? percent : parseInt(percent, 10);
    if (!isFinite(pct)) pct = 0;
    pct = Math.max(0, Math.min(100, Math.round(pct)));

    if (!el) {
      el = document.createElement("div");
      el.className = "learni-eval";
      el.setAttribute("data-learni-eval", String(kind));
      el.innerHTML =
        '<div class="learni-eval-head"><span class="learni-eval-title"></span><span class="learni-eval-percent"></span></div>' +
        '<div class="learni-eval-track"><div class="learni-eval-bar"></div></div>';
      container.insertBefore(el, container.firstChild || null);
    }

    var titleEl = el.querySelector ? el.querySelector(".learni-eval-title") : null;
    var pctEl = el.querySelector ? el.querySelector(".learni-eval-percent") : null;
    var barEl = el.querySelector ? el.querySelector(".learni-eval-bar") : null;
    if (titleEl) titleEl.textContent = String(title || "");
    if (pctEl) pctEl.textContent = String(pct) + "%";
    if (barEl && barEl.style) barEl.style.width = String(pct) + "%";
    return el;
  }

  function updatePartnerSectionFromApi(data) {
    if (!data || typeof data !== "object") return;
    var partner = data.partner || {};
    var otherUserId = partner && partner.otherUserId ? Number(partner.otherUserId) : 0;
    if (!otherUserId) return;

    var myLessons = data && data.progress && typeof data.progress.lessonsPercent === "number" ? data.progress.lessonsPercent : 0;
    var otherLessons = partner && typeof partner.otherLessonsPercent === "number" ? partner.otherLessonsPercent : 0;
    myLessons = Math.max(0, Math.min(100, Math.round(Number(myLessons))));
    otherLessons = Math.max(0, Math.min(100, Math.round(Number(otherLessons))));

    var myFinal = data && data.attempts && data.attempts.final ? data.attempts.final : null;
    var otherFinal = partner && partner.otherAttempts && partner.otherAttempts.final ? partner.otherAttempts.final : null;
    var myFinalPct = myFinal && typeof myFinal.percent === "number" ? myFinal.percent : null;
    var otherFinalPct = otherFinal && typeof otherFinal.percent === "number" ? otherFinal.percent : null;

    var items = document.querySelectorAll ? document.querySelectorAll(".learni-course-partner-progress-item[data-user-id]") : [];
    for (var i = 0; i < items.length; i++) {
      var el = items[i];
      if (!el) continue;
      var pid = Number(el.getAttribute("data-user-id") || 0);
      if (!pid) continue;

      var isOther = pid === otherUserId;
      var lessonsPct = isOther ? otherLessons : myLessons;
      var finalPct = isOther ? otherFinalPct : myFinalPct;
      var hasFinal = finalPct !== null && finalPct !== undefined;

      var pctNode = el.querySelector ? el.querySelector(".learni-course-partner-progress-percent") : null;
      if (pctNode) {
        pctNode.textContent = hasFinal ? ("🏆 PUNTAJE FINAL: " + String(finalPct) + "%") : (String(lessonsPct) + "%");
      }

      var bar = el.querySelector ? el.querySelector(".learni-course-partner-progress-bar") : null;
      if (hasFinal) {
        if (bar && bar.parentNode) bar.parentNode.removeChild(bar);
      } else {
        if (!bar) {
          bar = document.createElement("div");
          bar.className = "learni-course-partner-progress-bar";
          bar.setAttribute("role", "progressbar");
          bar.innerHTML = '<span class="learni-course-partner-progress-fill"></span>';
          el.appendChild(bar);
        }
        var fill = bar.querySelector ? bar.querySelector(".learni-course-partner-progress-fill") : null;
        if (fill && fill.style) fill.style.width = String(lessonsPct) + "%";
      }
    }
  }

  function ensureFirstQuizCta(container, courseId, shouldShow) {
    if (!container) return;
    var existing = document.getElementById("learni-course-first-quiz");
    if (!shouldShow) {
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      return;
    }
    if (!existing) {
      existing = document.createElement("button");
      existing.id = "learni-course-first-quiz";
      existing.className = "learni-btn learni-btn-quiz";
      existing.type = "button";
      existing.setAttribute("data-course-id", String(courseId));
      existing.setAttribute("data-phase", "initial");
      existing.textContent = i18n("takeFirstQuiz", "TAKE FIRST QUIZ");
    }
    var anchor = (container.querySelector && container.querySelector("#learni-course-final-quiz")) || (container.querySelector && container.querySelector("#learni-course-restart")) || (container.querySelector && container.querySelector(".learni-course-primary-btn")) || null;
    if (anchor && anchor.parentNode === container) {
      container.insertBefore(existing, anchor);
    } else if (!existing.parentNode) {
      container.appendChild(existing);
    }
  }

  function ensureTestPartnerCta(container, courseId, shouldShow) {
    if (!container) return;
    var existing = document.getElementById("learni-course-test-partner");
    if (!shouldShow) {
      if (existing) {
        var p = existing.parentNode;
        if (p && p.classList && p.classList.contains("learni-tooltip-wrap")) {
          if (p.parentNode) p.parentNode.removeChild(p);
        } else if (existing.parentNode) {
          existing.parentNode.removeChild(existing);
        }
      }
      return;
    }
    if (!existing) {
      existing = document.createElement("button");
      existing.id = "learni-course-test-partner";
      existing.className = "learni-btn learni-btn-quiz";
      existing.type = "button";
      existing.textContent = i18n("testPartner", "TEST PARTNER");
      existing.setAttribute("data-base-label", existing.textContent);
    }
    existing.setAttribute("data-course-id", String(courseId));
    var anchor = (container.querySelector && container.querySelector("#learni-course-restart")) || (container.querySelector && container.querySelector(".learni-course-primary-btn")) || null;
    if (anchor && anchor.parentNode === container) {
      if (existing.parentNode !== container) container.insertBefore(existing, anchor);
    } else if (existing.parentNode !== container) {
      container.appendChild(existing);
    }
  }

  function ensureFinalQuizCta(container, courseId, shouldShow) {
    if (!container) return;
    var existing = document.getElementById("learni-course-final-quiz");
    if (!shouldShow) {
      if (existing) {
        var p = existing.parentNode;
        if (p && p.classList && p.classList.contains("learni-tooltip-wrap")) {
          if (p.parentNode) p.parentNode.removeChild(p);
        } else if (existing.parentNode) {
          existing.parentNode.removeChild(existing);
        }
      }
      return;
    }
    if (!existing) {
      existing = document.createElement("button");
      existing.id = "learni-course-final-quiz";
      existing.className = "learni-btn learni-btn-quiz";
      existing.type = "button";
      existing.textContent = i18n("takeFinalQuiz", "TAKE FINAL QUIZ");
      existing.setAttribute("data-base-label", existing.textContent);
    }
    existing.setAttribute("data-course-id", String(courseId));
    existing.setAttribute("data-phase", "final");
    var anchor = (container.querySelector && container.querySelector("#learni-course-restart")) || (container.querySelector && container.querySelector(".learni-course-primary-btn")) || null;
    if (anchor && anchor.parentNode === container) {
      if (existing.parentNode !== container) container.insertBefore(existing, anchor);
    } else if (existing.parentNode !== container) {
      container.appendChild(existing);
    }
  }

  function wrapWithTooltip(btn, title) {
    if (!btn || !btn.parentNode) return;
    var p = btn.parentNode;
    if (p && p.classList && p.classList.contains("learni-tooltip-wrap")) {
      p.title = String(title || "");
      return;
    }
    var wrap = document.createElement("span");
    wrap.className = "learni-tooltip-wrap";
    wrap.title = String(title || "");
    p.insertBefore(wrap, btn);
    wrap.appendChild(btn);
  }

  function unwrapTooltip(btn) {
    if (!btn) return;
    var p = btn.parentNode;
    if (!p || !p.classList || !p.classList.contains("learni-tooltip-wrap")) return;
    var gp = p.parentNode;
    if (!gp) return;
    gp.insertBefore(btn, p);
    gp.removeChild(p);
  }

  function updateTestPartnerCtaFromApi(data, courseId) {
    if (!data || typeof data !== "object") return;
    var container = document.querySelector && document.querySelector(".learni-course-card-actions");
    if (!container) return;
    var partner = data.partner || {};
    var hasPartner = !!(partner && partner.hasPartner);
    if (!hasPartner) {
      ensureTestPartnerCta(container, courseId, false);
      return;
    }
    var shouldShow = !!partner.otherNeedsFinal && !(partner && partner.otherFinalEligible) && (typeof partner.otherLessonsPercent === "number" ? partner.otherLessonsPercent : 0) >= 100;
    ensureTestPartnerCta(container, courseId, shouldShow);
    var btn = document.getElementById("learni-course-test-partner");
    if (!btn) return;
    var days = partner && typeof partner.otherFinalCooldownDaysRemaining === "number" ? partner.otherFinalCooldownDaysRemaining : 0;
    var disabled = !partner.otherCanTakeFinal || days > 0;
    btn.disabled = !!disabled;
    if (days > 0) {
      var base = btn.getAttribute("data-base-label") || i18n("testPartner", "TEST PARTNER");
      btn.textContent = base + " \u2014 " + String(days) + " d\xEDas +";
      wrapWithTooltip(btn, "En " + String(days) + " d\xEDas podr\xE1s volver a tomar la Evaluaci\xF3n Final.");
    } else {
      btn.textContent = btn.getAttribute("data-base-label") || i18n("testPartner", "TEST PARTNER");
      unwrapTooltip(btn);
    }
  }

  function updateFinalQuizCtaFromApi(data, courseId) {
    if (!data || typeof data !== "object") return;
    var container = document.querySelector && document.querySelector(".learni-course-card-actions");
    if (!container) return;
    var partner = data.partner || {};
    if (!!(partner && partner.hasPartner)) {
      ensureFinalQuizCta(container, courseId, false);
      return;
    }
    var ui = (data && data.ui) || {};
    var progress = (data && data.progress) || {};
    var lessons = typeof progress.lessonsPercent === "number" ? progress.lessonsPercent : 0;
    var shouldShow = !!ui.needsFinal && lessons >= 100 && !(ui && ui.finalEligible);
    ensureFinalQuizCta(container, courseId, shouldShow);
    var btn = document.getElementById("learni-course-final-quiz");
    if (!btn) return;
    var days = ui && typeof ui.finalCooldownDaysRemaining === "number" ? ui.finalCooldownDaysRemaining : 0;
    var disabled = !ui.canTakeFinal || days > 0;
    btn.disabled = !!disabled;
    if (days > 0) {
      var base = btn.getAttribute("data-base-label") || i18n("takeFinalQuiz", "TAKE FINAL QUIZ");
      btn.textContent = base + " \u2014 " + String(days) + " d\xEDas +";
      wrapWithTooltip(btn, "En " + String(days) + " d\xEDas podr\xE1s volver a tomar la Evaluaci\xF3n Final.");
    } else {
      btn.textContent = btn.getAttribute("data-base-label") || i18n("takeFinalQuiz", "TAKE FINAL QUIZ");
      unwrapTooltip(btn);
    }
  }

  function syncBinomialAsideFromApi() {
    var cfg = getConfig();
    if (!cfg || !cfg.isLoggedIn) return;
    var courseId = getCourseIdFromDom();
    if (!courseId) return;
    var container = document.querySelector && document.querySelector(".learni-course-card-actions");
    if (!container) return;

    apiFetch("/learni/v1/courses/" + courseId + "/binomial", { method: "GET" })
      .then(function (res) { return fetchJson(res, "Failed to load quiz status"); })
      .then(function (data) {
        var attempts = (data && data.attempts) || {};
        var ui = (data && data.ui) || {};
        var initial = attempts && attempts.initial ? attempts.initial : null;
        var final = attempts && attempts.final ? attempts.final : null;
        var iPct = initial && typeof initial.percent === "number" ? initial.percent : null;
        var fPct = final && typeof final.percent === "number" ? final.percent : null;

        ensureEvalBlock(container, "initial", i18n("evalInitial", "EVALUACI\xD3N INICIAL"), iPct);
        ensureEvalBlock(container, "final", i18n("evalFinal", "EVALUACI\xD3N FINAL"), fPct);
        ensureFirstQuizCta(container, courseId, !!(ui && ui.needsInitial));
        updatePartnerSectionFromApi(data);
        updateTestPartnerCtaFromApi(data, courseId);
        updateFinalQuizCtaFromApi(data, courseId);
      })
      .catch(function () {});
  }

  function setupBinomialQuiz() {
    function onStartClick(btn) {
      if (!btn || btn.disabled) return;
      var cfg = getConfig();
      var courseId = btn.getAttribute("data-course-id") || "";
      var phase = btn.getAttribute("data-phase") || "initial";
      if (!cfg || !cfg.isLoggedIn) {
        openLoginRegister(courseId, phase);
        return;
      }
      if (courseId) startBinomialQuiz(courseId, phase);
    }

    document.addEventListener("click", function (e) {
      var t = e && e.target ? e.target : null;
      if (!t || !t.closest) return;

      var firstBtn = t.closest("#learni-course-first-quiz");
      if (firstBtn) { e.preventDefault(); onStartClick(firstBtn); return; }

      var finalBtn = t.closest("#learni-course-final-quiz");
      if (finalBtn) { e.preventDefault(); onStartClick(finalBtn); return; }

      var testPartnerBtn = t.closest("#learni-course-test-partner");
      if (testPartnerBtn) {
        e.preventDefault();
        if (testPartnerBtn.disabled) return;
        var cfg = getConfig();
        var courseId = testPartnerBtn.getAttribute("data-course-id") || "";
        if (!cfg || !cfg.isLoggedIn) { openLoginRegister(courseId, "final"); return; }
        if (courseId) startCrossEvalPartnerTest(courseId);
        return;
      }

      var restartBtn = t.closest("#learni-course-restart");
      if (restartBtn) {
        e.preventDefault();
        var courseId2 = restartBtn.getAttribute("data-course-id") || "";
        if (!courseId2) return;
        if (!window.confirm("\xBFReiniciar curso? Esto reiniciar\xE1 tu progreso de lecciones.")) return;
        restartBtn.disabled = true;
        apiFetch("/learni/v1/courses/" + courseId2 + "/restart", { method: "POST", body: JSON.stringify({}) })
          .then(function (res) { return res.json(); })
          .then(function () { window.location.reload(); })
          .catch(function (err) { restartBtn.disabled = false; alert((err && err.message) || "Failed to restart course"); });
      }
    });
  }

  function setupCertificates() {
    document.addEventListener("click", function (e) {
      var t = e && e.target ? e.target : null;
      if (!t || !t.closest) return;
      var trigger = t.closest("[data-learni-cert-open=\"1\"], .learni-course-cert-trigger");
      if (trigger) {
        e.preventDefault();
        var courseId = trigger.getAttribute("data-course-id") || "";
        if (courseId) openCertificate(courseId);
      }
    });
  }

  // Expose to window.LearniQuiz
  window.LearniQuiz.syncBinomialAsideFromApi = syncBinomialAsideFromApi;
  window.LearniQuiz.setupBinomialQuiz = setupBinomialQuiz;
  window.LearniQuiz.setupCertificates = setupCertificates;
  window.LearniQuiz.updatePartnerSectionFromApi = updatePartnerSectionFromApi;
  window.LearniQuiz.updateTestPartnerCtaFromApi = updateTestPartnerCtaFromApi;
})();
