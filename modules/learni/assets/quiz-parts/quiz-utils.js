(function () {
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

  function fetchJson(res, defaultMessage) {
    return res.json().then(function (data) {
      if (!res.ok) {
        var msg = (data && data.message) || defaultMessage || "Request failed";
        throw new Error(msg);
      }
      return data;
    });
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
    var s = String(str || "");
    var h = 2166136261;
    for (var i = 0; i < s.length; i++) {
      h ^= s.charCodeAt(i);
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

  function getCourseIdFromDom() {
    var el = document.getElementById("learni-course");
    return el ? (el.getAttribute("data-course-id") || "") : "";
  }

  // Expose to window.LearniQuiz
  window.LearniQuiz.getConfig = getConfig;
  window.LearniQuiz.i18n = i18n;
  window.LearniQuiz.formatTemplate = formatTemplate;
  window.LearniQuiz.fetchJson = fetchJson;
  window.LearniQuiz.apiFetch = apiFetch;
  window.LearniQuiz.escapeHtml = escapeHtml;
  window.LearniQuiz.hash32 = hash32;
  window.LearniQuiz.stableShuffleBySeed = stableShuffleBySeed;
  window.LearniQuiz.getCourseIdFromDom = getCourseIdFromDom;
})();
