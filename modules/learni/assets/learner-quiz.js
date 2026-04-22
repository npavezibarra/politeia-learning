(function () {
  // Learni Quiz Orchestrator
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts from modules
  var syncBinomialAsideFromApi = window.LearniQuiz.syncBinomialAsideFromApi;
  var setupBinomialQuiz = window.LearniQuiz.setupBinomialQuiz;
  var setupCertificates = window.LearniQuiz.setupCertificates;
  var maybeAutoStartQuizFromUrl = window.LearniQuiz.maybeAutoStartQuizFromUrl;
  var maybeAutoOpenCertificateFromUrl = window.LearniQuiz.maybeAutoOpenCertificateFromUrl;

  /**
   * Scrapes the course title from the DOM.
   */
  function getCourseTitleFromDom() {
    var titleEl = document.querySelector && document.querySelector(".learni-course-card-title");
    if (titleEl) return String(titleEl.textContent || "").trim();
    var h1 = document.querySelector && document.querySelector("h1");
    if (h1) return String(h1.textContent || "").trim();
    return "";
  }

  // Expose helper
  window.LearniQuiz.getCourseTitleFromDom = getCourseTitleFromDom;

  /**
   * Main entry point.
   */
  function init() {
    // 1. Sync sidebar status from API (attempts, CTAs)
    if (typeof syncBinomialAsideFromApi === "function") {
      syncBinomialAsideFromApi();
    }

    // 2. Set up click delegates for quizzes
    if (typeof setupBinomialQuiz === "function") {
      setupBinomialQuiz();
    }

    // 3. Set up click delegates for certificates
    if (typeof setupCertificates === "function") {
      setupCertificates();
    }

    // 4. Handle auto-launching from URL params (after redirect/auth)
    if (typeof maybeAutoStartQuizFromUrl === "function") {
      maybeAutoStartQuizFromUrl();
    }
    if (typeof maybeAutoOpenCertificateFromUrl === "function") {
      maybeAutoOpenCertificateFromUrl();
    }
  }

  // Launch
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
