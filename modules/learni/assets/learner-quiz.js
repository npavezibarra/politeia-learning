(function () {
  // Expose a tiny public API for lesson overlays.
  window.LearniQuiz = window.LearniQuiz || {};

  function getConfig() {
    return window.Learni || {};
  }

  function i18n(key, fallback) {
    var cfg = getConfig();
    var table = cfg && cfg.i18n ? cfg.i18n : null;
    if (table && Object.prototype.hasOwnProperty.call(table, key)) {
      var v = table[key];
      if (v !== undefined && v !== null && String(v) !== "") return String(v);
    }
    return String(fallback || "");
  }

  function formatTemplate(tpl, vars) {
    var out = String(tpl || "");
    var v = vars && typeof vars === "object" ? vars : {};
    Object.keys(v).forEach(function (k) {
      out = out.split("{" + k + "}").join(String(v[k]));
    });
    return out;
  }

  function getCourseTitleFromDom() {
    var el = document.getElementById("learni-course-title");
    if (el && el.textContent) return String(el.textContent).trim();
    // Fallback: some templates may not have the ID.
    var h1 = document.querySelector && document.querySelector("main .learni-course-header h1, main h1");
    if (h1 && h1.textContent) return String(h1.textContent).trim();
    return "";
  }

  function fetchJson(res, defaultMessage) {
    return res.json().then(function (data) {
      if (!res.ok) {
        var msg = (data && data.message) || defaultMessage || "Request failed";
        throw new Error(msg);
      }
      return data;
    });
  }

	  function openLoginRegister(courseId, phase) {
	    try {
	      // Open our modal (same UI as the "INGRESAR" menu button) and ensure the redirect_to includes
	      // a return URL that can launch the quiz right after auth.
	      var redirectUrl = new URL(window.location.href);
	      if (courseId) redirectUrl.searchParams.set("learni_course_id", String(courseId));
	      if (phase) redirectUrl.searchParams.set("learni_quiz_phase", String(phase));
	      redirectUrl.searchParams.set("learni_auto_quiz", "1");

	      if (window.PLAuthOpenModal) {
	        window.PLAuthOpenModal("login");
	        var input = document.querySelector && document.querySelector("#pl-auth-overlay [data-pl-auth-redirect]");
	        if (input) input.value = redirectUrl.toString();
	        return true;
	      }
	    } catch (e) {}

	    // Preferred: open our login/register modal with a redirect that auto-starts the quiz after registration/login.
	    try {
	      var cfg2 = getConfig();
      var base = cfg2 && cfg2.authBaseUrl ? String(cfg2.authBaseUrl) : "";
      if (base) {
        var redirectUrl = new URL(window.location.href);
        if (courseId) redirectUrl.searchParams.set("learni_course_id", String(courseId));
        if (phase) redirectUrl.searchParams.set("learni_quiz_phase", String(phase));
        redirectUrl.searchParams.set("learni_auto_quiz", "1");

        var modalUrl = new URL(base);
        modalUrl.searchParams.set("pl_auth_view", "login");
        modalUrl.searchParams.set("redirect_to", redirectUrl.toString());
        window.location.href = modalUrl.toString();
        return true;
      }
    } catch (e0) {}

    // Fallback: redirect to a login page (Woo "My account" if available).
    try {
      var cfg = getConfig();
      var url = cfg && cfg.loginUrl ? String(cfg.loginUrl) : "";
      if (url) {
        window.location.href = url;
        return true;
      }
    } catch (e2) {}

	    return false;
	  }

	  function showPostAuthQuizPrompt(courseId, phase, opts) {
	    var options = opts && typeof opts === "object" ? opts : {};
	    var isRegistered = !!options.isRegistered;
	    var courseTitle = getCourseTitleFromDom() || i18n("course", "Course");

	    // Try to reuse the exact aside CTA label when available (keeps language consistent).
	    var asideBtn = document.getElementById("learni-course-first-quiz");
	    var ctaLabel = asideBtn && asideBtn.textContent ? String(asideBtn.textContent).trim() : "";
	    if (!ctaLabel) {
	      ctaLabel = i18n("takeFirstQuiz", "Take First Quiz");
	    }

		    var cfg = getConfig();
		    var lang =
		      String((cfg && cfg.locale) || "") ||
		      String((document && document.documentElement && document.documentElement.lang) || "") ||
		      String((navigator && navigator.language) || "");
		    var isSpanish = lang.toLowerCase().indexOf("es") === 0;
	    var msg = isRegistered
	      ? (isSpanish
	          ? "¡Te has registrado con éxito! Toma el First Quiz ahora."
	          : "Registration successful! Take the First Quiz now.")
	      : (isSpanish
	          ? "Toma el First Quiz ahora."
	          : "Take the First Quiz now.");

	    // Ensure modal exists before setting title/body (otherwise setQuizModalTitle/Body are no-ops).
	    showQuizModal();
	    setQuizModalTitle(courseTitle);
	    setQuizModalBody(
	      '<div class="learni-quiz-intro">' +
	        '<div class="learni-quiz-intro__text">' +
	        escapeHtml(msg) +
	        "</div>" +
	        '<div class="learni-quiz-actions">' +
	        '<button type="button" class="learni-btn" id="learni-quiz-postauth-start">' +
	        escapeHtml(ctaLabel) +
	        "</button>" +
	        "</div>" +
	      "</div>"
	    );
	    var btn = document.getElementById("learni-quiz-postauth-start");
	    if (btn) {
	      btn.addEventListener("click", function () {
	        startBinomialQuiz(courseId, phase || "initial");
	      });
	    }
	  }

		  function maybeAutoStartQuizFromUrl() {
		    try {
		      var cfg = getConfig();
		      var url = new URL(window.location.href);
	      // One-time param used to show the "unverified account" prompt after completing the first quiz.
	      if (url.searchParams.get("pl_auth_unverified_after_quiz")) {
	        url.searchParams.delete("pl_auth_unverified_after_quiz");
	        try {
	          window.history.replaceState({}, document.title, url.toString());
	        } catch (e0) {}
	      }

		      if (!cfg || !cfg.isLoggedIn) return;

		      // After auth, we show an explicit CTA instead of auto-starting (more reliable across themes).
		      var auto = url.searchParams.get("learni_auto_quiz");
		      if (!auto) return;

		      var courseId = url.searchParams.get("learni_course_id") || "";
		      var phase = url.searchParams.get("learni_quiz_phase") || "initial";
		      if (!courseId) return;

		      var registered = url.searchParams.get("pl_auth_registered") === "1";

		      // Clean URL to avoid re-opening on refresh/back.
		      url.searchParams.delete("learni_auto_quiz");
		      url.searchParams.delete("learni_course_id");
		      url.searchParams.delete("learni_quiz_phase");
		      url.searchParams.delete("pl_auth_registered");
		      try {
		        window.history.replaceState({}, document.title, url.toString());
		      } catch (e) {}

		      showPostAuthQuizPrompt(courseId, phase, { isRegistered: registered });
	    } catch (e3) {}
	  }

  function apiFetch(path, options) {
    var cfg = getConfig();
    var url = (cfg.restUrl || "/wp-json/") + path.replace(/^\//, "");
    var headers = Object.assign(
      {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.restNonce || "",
      },
      (options && options.headers) || {}
    );
    return fetch(url, Object.assign({}, options || {}, { headers: headers }));
  }

	  function escapeHtml(s) {
	    return String(s || "")
	      .replace(/&/g, "&amp;")
	      .replace(/</g, "&lt;")
	      .replace(/>/g, "&gt;")
	      .replace(/"/g, "&quot;")
	      .replace(/'/g, "&#039;");
	  }

	  function hash32(str) {
	    // Simple non-crypto 32-bit hash (FNV-1a-ish) for stable shuffles.
	    var s = String(str || "");
	    var h = 2166136261;
	    for (var i = 0; i < s.length; i++) {
	      h ^= s.charCodeAt(i);
	      // h *= 16777619 (with overflow), via bit ops
	      h = (h + (h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24)) >>> 0;
	    }
	    return h >>> 0;
	  }

	  function stableShuffleBySeed(items, seed) {
	    var arr = Array.isArray(items) ? items.slice() : [];
	    var s = String(seed || "");
	    arr.sort(function (a, b) {
	      var ha = hash32(s + ":" + String(a && a.id !== undefined ? a.id : a));
	      var hb = hash32(s + ":" + String(b && b.id !== undefined ? b.id : b));
	      if (ha === hb) return 0;
	      return ha < hb ? -1 : 1;
	    });
	    return arr;
	  }

	  function ensureQuizModal() {
	    var existing = document.getElementById("learni-quiz-modal");
	    if (existing) return existing;

	    var modal = document.createElement("div");
	    modal.id = "learni-quiz-modal";
	    modal.className = "learni-quiz-modal";
	    modal.innerHTML =
	      '<div class="learni-quiz-modal__backdrop"></div>' +
	      '<div class="learni-quiz-modal__panel" role="dialog" aria-modal="true" aria-label="Quiz">' +
	      '<div class="learni-quiz-modal__head">' +
	      '<div class="learni-quiz-modal__title" id="learni-quiz-modal-title">Quiz</div>' +
	      '<button type="button" class="learni-quiz-modal__close" data-learni-quiz-close="1" aria-label="' +
	      escapeHtml(i18n("close", "Close")) +
	      '">×</button>' +
	      "</div>" +
	      '<div class="learni-quiz-modal__body" id="learni-quiz-modal-body"></div>' +
	      "</div>";
    document.body.appendChild(modal);

    modal.addEventListener("click", function (e) {
      var close = e.target && e.target.getAttribute && e.target.getAttribute("data-learni-quiz-close");
      if (close) hideQuizModal();
    });

    return modal;
  }

  function showQuizModal() {
    var modal = ensureQuizModal();
    modal.classList.add("is-open");
    document.body.style.overflow = "hidden";
  }

  function hideQuizModal() {
    var modal = ensureQuizModal();
    modal.classList.remove("is-open");
    document.body.style.overflow = "";
    try {
      var body = document.getElementById("learni-quiz-modal-body");
      if (body) body.innerHTML = "";
    } catch (e) {}
  }

  function setQuizModalTitle(text) {
    var node = document.getElementById("learni-quiz-modal-title");
    if (node) node.textContent = text || "Quiz";
  }

  function setQuizModalBody(html) {
    var node = document.getElementById("learni-quiz-modal-body");
    if (node) node.innerHTML = html || "";
  }

  function ensureCourseCompleteOverlay() {
    var existing = document.getElementById("learni-course-complete-overlay");
    if (existing) return existing;

    var el = document.createElement("div");
    el.id = "learni-course-complete-overlay";
    el.className = "learni-final-overlay";
    el.innerHTML =
      '<div class="learni-final-overlay__backdrop" data-learni-course-complete-close="1"></div>' +
      '<div class="learni-final-overlay__panel" role="dialog" aria-modal="true" aria-label="Course completed">' +
      '<div class="learni-final-overlay__body" id="learni-course-complete-overlay-body"></div>' +
      "</div>";
    document.body.appendChild(el);

    el.addEventListener("click", function (e) {
      var close =
        e.target &&
        e.target.getAttribute &&
        e.target.getAttribute("data-learni-course-complete-close");
      if (close) hideCourseCompleteOverlay(true);
    });

    return el;
  }

  function showCourseCompleteOverlay(html) {
    var o = ensureCourseCompleteOverlay();
    var body = document.getElementById("learni-course-complete-overlay-body");
    if (body) body.innerHTML = html || "";
    o.classList.add("is-open");
    document.body.style.overflow = "hidden";
  }

  function hideCourseCompleteOverlay(reload) {
    var o = ensureCourseCompleteOverlay();
    o.classList.remove("is-open");
    document.body.style.overflow = "";
    try {
      var body = document.getElementById("learni-course-complete-overlay-body");
      if (body) body.innerHTML = "";
    } catch (e) {}

    if (reload) {
      try {
        window.location.reload();
      } catch (e) {}
    }
  }

  function labelDelta(delta) {
    var d = Number(delta || 0);
    if (!isFinite(d)) d = 0;
    d = Math.round(d);
    if (d > 0) return "Mejoraste en " + d + " puntos porcentuales.";
    if (d < 0) return "Bajaste en " + Math.abs(d) + " puntos porcentuales.";
    return "Tu desempeño se mantuvo igual.";
  }

  function ringChartSvg(percent, gradientId, svgClassName) {
    var pct = Number(percent || 0);
    if (!isFinite(pct)) pct = 0;
    pct = Math.max(0, Math.min(100, Math.round(pct)));

    var r = 50;
    var circumference = 2 * Math.PI * r;
    var offset = circumference * (1 - pct / 100);

    return (
      '<svg class="' +
      escapeHtml(svgClassName || "") +
      '" viewBox="0 0 120 120" aria-hidden="true" focusable="false">' +
      "<defs>" +
      '<linearGradient id="' +
      escapeHtml(gradientId) +
      '" x1="0%" y1="0%" x2="100%" y2="100%">' +
      '<stop offset="0%" stop-color="#8A6B1E" />' +
      '<stop offset="50%" stop-color="#C79F32" />' +
      '<stop offset="100%" stop-color="#E9D18A" />' +
      "</linearGradient>" +
      "</defs>" +
      '<circle cx="60" cy="60" r="' +
      r +
      '" class="learni-final-overlay__chart-track" />' +
      '<circle cx="60" cy="60" r="' +
      r +
      '" class="learni-final-overlay__chart-progress" stroke="url(#' +
      escapeHtml(gradientId) +
      ')" stroke-dasharray="' +
      circumference.toFixed(3) +
      '" stroke-dashoffset="' +
      offset.toFixed(3) +
      '" />' +
      '<text x="50%" y="50%" text-anchor="middle" dy=".3em" class="learni-final-overlay__chart-text">' +
      pct +
      "%</text>" +
      "</svg>"
    );
  }

  function progressChart(title, percent, gradientId) {
    return (
      '<div class="learni-final-overlay__chart">' +
      ringChartSvg(percent, gradientId, "learni-final-overlay__chart-svg") +
      '<div class="learni-final-overlay__chart-label">' +
      escapeHtml(title) +
      "</div>" +
      "</div>"
    );
  }

  function showFinalQuizCompletion(courseId) {
    showCourseCompleteOverlay('<div class="learni-quiz-modal__loading">Loading…</div>');

    apiFetch("/learni/v1/courses/" + courseId + "/binomial", { method: "GET" })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            var msg = (data && data.message) || "Failed to load results";
            throw new Error(msg);
          }
          return data;
        });
      })
      .then(function (data) {
        var attempts = (data && data.attempts) || {};
        var initial = attempts && attempts.initial ? attempts.initial : null;
        var final = attempts && attempts.final ? attempts.final : null;

        var iPct = initial && typeof initial.percent === "number" ? initial.percent : null;
        var fPct = final && typeof final.percent === "number" ? final.percent : null;
        if (iPct === null || !isFinite(iPct)) iPct = 0;
        if (fPct === null || !isFinite(fPct)) fPct = 0;
        iPct = Math.max(0, Math.min(100, Math.round(iPct)));
        fPct = Math.max(0, Math.min(100, Math.round(fPct)));

        var delta = fPct - iPct;
        var certificateAvailable = !!(data && data.certificateAvailable);

        var certBlock = "";
        if (certificateAvailable) {
          certBlock =
            '<div class="learni-final-overlay__cert">Has obtenido el certificado de este curso.</div>' +
            '<button type="button" class="learni-btn learni-course-primary-btn" data-learni-cert-open="1" data-course-id="' +
            escapeHtml(String(courseId)) +
            '">VER CERTIFICADO</button>';
        }

        var html =
          '<div class="learni-final-overlay__title">Felicitaciones 🎉</div>' +
          '<div class="learni-final-overlay__text">Terminaste el curso. Estos fueron tus resultados:</div>' +
          '<div class="learni-final-overlay__charts" aria-label="Resultados">' +
          progressChart("Evaluación inicial", iPct, "learniGoldGradientInitial") +
          progressChart("Evaluación final", fPct, "learniGoldGradientFinal") +
          "</div>" +
          '<div class="learni-final-overlay__variation">' +
          escapeHtml(labelDelta(delta)) +
          "</div>" +
          (certBlock ? '<div class="learni-final-overlay__certwrap">' + certBlock + "</div>" : "") +
          '<div class="learni-final-overlay__actions">' +
          '<button type="button" class="learni-btn secondary learni-course-primary-btn" data-learni-course-complete-close="1">CERRAR</button>' +
          "</div>";

        showCourseCompleteOverlay(html);
      })
      .catch(function () {
        var html =
          '<div class="learni-final-overlay__title">Felicitaciones 🎉</div>' +
          '<div class="learni-final-overlay__text">Terminaste el curso. Tu evaluación final fue registrada correctamente.</div>' +
          '<div class="learni-final-overlay__actions">' +
          '<button type="button" class="learni-btn secondary learni-course-primary-btn" data-learni-course-complete-close="1">CERRAR</button>' +
          "</div>";
        showCourseCompleteOverlay(html);
      });
  }

  function startBinomialQuiz(courseId, phase) {
    showQuizModal();
    setQuizModalTitle(phase === "final" ? i18n("quizFinalKicker", "Final Quiz") : i18n("quizInitialKicker", "First Quiz"));
    setQuizModalBody('<div class="learni-quiz-modal__loading">' + escapeHtml(i18n("loading", "Loading…")) + "</div>");

    var startReq = apiFetch("/learni/v1/courses/" + courseId + "/binomial/start", {
      method: "POST",
      body: JSON.stringify({ phase: phase }),
    }).then(function (res) {
      return fetchJson(res, "Failed to start quiz");
    });

    var initialScoreReq =
      phase === "final"
        ? apiFetch("/learni/v1/courses/" + courseId + "/binomial", { method: "GET" })
            .then(function (res) {
              return fetchJson(res, "Failed to load results");
            })
            .then(function (data) {
              var attempts = (data && data.attempts) || {};
              var initial = attempts && attempts.initial ? attempts.initial : null;
              var pct = initial && typeof initial.percent === "number" ? initial.percent : null;
              if (pct === null || !isFinite(pct)) return null;
              return Math.max(0, Math.min(100, Math.round(pct)));
            })
            .catch(function () {
              return null;
            })
        : Promise.resolve(null);

    Promise.all([startReq, initialScoreReq])
      .then(function (parts) {
        var data = parts[0];
        var initialScore = parts[1];

	        var attemptId = data && data.attempt && data.attempt.id ? String(data.attempt.id) : "";
	        var title = data && data.quiz && data.quiz.title ? data.quiz.title : "Quiz";
	        var introText = data && data.quiz && data.quiz.introText ? String(data.quiz.introText) : "";
	        var questions = data && Array.isArray(data.questions) ? data.questions : [];

	        setQuizModalTitle(title);

		        var state = {
		          attemptId: attemptId,
		          phase: phase,
		          title: title,
		          introText: introText,
		          courseTitle: getCourseTitleFromDom() || title,
		          initialScore: initialScore,
		          questions: questions,
		          slide: "intro", // intro | q
		          index: 0,
		          answers: {}, // questionId -> answerId
		          answerOrders: {}, // questionId -> shuffled answers[]
		        };

	        function render() {
	          if (state.slide === "intro") {
	            var phaseLabel = state.phase === "final" ? i18n("quizFinalKicker", "Final Quiz") : i18n("quizInitialKicker", "First Quiz");
	            var prep =
	              state.phase === "final"
	                ? formatTemplate(i18n("quizPrepFinal", ""), {
	                    course: state.courseTitle,
	                    count: state.questions.length,
	                    score: typeof state.initialScore === "number" ? state.initialScore : "—",
	                  })
	                : formatTemplate(i18n("quizPrepInitial", ""), { course: state.courseTitle, count: state.questions.length });

	            var extra = state.introText
	              ? '<div class="learni-quiz-intro__text">' + escapeHtml(String(state.introText)) + "</div>"
	              : "";
		            var html =
		              '<div class="learni-quiz-intro">' +
		              '<div class="learni-quiz-intro__kicker">' +
		              escapeHtml(phaseLabel) +
		              "</div>" +
		              '<div class="learni-quiz-intro__title">' +
		              escapeHtml(state.courseTitle) +
		              "</div>" +
		              '<div class="learni-quiz-intro__text">' +
		              escapeHtml(prep) +
		              "</div>" +
		              extra +
		              '<div class="learni-quiz-actions">' +
		              '<button type="button" class="learni-btn" id="learni-quiz-begin">' +
		              escapeHtml(i18n("quizBegin", "Begin")) +
		              "</button>" +
		              "</div>" +
		              "</div>";
	            setQuizModalBody(html);
	            var begin = document.getElementById("learni-quiz-begin");
            if (begin) {
              begin.addEventListener("click", function () {
                state.slide = "q";
                state.index = 0;
                render();
              });
            }
            return;
          }

          var q = state.questions[state.index];
          if (!q) {
            setQuizModalBody('<div class="learni-quiz-modal__error">No questions found.</div>');
            return;
          }

	          var isLast = state.index === state.questions.length - 1;
	          var answersHtml = "";
	          var answers = Array.isArray(q.answers) ? q.answers : [];
	          var qid = String(q.id || "");
	          if (qid) {
	            if (!state.answerOrders[qid]) {
	              state.answerOrders[qid] = stableShuffleBySeed(answers, state.attemptId + ":" + qid);
	            }
	            answers = state.answerOrders[qid];
	          } else {
	            answers = stableShuffleBySeed(answers, state.attemptId + ":idx:" + String(state.index));
	          }
	          answers.forEach(function (a) {
	            var aid = a && a.id !== undefined ? String(a.id) : "";
	            var checked = "";
	            if (qid && state.answers[qid] !== undefined && String(state.answers[qid]) === aid) checked = ' checked="checked"';
	            answersHtml +=
	              '<label class="learni-quiz-a">' +
	              '<input type="radio" name="q" value="' +
	              escapeHtml(String(a.id)) +
	              '"' +
	              checked +
	              ">" +
	              '<span class="learni-quiz-a__text">' +
	              escapeHtml(String(a.text || "")) +
	              "</span>" +
	              "</label>";
	          });

	          var htmlQ =
	            '<form id="learni-quiz-slide" class="learni-quiz-form" data-attempt-id="' +
	            escapeHtml(state.attemptId) +
	            '">' +
	            '<div class="learni-quiz-q">' +
	            '<div class="learni-quiz-q__meta">' +
	            escapeHtml(
	              formatTemplate(i18n("quizQuestionOf", "Question {current} of {total}"), {
	                current: state.index + 1,
	                total: state.questions.length,
	              })
	            ) +
	            "</div>" +
	            '<div class="learni-quiz-q__text">' +
	            escapeHtml(String(q.prompt || "")) +
	            "</div>" +
	            "</div>" +
            '<div class="learni-quiz-a-list">' +
            answersHtml +
            "</div>" +
	            '<div class="learni-quiz-actions">' +
	            (state.index > 0
	              ? '<button type="button" class="learni-btn secondary" id="learni-quiz-prev">' + escapeHtml(i18n("quizBack", "Back")) + "</button>"
	              : "") +
	            '<button type="submit" class="learni-btn" id="learni-quiz-next">' +
	            escapeHtml(isLast ? i18n("quizSubmit", "Submit") : i18n("quizNext", "Next")) +
	            "</button>" +
	            "</div>" +
	            "</form>";

          setQuizModalBody(htmlQ);

          var form = document.getElementById("learni-quiz-slide");
	          var prevBtn = document.getElementById("learni-quiz-prev");
	          if (prevBtn) {
	            prevBtn.addEventListener("click", function () {
	              state.index = Math.max(0, state.index - 1);
	              render();
	            });
	          }

          if (!form) return;
	          form.addEventListener("submit", function (e) {
	            e.preventDefault();
	            var chosen = form.querySelector('input[name="q"]:checked');
	            if (!chosen || !chosen.value) {
	              alert(i18n("quizChooseAnswer", "Please choose an answer."));
	              return;
	            }
	            state.answers[String(q.id)] = Number(chosen.value);

            if (!isLast) {
              state.index = Math.min(state.questions.length - 1, state.index + 1);
              render();
              return;
            }

            var submitBtn = document.getElementById("learni-quiz-next");
            if (submitBtn) submitBtn.disabled = true;

	            if (Object.keys(state.answers).length !== state.questions.length) {
	              if (submitBtn) submitBtn.disabled = false;
	              alert(i18n("quizAnswerAll", "Please answer all questions."));
	              return;
	            }

            apiFetch("/learni/v1/attempts/" + state.attemptId + "/submit", {
              method: "POST",
              body: JSON.stringify({ answers: state.answers }),
            })
              .then(function (res) {
                return res.json().then(function (data) {
                  if (!res.ok) {
                    var msg = (data && data.message) || "Failed to submit quiz";
                    throw new Error(msg);
                  }
                  return data;
                });
              })
              .then(function (data) {
                if (state.phase === "final") {
                  hideQuizModal();
                  showFinalQuizCompletion(courseId);
                  return;
                }

                var percent = data && typeof data.percent === "number" ? data.percent : null;
                if (percent === null || !isFinite(percent)) percent = 0;
                percent = Math.max(0, Math.min(100, Math.round(percent)));

                var score = data && typeof data.score === "number" ? data.score : 0;
                var total = data && typeof data.total === "number" ? data.total : state.questions.length;
                if (!isFinite(score) || score < 0) score = 0;
                if (!isFinite(total) || total <= 0) total = state.questions.length;

                setQuizModalTitle("Resultados");

                var html =
                  '<div class="learni-quiz-results">' +
                  '<div class="learni-quiz-results__kicker">Evaluación inicial</div>' +
                  '<div class="learni-quiz-results__chart">' +
                  ringChartSvg(percent, "learniGoldGradientResults", "learni-quiz-results__chart-svg") +
                  "</div>" +
                  '<div class="learni-quiz-results__meta">' +
                  score +
                  " de " +
                  total +
                  " correctas</div>" +
                  '<div class="learni-quiz-results__text">Felicitaciones: obtuviste <strong>' +
                  percent +
                  '%</strong> de respuestas correctas en la Evaluación Inicial. Ahora completa todas las lecciones de este curso. Al finalizar, podrás rendir la Evaluación Final y compararemos tu resultado inicial con el final para ver tu progreso.</div>' +
                  '<div class="learni-quiz-actions">' +
                  '<button type="button" class="learni-btn" id="learni-quiz-results-continue">Continuar</button>' +
                  "</div>" +
                  "</div>";

                setQuizModalBody(html);

		                var cont = document.getElementById("learni-quiz-results-continue");
		                if (cont) {
		                  cont.addEventListener("click", function () {
		                    hideQuizModal();
		                    try {
		                      // After finishing the first quiz, show the "unverified account" prompt on reload (if applicable).
		                      var url = new URL(window.location.href);
		                      url.searchParams.set("pl_auth_unverified_after_quiz", "1");
		                      window.location.href = url.toString();
		                    } catch (e) {
		                      window.location.reload();
		                    }
		                  });
		                }
	              })
	              .catch(function (err) {
                if (submitBtn) submitBtn.disabled = false;
                alert((err && err.message) || "Failed to submit quiz");
              });
          });
        }

        render();
      })
      .catch(function (err) {
        setQuizModalBody('<div class="learni-quiz-modal__error">' + escapeHtml((err && err.message) || "Failed to start quiz") + "</div>");
      });
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
      if (!courseId) return;
      startBinomialQuiz(courseId, phase);
    }

    // Delegate clicks so dynamically-inserted CTAs (partner views, cache busting) also work.
    document.addEventListener("click", function (e) {
      var t = e && e.target ? e.target : null;
      if (!t || !t.closest) return;

      var firstBtn = t.closest("#learni-course-first-quiz");
      if (firstBtn) {
        e.preventDefault();
        onStartClick(firstBtn);
        return;
      }

      var finalBtn = t.closest("#learni-course-final-quiz");
      if (finalBtn) {
        e.preventDefault();
        onStartClick(finalBtn);
        return;
      }

      var restartBtn = t.closest("#learni-course-restart");
      if (restartBtn) {
        e.preventDefault();
        var courseId = restartBtn.getAttribute("data-course-id") || "";
        if (!courseId) return;
        if (!window.confirm("¿Reiniciar curso? Esto reiniciará tu progreso de lecciones.")) return;
        restartBtn.disabled = true;
        apiFetch("/learni/v1/courses/" + courseId + "/restart", { method: "POST", body: JSON.stringify({}) })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok) {
                var msg = (data && data.message) || "Failed to restart course";
                throw new Error(msg);
              }
              return data;
            });
          })
          .then(function () {
            window.location.reload();
          })
          .catch(function (err) {
            restartBtn.disabled = false;
            alert((err && err.message) || "Failed to restart course");
          });
      }
    });
  }

  function getCourseIdFromDom() {
    var root = document.getElementById("learni-course");
    var courseId = root ? root.getAttribute("data-course-id") || "" : "";
    if (courseId) return String(courseId);
    var anyBtn = document.querySelector && document.querySelector("[data-course-id]");
    if (anyBtn) return String(anyBtn.getAttribute("data-course-id") || "");
    return "";
  }

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
      // Insert near the top of the actions area.
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
    } else {
      existing.setAttribute("data-course-id", String(courseId));
      existing.setAttribute("data-phase", "initial");
    }

    // Place it before the primary CTA (CONTINUE/BUY) if present.
    var anchor =
      (container.querySelector && container.querySelector("#learni-course-final-quiz")) ||
      (container.querySelector && container.querySelector("#learni-course-restart")) ||
      (container.querySelector && container.querySelector(".learni-course-primary-btn")) ||
      null;
    if (anchor && anchor.parentNode === container) {
      container.insertBefore(existing, anchor);
    } else if (!existing.parentNode) {
      container.appendChild(existing);
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
      .then(function (res) {
        return fetchJson(res, "Failed to load quiz status");
      })
      .then(function (data) {
        var attempts = (data && data.attempts) || {};
        var ui = (data && data.ui) || {};
        var initial = attempts && attempts.initial ? attempts.initial : null;
        var final = attempts && attempts.final ? attempts.final : null;

        var iPct = initial && typeof initial.percent === "number" ? initial.percent : null;
        var fPct = final && typeof final.percent === "number" ? final.percent : null;

        // Always re-sync the evaluation blocks from the API response (avoids cross-user cached HTML).
        ensureEvalBlock(container, "initial", i18n("evalInitial", "EVALUACIÓN INICIAL"), iPct);
        ensureEvalBlock(container, "final", i18n("evalFinal", "EVALUACIÓN FINAL"), fPct);

        var needsInitial = !!(ui && ui.needsInitial);
        ensureFirstQuizCta(container, courseId, needsInitial);
      })
      .catch(function () {
        // Ignore: server HTML stays as-is.
      });
  }

  function ensureCertModal() {
    var existing = document.getElementById("learni-cert-modal");
    if (existing) {
      // Ensure the modal is attached to <body> so `position: fixed` truly covers the viewport
      // (some themes add transforms to content wrappers, which would otherwise scope fixed positioning).
      try {
        if (existing.parentNode && existing.parentNode !== document.body) {
          document.body.appendChild(existing);
        }
      } catch (e) {}

      // Ensure event handlers exist for server-rendered modals.
      if (!existing.getAttribute("data-learni-cert-bound")) {
        existing.setAttribute("data-learni-cert-bound", "1");
        existing.addEventListener("click", function (e) {
          var t = e && e.target ? e.target : null;
          if (!t || !t.getAttribute) return;

          if (t.getAttribute("data-learni-cert-close") || (t.closest && t.closest("[data-learni-cert-close]"))) {
            hideCertModal();
            return;
          }

          if (t.getAttribute("data-learni-cert-download") || (t.closest && t.closest("[data-learni-cert-download]"))) {
            downloadCertPdf();
          }
        });
      }
      if (!window.__learniCertEscapeBound) {
        window.__learniCertEscapeBound = true;
        document.addEventListener("keydown", function (e) {
          if (e && e.key === "Escape") hideCertModal();
        });
      }

      // Backfill expected IDs if a server-rendered modal exists.
      var notice = existing.querySelector && existing.querySelector(".learni-cert-modal__notice");
      if (notice && !notice.id) notice.id = "learni-cert-modal-notice";
      var body = existing.querySelector && existing.querySelector(".learni-cert-modal__body");
      if (body && !body.id) body.id = "learni-cert-modal-body";
      return existing;
    }

    var modal = document.createElement("div");
    modal.id = "learni-cert-modal";
    modal.className = "learni-cert-modal";
    modal.setAttribute("aria-hidden", "true");
    modal.innerHTML =
      '<div class="learni-cert-modal__backdrop" data-learni-cert-close="1"></div>' +
      '<div class="learni-cert-modal__panel" role="dialog" aria-modal="true" aria-label="Certificate">' +
      '<div class="learni-cert-modal__head">' +
      '<div class="learni-cert-modal__title">' +
      i18n("certTitle", "Certificate") +
      "</div>" +
      '<div class="learni-cert-modal__actions">' +
      '<button type="button" class="learni-btn secondary" data-learni-cert-download="1" style="display:none" disabled>' +
      i18n("downloadPdf", "Download PDF") +
      "</button>" +
      '<button type="button" class="learni-btn secondary" data-learni-cert-close="1">' +
      i18n("close", "Close") +
      "</button>" +
      "</div>" +
      "</div>" +
      '<div class="learni-cert-modal__notice" id="learni-cert-modal-notice" style="display:none"></div>' +
      '<div class="learni-cert-modal__body" id="learni-cert-modal-body"></div>' +
      "</div>";

    document.body.appendChild(modal);

    modal.addEventListener("click", function (e) {
      var t = e && e.target ? e.target : null;
      if (!t || !t.getAttribute) return;

      if (t.getAttribute("data-learni-cert-close") || (t.closest && t.closest("[data-learni-cert-close]"))) {
        hideCertModal();
        return;
      }

      if (t.getAttribute("data-learni-cert-download") || (t.closest && t.closest("[data-learni-cert-download]"))) {
        downloadCertPdf();
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e && e.key === "Escape") hideCertModal();
    });

    return modal;
  }

  function showCertModal() {
    var modal = ensureCertModal();
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function hideCertModal() {
    var modal = ensureCertModal();
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
    try {
      var body = document.getElementById("learni-cert-modal-body");
      if (body) body.innerHTML = "";
      var notice = document.getElementById("learni-cert-modal-notice");
      if (notice) {
        notice.textContent = "";
        notice.style.display = "none";
      }
    } catch (e) {}
    window.removeEventListener("resize", resizeCertStage);
  }

  function setCertModalBody(html) {
    var body = document.getElementById("learni-cert-modal-body");
    if (!body) {
      var modal = document.getElementById("learni-cert-modal");
      body = modal && modal.querySelector ? modal.querySelector(".learni-cert-modal__body") : null;
      if (body && !body.id) body.id = "learni-cert-modal-body";
    }
    if (body) body.innerHTML = html || "";
    resizeCertStage();
  }

  function setCertNotice(text) {
    var notice = document.getElementById("learni-cert-modal-notice");
    if (!notice) {
      var modal = document.getElementById("learni-cert-modal");
      notice = modal && modal.querySelector ? modal.querySelector(".learni-cert-modal__notice") : null;
      if (notice && !notice.id) notice.id = "learni-cert-modal-notice";
    }
    if (!notice) return;
    if (text) {
      notice.textContent = text;
      notice.style.display = "";
    } else {
      notice.textContent = "";
      notice.style.display = "none";
    }
  }

  function setCertDownloadEnabled(enabled) {
    var modal = document.getElementById("learni-cert-modal");
    var btn = modal && modal.querySelector ? modal.querySelector("[data-learni-cert-download]") : null;
    if (!btn) return;
    if (enabled) {
      btn.disabled = false;
      btn.style.display = "";
      btn.setAttribute("aria-disabled", "false");
    } else {
      btn.disabled = true;
      btn.style.display = "none";
      btn.setAttribute("aria-disabled", "true");
    }
  }

  function resizeCertStage() {
    var stage = document.querySelector("#learni-cert-modal-body .learni-cert-stage");
    var sheet = stage && stage.querySelector ? stage.querySelector(".learni-cert-sheet") : null;
    if (!stage || !sheet) return;

    // Measure natural sheet size (before scaling).
    stage.style.setProperty("--learni-cert-scale", "1");
    var sheetW = sheet.offsetWidth || 0;
    var sheetH = sheet.offsetHeight || 0;
    if (!sheetW || !sheetH) return;

    var availW = Math.max(320, window.innerWidth - 32);
    var availH = Math.max(320, window.innerHeight - 140);
    var scale = Math.min(availW / sheetW, availH / sheetH, 1);
    scale = Math.max(0.1, Math.round(scale * 100) / 100);

    stage.style.setProperty("--learni-cert-scale", String(scale));
    stage.style.width = Math.round(sheetW * scale) + "px";
    stage.style.height = Math.round(sheetH * scale) + "px";
  }

  function openCertificate(courseId) {
    if (!courseId) return;
    showCertModal();
    setCertNotice("");
    setCertDownloadEnabled(false);
    setCertModalBody('<div class="learni-quiz-modal__loading">' + escapeHtml(i18n("loading", "Loading…")) + "</div>");

    apiFetch("/learni/v1/courses/" + String(courseId) + "/certificate", { method: "GET" })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            var msg = (data && data.message) || "Failed to load certificate";
            throw new Error(msg);
          }
          return data;
        });
      })
      .then(function (data) {
        var eligible = !!(data && data.eligible);
        if (!eligible) {
          setCertNotice(i18n("ineligible", "Complete the course to unlock your certificate."));
        }
        setCertModalBody((data && data.html) || "");
        setCertDownloadEnabled(eligible);
        window.addEventListener("resize", resizeCertStage);
      })
      .catch(function (err) {
        setCertModalBody('<div class="learni-quiz-modal__error">' + escapeHtml((err && err.message) || "Failed to load certificate") + "</div>");
      });
  }

  function downloadCertPdf() {
    var sheet = document.querySelector("#learni-cert-modal-body .learni-cert-sheet");
    if (!sheet) return;
    var win = window.open("", "_blank");
    if (!win) return;

	    var css =
	      "@page{size:11in 8.5in;margin:0}" +
	      "html,body{width:11in;height:8.5in;margin:0;padding:0;overflow:hidden;-webkit-print-color-adjust:exact;print-color-adjust:exact}" +
	      "body{background:#fff;font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111827}" +
	      ".print-wrap{width:11in;height:8.5in;display:flex;justify-content:center;align-items:stretch;padding:0;margin:0}" +
	      ".learni-cert-sheet{width:11in;height:8.5in;margin:0;background:#fff;border:0;border-radius:0;box-shadow:none;overflow:hidden}" +
	      ".learni-cert-sheet__inner{height:100%;padding:42px 54px;box-sizing:border-box;display:flex;flex-direction:column;gap:8px}" +
	      ".learni-cert-sheet__top{min-height:42px;display:flex;align-items:center}" +
	      ".learni-cert-sheet__top.learni-align-left{justify-content:flex-start}" +
      ".learni-cert-sheet__top.learni-align-center{justify-content:center}" +
      ".learni-cert-sheet__top.learni-align-right{justify-content:flex-end}" +
      ".learni-cert-sheet__logo{max-height:64px;max-width:240px;object-fit:contain}" +
      ".learni-cert-sheet__title{font-size:34px;font-weight:800;letter-spacing:-.02em;margin-top:2px}" +
      ".learni-cert-sheet__kicker{opacity:.7;font-size:14px}" +
      ".learni-cert-sheet__name{font-size:28px;font-weight:700}" +
      ".learni-cert-sheet__course{font-size:24px;font-weight:600;opacity:.9}" +
      ".learni-cert-sheet__paragraph{font-size:14px;opacity:.85;max-width:76ch;white-space:pre-line}" +
      ".learni-cert-sheet__claims{margin-top:6px;padding:12px 14px;border:1px solid rgba(17,24,39,.08);border-radius:10px;background:rgba(17,24,39,.02);display:grid;gap:6px;width:fit-content}" +
      ".learni-cert-sheet__claims-title{font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;opacity:.7}" +
      ".learni-cert-sheet__claim{font-size:13px;opacity:.9}" +
      ".learni-cert-sheet__bottom{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-top:auto;padding-top:10px}" +
      ".learni-cert-sheet__meta{display:grid;gap:6px;font-size:12px;opacity:.85;min-width:0}" +
      ".learni-cert-sheet__meta-row{display:flex;gap:10px;align-items:baseline;min-width:0}" +
      ".learni-cert-sheet__meta-label{font-weight:700;opacity:.7;white-space:nowrap}" +
      ".learni-cert-sheet__meta-value{min-width:0;overflow:hidden;text-overflow:ellipsis}" +
      ".learni-cert-sheet__sig{display:grid;justify-items:end;gap:6px;min-width:260px;max-width:45%}" +
      ".learni-cert-sheet__sigimg{max-height:92px;max-width:100%;object-fit:contain}" +
      ".learni-cert-sheet__sigline{width:100%;max-width:320px;height:1px;background:rgba(17,24,39,.25)}" +
      ".learni-cert-sheet__siglabel{font-size:12px;opacity:.75}";

	    var html =
	      "<!doctype html><html><head><meta charset=\"utf-8\" />" +
	      "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />" +
	      "<title></title>" +
	      "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\"><link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>" +
	      "<link href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap\" rel=\"stylesheet\">" +
      "<style>" +
      css +
      "</style>" +
      "</head><body><div class=\"print-wrap\">" +
      sheet.outerHTML +
      "</div><script>window.onload=function(){setTimeout(function(){window.print();},150);};</script></body></html>";

    win.document.open();
    win.document.write(html);
    win.document.close();
  }

  function setupCertificates() {
    document.addEventListener("click", function (e) {
      var t = e && e.target ? e.target : null;
      if (!t || !t.closest) return;
      var trigger = t.closest("[data-learni-cert-open=\"1\"], .learni-course-cert-trigger");
      if (!trigger) return;

      e.preventDefault();
      var courseId = trigger.getAttribute("data-course-id") || "";
      openCertificate(courseId);
    });
  }

  function init() {
    syncBinomialAsideFromApi();
    setupBinomialQuiz();
    setupCertificates();
    maybeAutoStartQuizFromUrl();
  }

  window.LearniQuiz.apiFetch = apiFetch;
  window.LearniQuiz.startBinomialQuiz = startBinomialQuiz;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
