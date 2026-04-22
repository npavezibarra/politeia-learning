(function () {
  window.LearniQuiz = window.LearniQuiz || {};

  // Dependency shortcuts
  var getConfig = window.LearniQuiz.getConfig;
  var i18n = window.LearniQuiz.i18n;
  var apiFetch = window.LearniQuiz.apiFetch;
  var escapeHtml = window.LearniQuiz.escapeHtml;
  var getCourseIdFromDom = window.LearniQuiz.getCourseIdFromDom;

  function ensureCertModal() {
    var existing = document.getElementById("learni-cert-modal");
    if (existing) {
      try {
        if (existing.parentNode && existing.parentNode !== document.body) {
          document.body.appendChild(existing);
        }
      } catch (e) {}

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
      '<div class="learni-cert-modal__title">' + i18n("certTitle", "Certificate") + "</div>" +
      '<div class="learni-cert-modal__actions">' +
      '<button type="button" class="learni-btn secondary" data-learni-cert-download="1" style="display:none" disabled>' + i18n("downloadPdf", "Download PDF") + "</button>" +
      '<button type="button" class="learni-btn secondary" data-learni-cert-close="1">' + i18n("close", "Close") + "</button>" +
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
      ".learni-cert-sheet__subtitle{font-size:16px;font-weight:600;letter-spacing:-.01em;color:#6b7280;margin-bottom:8px}" +
      ".learni-cert-sheet__main{flex:1;display:flex;flex-direction:column;justify-content:center;gap:12px}" +
      ".learni-cert-sheet__recipient-pre{font-size:18px;color:#6b7280}" +
      ".learni-cert-sheet__recipient-name{font-size:48px;font-weight:800;letter-spacing:-.03em;color:#111827;line-height:1}" +
      ".learni-cert-sheet__body{font-size:18px;color:#374151;max-width:80%;line-height:1.5}" +
      ".learni-cert-sheet__body strong{color:#111827;font-weight:700}" +
      ".learni-cert-sheet__footer{display:flex;justify-content:space-between;align-items:flex-end;margin-top:24px}" +
      ".learni-cert-sheet__stats{display:flex;gap:32px}" +
      ".learni-cert-sheet__stat{display:flex;flex-direction:column;gap:4px}" +
      ".learni-cert-sheet__stat-label{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af}" +
      ".learni-cert-sheet__stat-value{font-size:18px;font-weight:700;color:#374151}" +
      ".learni-cert-sheet__qr-wrap{text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px}" +
      ".learni-cert-sheet__qr{width:84px;height:84px;background:#f3f4f6;border-radius:6px}" +
      ".learni-cert-sheet__id{font-size:12px;color:#9ca3af;font-family:monospace}";

    var html =
      '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + (i18n("certTitle", "Certificate")) + "</title>" +
      "<style>" + css + "</style></head><body>" +
      '<div class="print-wrap">' + sheet.outerHTML + "</div>" +
      "<script>window.onload = function(){ window.print(); window.onafterprint = function(){ window.close(); }; };</script>" +
      "</body></html>";

    win.document.open();
    win.document.write(html);
    win.document.close();
  }

  function maybeAutoOpenCertificateFromUrl() {
    try {
      var cfg = getConfig();
      if (!cfg || !cfg.isLoggedIn) return;
      var url = new URL(window.location.href);
      if (url.searchParams.get("learni_open_cert") !== "1") return;

      var courseId = getCourseIdFromDom();
      if (!courseId) return;

      // Clean URL to avoid reopening on refresh/back.
      url.searchParams.delete("learni_open_cert");
      try {
        window.history.replaceState({}, document.title, url.toString());
      } catch (e0) {}

      openCertificate(courseId);
    } catch (e) {}
  }

  // Expose to window.LearniQuiz
  window.LearniQuiz.ensureCertModal = ensureCertModal;
  window.LearniQuiz.showCertModal = showCertModal;
  window.LearniQuiz.hideCertModal = hideCertModal;
  window.LearniQuiz.setCertModalBody = setCertModalBody;
  window.LearniQuiz.setCertNotice = setCertNotice;
  window.LearniQuiz.setCertDownloadEnabled = setCertDownloadEnabled;
  window.LearniQuiz.resizeCertStage = resizeCertStage;
  window.LearniQuiz.openCertificate = openCertificate;
  window.LearniQuiz.downloadCertPdf = downloadCertPdf;
  window.LearniQuiz.maybeAutoOpenCertificateFromUrl = maybeAutoOpenCertificateFromUrl;
})();
