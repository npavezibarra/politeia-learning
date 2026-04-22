(function () {
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts
  var i18n = window.LearniQuiz.i18n;
  var apiFetch = window.LearniQuiz.apiFetch;
  var escapeHtml = window.LearniQuiz.escapeHtml;
  var fetchJson = window.LearniQuiz.fetchJson;
  var setQuizModalBody = window.LearniQuiz.setQuizModalBody;
  var setQuizModalTitle = window.LearniQuiz.setQuizModalTitle;
  var showQuizModal = window.LearniQuiz.showQuizModal;
  var hideQuizModal = window.LearniQuiz.hideQuizModal;
  var renderBinomialQuizFromData = window.LearniQuiz.renderBinomialQuizFromData;

  function startCrossEvalPartnerTest(courseId) {
    showQuizModal();
    setQuizModalTitle(i18n("quizCrossKicker", "Test Partner"));

    function renderWait(sessionId) {
      var html =
        '<div class="learni-quiz-intro">' +
        '<div class="learni-quiz-intro__kicker">' + escapeHtml(i18n("quizCrossKicker", "Test Partner")) + "</div>" +
        '<div class="learni-quiz-intro__title">' + escapeHtml(i18n("quizCrossWaitTitle", "Waiting…")) + "</div>" +
        '<div class="learni-quiz-intro__text">' + escapeHtml(i18n("quizCrossWaitBody", "")) + "</div>" +
        '<div class="learni-quiz-actions">' +
        '<button type="button" class="learni-btn secondary" id="learni-cross-eval-cancel">' + escapeHtml(i18n("quizCrossCancel", "Cancel")) + "</button>" +
        "</div>" +
        "</div>";
      setQuizModalBody(html);
      var cancelBtn = document.getElementById("learni-cross-eval-cancel");
      if (cancelBtn) {
        cancelBtn.addEventListener("click", function () {
          if (!sessionId) {
            hideQuizModal();
            return;
          }
          cancelBtn.disabled = true;
          apiFetch("/learni/v1/cross-eval/sessions/" + String(sessionId) + "/cancel", { method: "POST", body: JSON.stringify({}) })
            .then(function () { hideQuizModal(); })
            .catch(function () { hideQuizModal(); });
        });
      }
    }

    renderWait(0);

    apiFetch("/learni/v1/courses/" + courseId + "/cross-eval/create", { method: "POST", body: JSON.stringify({}) })
      .then(function (res) { return fetchJson(res, "Failed to create session"); })
      .then(function (data) {
        var sessionId = data && data.sessionId ? String(data.sessionId) : "";
        if (!sessionId) throw new Error("Invalid session");
        renderWait(sessionId);

        var stopped = false;
        var intId = null;
        var poll = function () {
          if (stopped) return;
          apiFetch("/learni/v1/cross-eval/sessions/" + sessionId, { method: "GET" })
            .then(function (res) { return fetchJson(res, "Failed to load status"); })
            .then(function (sdata) {
              var st = sdata && sdata.session && sdata.session.status ? String(sdata.session.status) : "";
              if (!st) return;
              if (st === "accepted" || st === "started") {
                stopped = true;
                try { if (intId) window.clearInterval(intId); } catch (e) {}
                setQuizModalBody('<div class="learni-quiz-modal__loading">' + escapeHtml(i18n("loading", "Loading…")) + "</div>");
                return apiFetch("/learni/v1/courses/" + courseId + "/cross-eval/binomial/start", {
                  method: "POST",
                  body: JSON.stringify({ sessionId: Number(sessionId) }),
                })
                  .then(function (res) { return fetchJson(res, "Failed to start quiz"); })
                  .then(function (qdata) {
                    renderBinomialQuizFromData(courseId, "final", qdata, null, {
                      mode: "cross",
                      submitAttempt: function (attemptId, answers) {
                        return apiFetch("/learni/v1/attempts/" + String(attemptId) + "/cross-eval/submit", {
                          method: "POST",
                          body: JSON.stringify({ sessionId: Number(sessionId), answers: answers }),
                        }).then(function (res) {
                          return fetchJson(res, "Failed to submit quiz");
                        });
                      },
                    });
                  });
              }
              if (st === "declined" || st === "expired" || st === "canceled") {
                stopped = true;
                try { if (intId) window.clearInterval(intId); } catch (e) {}
                setQuizModalBody('<div class="learni-quiz-modal__error">' + escapeHtml("Solicitud " + st + ".") + "</div>");
              }
            })
            .catch(function () {});
        };

        intId = window.setInterval(poll, 2000);
        window.setTimeout(function () {
          if (!stopped) poll();
        }, 250);
        window.setTimeout(function () {
          if (stopped) return;
          stopped = true;
          try { window.clearInterval(intId); } catch (e) {}
        }, 180000);
      })
      .catch(function (err) {
        setQuizModalBody('<div class="learni-quiz-modal__error">' + escapeHtml((err && err.message) || "Failed to start") + "</div>");
      });
  }

  // Expose to window.LearniQuiz
  window.LearniQuiz.startCrossEvalPartnerTest = startCrossEvalPartnerTest;
})();
