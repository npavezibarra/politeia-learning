(function () {
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts
  var i18n = window.LearniQuiz.i18n;
  var formatTemplate = window.LearniQuiz.formatTemplate;
  var fetchJson = window.LearniQuiz.fetchJson;
  var apiFetch = window.LearniQuiz.apiFetch;
  var escapeHtml = window.LearniQuiz.escapeHtml;
  var showQuizModal = window.LearniQuiz.showQuizModal;
  var hideQuizModal = window.LearniQuiz.hideQuizModal;
  var setQuizModalTitle = window.LearniQuiz.setQuizModalTitle;
  var setQuizModalBody = window.LearniQuiz.setQuizModalBody;
  var showCourseCompleteOverlay = window.LearniQuiz.showCourseCompleteOverlay;
  var labelDelta = window.LearniQuiz.labelDelta;
  var ringChartSvg = window.LearniQuiz.ringChartSvg;
  var progressChart = window.LearniQuiz.progressChart;

  function getCourseTitleFromDom() {
    var el = document.getElementById("learni-course-title");
    if (el && el.textContent) return String(el.textContent).trim();
    var h1 = document.querySelector && document.querySelector("main .learni-course-header h1, main h1");
    if (h1 && h1.textContent) return String(h1.textContent).trim();
    return "";
  }

  function showFinalQuizCompletion(courseId) {
    showCourseCompleteOverlay('<div class="learni-quiz-modal__loading">Loading…</div>');

    apiFetch("/learni/v1/courses/" + courseId + "/binomial", { method: "GET" })
      .then(function (res) { return fetchJson(res, "Failed to load results"); })
      .then(function (data) {
        var attempts = (data && data.attempts) || {};
        var initial = attempts && attempts.initial ? attempts.initial : null;
        var final = attempts && attempts.final ? attempts.final : null;

        var iPct = initial && typeof initial.percent === "number" ? initial.percent : 0;
        var fPct = final && typeof final.percent === "number" ? final.percent : 0;
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

  function showBinomialComparisonInQuiz(courseId, opts) {
    opts = opts && typeof opts === "object" ? opts : {};
    var subject = opts.subject === "partnerOther" ? "partnerOther" : "self";
    var title = opts.title || i18n("quizCrossDoneTitle", "Listo");
    var kicker = opts.kicker || i18n("quizCrossKicker", "Test Partner");

    showQuizModal();
    setQuizModalTitle(title);
    setQuizModalBody('<div class="learni-quiz-modal__loading">' + escapeHtml(i18n("loading", "Loading…")) + "</div>");

    apiFetch("/learni/v1/courses/" + String(courseId) + "/binomial", { method: "GET" })
      .then(function (res) { return fetchJson(res, "Failed to load results"); })
      .then(function (data) {
        if (window.LearniQuiz.updatePartnerSectionFromApi) window.LearniQuiz.updatePartnerSectionFromApi(data);
        if (window.LearniQuiz.updateTestPartnerCtaFromApi) window.LearniQuiz.updateTestPartnerCtaFromApi(data, courseId);

        var attempts = (subject === "partnerOther") ? (data && data.partner && data.partner.otherAttempts) : (data && data.attempts);
        attempts = attempts || {};

        var initial = attempts.initial || null;
        var final = attempts.final || null;
        var iPct = Math.max(0, Math.min(100, Math.round(initial && typeof initial.percent === "number" ? initial.percent : 0)));
        var fPct = Math.max(0, Math.min(100, Math.round(final && typeof final.percent === "number" ? final.percent : 0)));
        var delta = fPct - iPct;

        var courseTitle = getCourseTitleFromDom();
        if (!courseTitle) courseTitle = String((data && data.partner && data.partner.courseTitle) || "");

        var html =
          '<div class="learni-quiz-results">' +
          '<div class="learni-quiz-results__kicker">' +
          escapeHtml(kicker) +
          "</div>" +
          '<div class="learni-quiz-results__text">' +
          escapeHtml(i18n("quizCrossResultsBody", "Estos fueron los resultados:")) +
          "</div>" +
          '<div class="learni-final-overlay__charts" aria-label="Resultados">' +
          progressChart(i18n("quizInitialKicker", "Evaluación inicial"), iPct, "learniGoldGradientInitialInQuiz") +
          progressChart(i18n("quizFinalKicker", "Evaluación final"), fPct, "learniGoldGradientFinalInQuiz") +
          "</div>" +
          '<div class="learni-final-overlay__variation">' +
          escapeHtml(labelDelta(delta)) +
          "</div>" +
          '<div class="learni-quiz-actions">' +
          '<button type="button" class="learni-btn" id="learni-quiz-cross-results-close">' +
          escapeHtml(i18n("quizCrossResultsClose", i18n("quizCrossDoneClose", "Cerrar"))) +
          "</button>" +
          "</div>" +
          "</div>";

        setQuizModalBody(html);
        var closeBtn = document.getElementById("learni-quiz-cross-results-close");
        if (closeBtn) closeBtn.addEventListener("click", function () { hideQuizModal(); });
      })
      .catch(function () {
        setQuizModalTitle(i18n("quizCrossDoneTitle", "Listo"));
        var html =
          '<div class="learni-quiz-results">' +
          '<div class="learni-quiz-results__kicker">' + escapeHtml(kicker) + "</div>" +
          '<div class="learni-quiz-results__text">' + escapeHtml(i18n("quizCrossDoneBody", "El resultado quedó guardado en la cuenta de tu partner.")) + "</div>" +
          '<div class="learni-quiz-actions">' +
          '<button type="button" class="learni-btn" id="learni-quiz-cross-done-close">' + escapeHtml(i18n("quizCrossDoneClose", "Cerrar")) + "</button>" +
          "</div>" +
          "</div>";
        setQuizModalBody(html);
        var closeBtn = document.getElementById("learni-quiz-cross-done-close");
        if (closeBtn) closeBtn.addEventListener("click", function () { hideQuizModal(); });
      });
  }

  function startBinomialQuiz(courseId, phase) {
    showQuizModal();
    setQuizModalTitle(phase === "final" ? i18n("quizFinalKicker", "Final Quiz") : i18n("quizInitialKicker", "First Quiz"));
    setQuizModalBody('<div class="learni-quiz-modal__loading">' + escapeHtml(i18n("loading", "Loading…")) + "</div>");

    var startReq = apiFetch("/learni/v1/courses/" + courseId + "/binomial/start", {
      method: "POST",
      body: JSON.stringify({ phase: phase }),
    }).then(function (res) { return fetchJson(res, "Failed to start quiz"); });

    var initialScoreReq = phase === "final"
      ? apiFetch("/learni/v1/courses/" + courseId + "/binomial", { method: "GET" })
          .then(function (res) { return fetchJson(res, "Failed to load results"); })
          .then(function (data) {
            var initial = (data && data.attempts && data.attempts.initial) || null;
            var pct = initial && typeof initial.percent === "number" ? initial.percent : null;
            return (pct !== null && isFinite(pct)) ? Math.max(0, Math.min(100, Math.round(pct))) : null;
          }).catch(function () { return null; })
      : Promise.resolve(null);

    Promise.all([startReq, initialScoreReq])
      .then(function (parts) {
        renderBinomialQuizFromData(courseId, phase, parts[0], parts[1], {
          mode: "self",
          submitAttempt: function (attemptId, answers) {
            return apiFetch("/learni/v1/attempts/" + String(attemptId) + "/submit", {
              method: "POST",
              body: JSON.stringify({ answers: answers }),
            }).then(function (res) { return fetchJson(res, "Failed to submit quiz"); });
          },
        });
      })
      .catch(function (err) {
        setQuizModalBody('<div class="learni-quiz-modal__error">' + escapeHtml((err && err.message) || "Failed to start quiz") + "</div>");
      });
  }

	  function renderBinomialQuizFromData(courseId, phase, data, initialScore, opts) {
	    opts = opts || {};
	    var mode = opts.mode || "self";
	    var submitAttempt = opts.submitAttempt;

    var attemptId = data && data.attempt && data.attempt.id ? String(data.attempt.id) : "";
    var title = data && data.quiz && data.quiz.title ? data.quiz.title : "Quiz";
    var introText = data && data.quiz && data.quiz.introText ? String(data.quiz.introText) : "";
    var questions = data && Array.isArray(data.questions) ? data.questions : [];

    setQuizModalTitle(title);

    function indexToLabel(i) {
      var n = parseInt(i, 10) + 1;
      var s = "";
      while (n > 0) {
        var rem = (n - 1) % 26;
        s = String.fromCharCode(65 + rem) + s;
        n = Math.floor((n - 1) / 26);
      }
      return s;
    }

    var state = {
      attemptId: attemptId, phase: phase, mode: mode, title: title, introText: introText,
      courseTitle: getCourseTitleFromDom() || title, initialScore: initialScore,
      questions: questions, slide: "intro", index: 0, answers: {}, answerOrders: {},
    };

	    function render() {
	      if (state.slide === "intro") {
        var phaseLabel = state.mode === "cross" ? i18n("quizCrossKicker", "Test Partner") : (state.phase === "final" ? i18n("quizFinalKicker", "Final Quiz") : i18n("quizInitialKicker", "First Quiz"));
        var prep = "";
        if (state.mode === "cross") {
          prep = formatTemplate(i18n("quizCrossPrepFinal", ""), { course: state.courseTitle, count: state.questions.length });
        } else if (state.phase === "final") {
          prep = formatTemplate(i18n("quizPrepFinal", ""), { course: state.courseTitle, count: state.questions.length, score: typeof state.initialScore === "number" ? state.initialScore : "—" });
        } else {
          prep = formatTemplate(i18n("quizPrepInitial", ""), { course: state.courseTitle, count: state.questions.length });
        }

        var extra = state.introText ? '<div class="learni-quiz-intro__text">' + escapeHtml(String(state.introText)) + "</div>" : "";
        var html = '<div class="learni-quiz-intro"><div class="learni-quiz-intro__kicker">' + escapeHtml(phaseLabel) + "</div>" +
          '<div class="learni-quiz-intro__title">' + escapeHtml(state.courseTitle) + "</div>" +
          '<div class="learni-quiz-intro__text">' + escapeHtml(prep) + "</div>" + extra +
          '<div class="learni-quiz-actions"><button type="button" class="learni-btn" id="learni-quiz-begin">' + escapeHtml(i18n("quizBegin", "Begin")) + "</button></div></div>";
        setQuizModalBody(html);
        var begin = document.getElementById("learni-quiz-begin");
        if (begin) begin.addEventListener("click", function () { state.slide = "q"; state.index = 0; render(); });
        return;
      }

	      var q = state.questions[state.index];
	      if (!q) { setQuizModalBody('<div class="learni-quiz-modal__error">No questions found.</div>'); return; }

	      function getImageUrl(obj) {
	        if (!obj || typeof obj !== "object") return "";
	        return String(obj.imageUrl || obj.image_url || obj.image || "");
	      }

	      var isLast = state.index === state.questions.length - 1;
	      var qid = String(q.id || "");
	      if (qid && !state.answerOrders[qid]) state.answerOrders[qid] = (q.answers || []).slice();
	      var answers = state.answerOrders[qid] || [];

	      var hasImageAnswers = false;
	      for (var i = 0; i < answers.length; i++) {
	        if (getImageUrl(answers[i])) { hasImageAnswers = true; break; }
	      }

	      var answersHtml = "";
	      if (hasImageAnswers) {
	        answers.forEach(function (a) {
	          var aid = String(a.id);
	          var checked = (qid && String(state.answers[qid]) === aid) ? ' checked="checked"' : "";
	          var img = getImageUrl(a);
	          var thumb = img
	            ? '<img src="' + escapeHtml(img) + '" alt="" loading="lazy">'
	            : '<span class="learni-quiz-img-a__thumb-placeholder" aria-hidden="true"></span>';
	          answersHtml +=
	            '<label class="learni-quiz-img-a">' +
	            '<input type="radio" name="q" value="' + escapeHtml(aid) + '"' + checked + ">" +
	            '<span class="learni-quiz-img-a__inner">' +
	            '<span class="learni-quiz-img-a__thumb">' + thumb + "</span>" +
	            '<span class="learni-quiz-img-a__text">' + escapeHtml(String(a.text || "")) + "</span>" +
	            '<span class="learni-quiz-img-a__check" aria-hidden="true"><span class="material-symbols-outlined">check</span></span>' +
	            "</span>" +
	            "</label>";
	        });
	      } else {
	        answers.forEach(function (a, idx) {
	          var aid = String(a.id);
	          var checked = (qid && String(state.answers[qid]) === aid) ? ' checked="checked"' : "";
	          answersHtml += '<label class="learni-quiz-a"><input type="radio" name="q" value="' + escapeHtml(aid) + '"' + checked + ">" +
	            '<span class="learni-quiz-a__label">' + escapeHtml(indexToLabel(idx)) + "</span>" +
	            '<span class="learni-quiz-a__text">' + escapeHtml(String(a.text || "")) + "</span></label>";
	        });
	      }

	      var qImgUrl = getImageUrl(q);
	      var qImgHtml = qImgUrl
	        ? '<div class="learni-quiz-q__img"><img src="' + escapeHtml(qImgUrl) + '" alt="" loading="lazy"></div>'
	        : "";

	      var pickerKicker = hasImageAnswers
	        ? '<div class="learni-quiz-a-kicker">' + escapeHtml(i18n("quizPickOne", "Selecciona la opción correcta")) + "</div>"
	        : "";

	      var htmlQ = '<form id="learni-quiz-slide" class="learni-quiz-form" data-attempt-id="' + escapeHtml(state.attemptId) + '">' +
	        '<div class="learni-quiz-q"><div class="learni-quiz-q__meta">' + escapeHtml(formatTemplate(i18n("quizQuestionOf", "Question {current} of {total}"), { current: state.index + 1, total: state.questions.length })) + "</div>" +
	        qImgHtml +
	        '<div class="learni-quiz-q__text">' + escapeHtml(String(q.prompt || "")) + "</div></div>" +
	        pickerKicker +
	        '<div class="learni-quiz-a-list' + (hasImageAnswers ? " learni-quiz-a-list--grid" : "") + '">' + answersHtml + "</div>" +
	        '<div class="learni-quiz-actions">' + (state.index > 0 ? '<button type="button" class="learni-btn secondary" id="learni-quiz-prev">' + escapeHtml(i18n("quizBack", "Back")) + "</button>" : "") +
	        '<button type="submit" class="learni-btn" id="learni-quiz-next">' + escapeHtml(isLast ? i18n("quizSubmit", "Submit") : i18n("quizNext", "Next")) + "</button></div></form>";

      setQuizModalBody(htmlQ);
      var form = document.getElementById("learni-quiz-slide");
      var prevBtn = document.getElementById("learni-quiz-prev");
      if (prevBtn) prevBtn.addEventListener("click", function () { state.index = Math.max(0, state.index - 1); render(); });
      if (!form) return;
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var chosen = form.querySelector('input[name="q"]:checked');
        if (!chosen || !chosen.value) { alert(i18n("quizChooseAnswer", "Please choose an answer.")); return; }
        state.answers[qid] = Number(chosen.value);

        if (!isLast) { state.index++; render(); return; }

        var submitBtn = document.getElementById("learni-quiz-next");
        if (submitBtn) submitBtn.disabled = true;
        if (Object.keys(state.answers).length !== state.questions.length) { if (submitBtn) submitBtn.disabled = false; alert(i18n("quizAnswerAll", "Please answer all questions.")); return; }
        if (!submitAttempt) { if (submitBtn) submitBtn.disabled = false; alert("Missing submit handler"); return; }

        submitAttempt(state.attemptId, state.answers)
          .then(function (data) {
            if (state.phase === "final") {
              if (state.mode === "cross") {
                window.LearniQuiz.showBinomialComparisonInQuiz(courseId, { subject: "partnerOther", title: i18n("quizCrossDoneTitle", "Listo"), kicker: i18n("quizCrossKicker", "Test Partner") });
                return;
              }
              hideQuizModal(); showFinalQuizCompletion(courseId); return;
            }
            var pct = Math.max(0, Math.min(100, Math.round(data && typeof data.percent === "number" ? data.percent : 0)));
            var score = data && typeof data.score === "number" ? data.score : 0;
            var total = data && typeof data.total === "number" ? data.total : state.questions.length;

            setQuizModalTitle("Resultados");
            var ctaLabel = i18n("quizResultsContinue", "Continuar");
            var isCheckout = false;
            if (state.phase === "initial") {
              if (data.hasAccess) {
                ctaLabel = "COMENZAR CURSO";
              } else {
                ctaLabel = "COMPRAR CURSO";
                isCheckout = true;
              }
            }

            var html = '<div class="learni-quiz-results"><div class="learni-quiz-results__kicker">Evaluación inicial</div>' +
              '<div class="learni-quiz-results__chart">' + ringChartSvg(pct, "learniGoldGradientResults", "learni-quiz-results__chart-svg") + "</div>" +
              '<div class="learni-quiz-results__meta">' + score + " de " + total + " correctas</div>" +
              '<div class="learni-quiz-results__text">Felicitaciones: obtuviste <strong>' + pct + '%</strong> de respuestas correctas en la Evaluación Inicial. Ahora completa todas las lecciones de este curso. Al finalizar, podrás rendir la Evaluación Final y compararemos tu resultado inicial con el final para ver tu progreso.</div>' +
              '<div class="learni-quiz-actions"><button type="button" class="learni-btn" id="learni-quiz-results-continue">' + escapeHtml(ctaLabel) + '</button></div></div>';
            setQuizModalBody(html);
            var cont = document.getElementById("learni-quiz-results-continue");
            if (cont) cont.addEventListener("click", function () {
              if (isCheckout && data.checkoutUrl) {
                window.location.href = data.checkoutUrl;
                return;
              }
              hideQuizModal();
              try { var url = new URL(window.location.href); url.searchParams.set("pl_auth_unverified_after_quiz", "1"); window.location.href = url.toString(); } catch (e) { window.location.reload(); }
            });
          })
          .catch(function (err) { if (submitBtn) submitBtn.disabled = false; alert((err && err.message) || "Failed to submit quiz"); });
      });
    }
    render();
  }

  // Expose to window.LearniQuiz
  window.LearniQuiz.showFinalQuizCompletion = showFinalQuizCompletion;
  window.LearniQuiz.showBinomialComparisonInQuiz = showBinomialComparisonInQuiz;
  window.LearniQuiz.startBinomialQuiz = startBinomialQuiz;
  window.LearniQuiz.renderBinomialQuizFromData = renderBinomialQuizFromData;
})();
