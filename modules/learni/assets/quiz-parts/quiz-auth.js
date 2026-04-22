(function () {
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts
  var getConfig = window.LearniQuiz.getConfig;
  var i18n = window.LearniQuiz.i18n;
  var escapeHtml = window.LearniQuiz.escapeHtml;
  var showQuizModal = window.LearniQuiz.showQuizModal;
  var setQuizModalTitle = window.LearniQuiz.setQuizModalTitle;
  var setQuizModalBody = window.LearniQuiz.setQuizModalBody;
  var startBinomialQuiz = window.LearniQuiz.startBinomialQuiz;
  var getCourseIdFromDom = window.LearniQuiz.getCourseIdFromDom;

  function openLoginRegister(courseId, phase) {
    try {
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

    try {
      var cfg2 = getConfig();
      var base = cfg2 && cfg2.authBaseUrl ? String(cfg2.authBaseUrl) : "";
      if (base) {
        var rUrl = new URL(window.location.href);
        if (courseId) rUrl.searchParams.set("learni_course_id", String(courseId));
        if (phase) rUrl.searchParams.set("learni_quiz_phase", String(phase));
        rUrl.searchParams.set("learni_auto_quiz", "1");

        var modalUrl = new URL(base);
        modalUrl.searchParams.set("pl_auth_view", "login");
        modalUrl.searchParams.set("redirect_to", rUrl.toString());
        window.location.href = modalUrl.toString();
        return true;
      }
    } catch (e0) {}

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
    var courseTitle = (typeof window.LearniQuiz.getCourseTitleFromDom === "function" ? window.LearniQuiz.getCourseTitleFromDom() : "") || i18n("course", "Course");

    var asideBtn = document.getElementById("learni-course-first-quiz");
    var ctaLabel = asideBtn && asideBtn.textContent ? String(asideBtn.textContent).trim() : i18n("takeFirstQuiz", "Take First Quiz");

    var cfg = getConfig();
    var lang = String((cfg && cfg.locale) || "") || String((document && document.documentElement && document.documentElement.lang) || "") || String((navigator && navigator.language) || "");
    var isSpanish = lang.toLowerCase().indexOf("es") === 0;
    var msg = isRegistered ? (isSpanish ? "\xA1Te has registrado con \xE9xito! Toma el First Quiz ahora." : "Registration successful! Take the First Quiz now.") : (isSpanish ? "Toma el First Quiz ahora." : "Take the First Quiz now.");

    showQuizModal();
    setQuizModalTitle(courseTitle);
    setQuizModalBody('<div class="learni-quiz-intro"><div class="learni-quiz-intro__text">' + escapeHtml(msg) + '</div><div class="learni-quiz-actions"><button type="button" class="learni-btn" id="learni-quiz-postauth-start">' + escapeHtml(ctaLabel) + "</button></div></div>");
    var btn = document.getElementById("learni-quiz-postauth-start");
    if (btn) btn.addEventListener("click", function () { startBinomialQuiz(courseId, phase || "initial"); });
  }

  function maybeAutoStartQuizFromUrl() {
    try {
      var cfg = getConfig();
      var url = new URL(window.location.href);
      if (url.searchParams.get("pl_auth_unverified_after_quiz")) {
        url.searchParams.delete("pl_auth_unverified_after_quiz");
        try { window.history.replaceState({}, document.title, url.toString()); } catch (e0) {}
      }
      if (!cfg || !cfg.isLoggedIn) return;
      var auto = url.searchParams.get("learni_auto_quiz");
      if (!auto) return;

      var courseId = url.searchParams.get("learni_course_id") || "";
      var phase = url.searchParams.get("learni_quiz_phase") || "initial";
      if (!courseId) return;

      var registered = url.searchParams.get("pl_auth_registered") === "1";
      url.searchParams.delete("learni_auto_quiz");
      url.searchParams.delete("learni_course_id");
      url.searchParams.delete("learni_quiz_phase");
      url.searchParams.delete("pl_auth_registered");
      try { window.history.replaceState({}, document.title, url.toString()); } catch (e) {}

      showPostAuthQuizPrompt(courseId, phase, { isRegistered: registered });
    } catch (e3) {}
  }

  // Expose to window.LearniQuiz
  window.LearniQuiz.openLoginRegister = openLoginRegister;
  window.LearniQuiz.showPostAuthQuizPrompt = showPostAuthQuizPrompt;
  window.LearniQuiz.maybeAutoStartQuizFromUrl = maybeAutoStartQuizFromUrl;
})();
