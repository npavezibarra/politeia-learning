(function () {
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts
  var i18n = window.LearniQuiz.i18n;
  var escapeHtml = window.LearniQuiz.escapeHtml;

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

  // Expose to window.LearniQuiz
  window.LearniQuiz.ensureQuizModal = ensureQuizModal;
  window.LearniQuiz.showQuizModal = showQuizModal;
  window.LearniQuiz.hideQuizModal = hideQuizModal;
  window.LearniQuiz.setQuizModalTitle = setQuizModalTitle;
  window.LearniQuiz.setQuizModalBody = setQuizModalBody;
  window.LearniQuiz.ensureCourseCompleteOverlay = ensureCourseCompleteOverlay;
  window.LearniQuiz.showCourseCompleteOverlay = showCourseCompleteOverlay;
  window.LearniQuiz.hideCourseCompleteOverlay = hideCourseCompleteOverlay;
  window.LearniQuiz.labelDelta = labelDelta;
  window.LearniQuiz.ringChartSvg = ringChartSvg;
  window.LearniQuiz.progressChart = progressChart;
})();
