(function () {
  var MOBILE_MQ = "(max-width: 970px)";

  function qsa(root, sel) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function qs(root, sel) {
    return (root || document).querySelector(sel);
  }

  function setActive(root, key) {
    qsa(root, ".learni-tab").forEach(function (btn) {
      var isActive = (btn.getAttribute("data-learni-tab") || "") === key;
      btn.classList.toggle("is-active", isActive);
      btn.setAttribute("aria-selected", isActive ? "true" : "false");
    });
    qsa(root, ".learni-tabpanel").forEach(function (panel) {
      var isActive = (panel.getAttribute("data-learni-panel") || "") === key;
      panel.classList.toggle("is-active", isActive);
    });
  }

	  function relocateHeroCard(root) {
	    if (!root) return;

	    var card = qs(root, ".learni-course-hero-card");
	    if (!card) return;

    var heroInner = qs(root, ".learni-course-hero-inner");
    var body = qs(root, ".learni-course-body");
    if (!heroInner || !body) return;

    var isMobile = false;
    try {
      isMobile = window.matchMedia && window.matchMedia(MOBILE_MQ).matches;
    } catch (e) {
      isMobile = window.innerWidth <= 970;
    }

    // Store original location once so we can restore reliably.
    if (!card.__learniOriginalParent) {
      card.__learniOriginalParent = heroInner;
    }

	    if (isMobile) {
	      if (card.parentNode !== body) {
	        body.appendChild(card);
	      }
	      // Restore default positioning on mobile (card is rendered in-flow).
	      card.style.position = "";
	      card.style.top = "";
	      card.style.left = "";
	      card.style.right = "";
	      card.style.bottom = "";
	      card.style.width = "";
	    } else {
	      if (card.parentNode !== card.__learniOriginalParent) {
	        card.__learniOriginalParent.appendChild(card);
	      }
	      // Desktop: keep the card in its original layout (it should scroll with the page).
	      card.style.position = "";
	      card.style.top = "";
	      card.style.left = "";
	      card.style.right = "";
	      card.style.bottom = "";
	      card.style.width = "";
	    }
	  }

  function init() {
    qsa(document, ".learni-tabs").forEach(function (tabs) {
      var root = tabs.closest("#learni-course") || document;
      tabs.addEventListener("click", function (e) {
        var btn = e.target && e.target.closest ? e.target.closest(".learni-tab") : null;
        if (!btn) return;
        var key = btn.getAttribute("data-learni-tab") || "content";
        setActive(root, key);
      });
      setActive(root, "content");
      relocateHeroCard(root);
    });

    // Keep the card in the right place when resizing across the breakpoint.
    var onResize = function () {
      qsa(document, "#learni-course").forEach(function (root) {
        relocateHeroCard(root);
      });
    };
    window.addEventListener("resize", onResize);
    window.addEventListener("orientationchange", onResize);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
