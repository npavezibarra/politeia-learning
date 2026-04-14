(function () {
  // Expose a tiny public API for lesson overlays.
  window.LearniQuiz = window.LearniQuiz || {};

  function getConfig() {
    return window.Learni || {};
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

  function ensureQuizModal() {
    var existing = document.getElementById("learni-quiz-modal");
    if (existing) return existing;

    var modal = document.createElement("div");
    modal.id = "learni-quiz-modal";
    modal.className = "learni-quiz-modal";
    modal.innerHTML =
      '<div class="learni-quiz-modal__backdrop" data-learni-quiz-close="1"></div>' +
      '<div class="learni-quiz-modal__panel" role="dialog" aria-modal="true" aria-label="Quiz">' +
      '<div class="learni-quiz-modal__head">' +
      '<div class="learni-quiz-modal__title" id="learni-quiz-modal-title">Quiz</div>' +
      '<button type="button" class="learni-quiz-modal__close" data-learni-quiz-close="1">Close</button>' +
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
        var certificateUrl = data && data.certificateUrl ? String(data.certificateUrl) : "";

        var certBlock = "";
        if (certificateUrl) {
          certBlock =
            '<div class="learni-final-overlay__cert">Has obtenido el certificado de este curso.</div>' +
            '<a class="learni-btn learni-course-primary-btn" href="' +
            escapeHtml(certificateUrl) +
            '" target="_blank" rel="noopener">VER CERTIFICADO</a>';
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
    setQuizModalTitle(phase === "final" ? "Final Quiz" : "First Quiz");
    setQuizModalBody('<div class="learni-quiz-modal__loading">Loading…</div>');

    apiFetch("/learni/v1/courses/" + courseId + "/binomial/start", {
      method: "POST",
      body: JSON.stringify({ phase: phase }),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) {
            var msg = (data && data.message) || "Failed to start quiz";
            throw new Error(msg);
          }
          return data;
        });
      })
      .then(function (data) {
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
          questions: questions,
          slide: "intro", // intro | q
          index: 0,
          answers: {}, // questionId -> answerId
        };

        function render() {
          if (state.slide === "intro") {
            var phaseLabel = state.phase === "final" ? "Final Quiz" : "First Quiz";
            var intro = state.introText
              ? '<div class="learni-quiz-intro__text">' + escapeHtml(state.introText) + "</div>"
              : "";
            var html =
              '<div class="learni-quiz-intro">' +
              '<div class="learni-quiz-intro__kicker">' +
              escapeHtml(phaseLabel) +
              "</div>" +
              '<div class="learni-quiz-intro__title">' +
              escapeHtml(state.title) +
              "</div>" +
              intro +
              '<div class="learni-quiz-actions">' +
              '<button type="button" class="learni-btn" id="learni-quiz-begin">Begin</button>' +
              '<button type="button" class="learni-btn secondary" data-learni-quiz-close="1">Cancel</button>' +
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
          answers.forEach(function (a) {
            answersHtml +=
              '<label class="learni-quiz-a">' +
              '<input type="radio" name="q" value="' +
              escapeHtml(String(a.id)) +
              '">' +
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
            '<div class="learni-quiz-q__meta">Question ' +
            (state.index + 1) +
            " of " +
            state.questions.length +
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
              ? '<button type="button" class="learni-btn secondary" id="learni-quiz-prev">Back</button>'
              : '<button type="button" class="learni-btn secondary" data-learni-quiz-close="1">Cancel</button>') +
            '<button type="submit" class="learni-btn" id="learni-quiz-next">' +
            (isLast ? "Submit" : "Next") +
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
              alert("Please choose an answer.");
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
              alert("Please answer all questions.");
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
                    window.location.reload();
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
    var firstBtn = document.getElementById("learni-course-first-quiz");
    var finalBtn = document.getElementById("learni-course-final-quiz");
    var restartBtn = document.getElementById("learni-course-restart");

    function onStartClick(btn) {
      if (!btn || btn.disabled) return;
      var courseId = btn.getAttribute("data-course-id") || "";
      var phase = btn.getAttribute("data-phase") || "initial";
      if (!courseId) return;
      startBinomialQuiz(courseId, phase);
    }

    if (firstBtn) {
      firstBtn.addEventListener("click", function () {
        onStartClick(firstBtn);
      });
    }

    if (finalBtn) {
      finalBtn.addEventListener("click", function () {
        onStartClick(finalBtn);
      });
    }

    if (restartBtn) {
      restartBtn.addEventListener("click", function () {
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
      });
    }
  }

  function ensureCertModal() {
    return document.getElementById("learni-cert-modal");
  }

  function showCertModal() {
    var modal = ensureCertModal();
    if (!modal) return;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function hideCertModal() {
    var modal = ensureCertModal();
    if (!modal) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  function certPrintStyles() {
    return (
      "@page{size:letter landscape;margin:0.5in;}html,body{padding:0;margin:0;background:#fff;}" +
      ".wrap{padding:0.2in;}" +
      ".sheet{width:11in;height:8.5in;aspect-ratio:auto;box-shadow:none;border:0;border-radius:0;}" +
      ".sheet .actions{display:none;}"
    );
  }

  function printCertificate() {
    // We add a temporary class to the body to handle print visibility in CSS.
    // This avoids popup blockers entirely by printing the current page with a dedicated print stylesheet.
    document.body.classList.add("is-printing-cert");
    
    // We need to ensure the scaling is removed during print so it uses the full 11in width.
    // The CSS @media print will handle this, but calling window.print() here is the trigger.
    window.print();
    
    // Remote the class after the print dialog closes.
    // Most browsers pause execution while the dialog is open.
    setTimeout(function() {
      document.body.classList.remove("is-printing-cert");
    }, 500);
  }

  function setupCertificates() {
    function sheetPxSize() {
      // Standard Letter Landscape is 11in x 8.5in. CSS uses 96px/in.
      return { w: 11 * 96, h: 8.5 * 96 };
    }

    function ensureStage(sheetEl) {
      if (!sheetEl) return null;
      var parent = sheetEl.parentNode;
      if (parent && parent.classList && parent.classList.contains("learni-cert-stage")) return parent;

      var stage = document.createElement("div");
      stage.className = "learni-cert-stage";
      if (parent) {
        parent.insertBefore(stage, sheetEl);
        stage.appendChild(sheetEl);
      }
      return stage;
    }

    function applyScale(containerEl, sheetEl) {
      if (!containerEl || !sheetEl) return;
      var sz = sheetPxSize();
      var pad = 48; // modal body padding
      var rect = null;
      try {
        rect = containerEl.getBoundingClientRect ? containerEl.getBoundingClientRect() : null;
      } catch (e) {}
      var cw = rect && rect.width ? rect.width : containerEl.clientWidth || 0;
      var ch = rect && rect.height ? rect.height : containerEl.clientHeight || 0;

      if (cw < 100 || ch < 100) return;

      var availW = Math.max(100, cw - pad);
      var availH = Math.max(100, ch - pad);
      
      // Restore dual-axis scaling to ensure the certificate fits both width and height
      // and remains centered without overflowing the viewport.
      var scale = Math.min(availW / sz.w, availH / sz.h, 1);
      
      scale = Math.max(0.1, Math.round(scale * 100) / 100);

      var stage = ensureStage(sheetEl);
      if (!stage) return;
      stage.style.width = String(sz.w * scale) + "px";
      stage.style.height = String(sz.h * scale) + "px";
      sheetEl.style.setProperty("--learni-cert-scale", String(scale));

      try {
        var modalRoot = containerEl.closest ? containerEl.closest(".learni-cert-modal") : null;
        if (modalRoot && modalRoot.style) {
          modalRoot.style.setProperty("--learni-cert-stage-w", String(sz.w * scale) + "px");
          modalRoot.style.setProperty("--learni-cert-stage-h", String(sz.h * scale) + "px");
        }
      } catch (e) {}
    }

    function scheduleScale(containerEl, sheetEl) {
      try {
        requestAnimationFrame(function () {
          applyScale(containerEl, sheetEl);
          requestAnimationFrame(function () {
            applyScale(containerEl, sheetEl);
          });
        });
      } catch (e) {}
      setTimeout(function () { applyScale(containerEl, sheetEl); }, 100);
      setTimeout(function () { applyScale(containerEl, sheetEl); }, 400);
    }

    // Intercept trigger clicks
    document.addEventListener("click", function (e) {
      var trigger = e.target && e.target.closest && e.target.closest(".learni-course-cert-trigger");
      if (!trigger) return;

      var modal = ensureCertModal();
      if (!modal) return; // Fallback to normal server-side link (target=_blank)

      e.preventDefault();
      showCertModal();

      var body = modal.querySelector(".learni-cert-modal__body");
      var sheet = modal.querySelector("[data-learni-cert-sheet]");
      if (body && sheet) scheduleScale(body, sheet);
    });

    var modal = ensureCertModal();
    if (modal) {
      // Move modal to body to ensure full-screen backdrop coverage (escapes sidebar/content containers)
      document.body.appendChild(modal);

      var bodyEl = modal.querySelector(".learni-cert-modal__body");
      var sheetEl = modal.querySelector("[data-learni-cert-sheet]");

      if (bodyEl && sheetEl) {
        window.addEventListener("resize", function () {
          scheduleScale(bodyEl, sheetEl);
        });
      }

      modal.addEventListener("click", function (e) {
        var t = e && e.target ? e.target : null;
        if (!t || !t.getAttribute) return;

        if (t.getAttribute("data-learni-cert-close") || t.closest("[data-learni-cert-close]")) {
          hideCertModal();
          return;
        }

        if (t.getAttribute("data-learni-cert-download") || t.closest("[data-learni-cert-download]")) {
          printCertificate();
        }
      });

      document.addEventListener("keydown", function (e) {
        if (e && e.key === "Escape") hideCertModal();
      });
    }
  }

  function init() {
    setupBinomialQuiz();
    setupCertificates();
  }

  window.LearniQuiz.apiFetch = apiFetch;
  window.LearniQuiz.startBinomialQuiz = startBinomialQuiz;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
