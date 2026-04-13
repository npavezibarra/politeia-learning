(function () {
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
              .then(function () {
                hideQuizModal();
                window.location.reload();
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
  }

  function init() {
    setupBinomialQuiz();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();

