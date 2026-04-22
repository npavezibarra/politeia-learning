/**
 * Navigation JS - Handles Dropdowns and Toggles
 */
(function() {
    var selector = ".pl-user-menu-item";

    function closeAll(except) {
        document.querySelectorAll(selector + ".is-open").forEach(function(item) {
            if (except && item === except) { return; }
            item.classList.remove("is-open");
            var toggle = item.querySelector(".pl-user-menu__toggle");
            if (toggle) { 
                toggle.setAttribute("aria-expanded", "false"); 
            }
        });
    }

    document.addEventListener("click", function(event) {
        var item = event.target.closest(selector);
        var toggle = event.target.closest(".pl-user-menu__toggle");

        if (toggle && item) {
            event.preventDefault();
            var isOpen = item.classList.contains("is-open");
            closeAll(item);
            if (!isOpen) {
                item.classList.add("is-open");
                toggle.setAttribute("aria-expanded", "true");
            }
            return;
        }

        // Close if clicking outside the dropdown
        if (!event.target.closest(".pl-user-menu__dropdown")) {
            closeAll();
        }
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") { 
            closeAll(); 
        }
    });
})();
/* Smartphone menu overlay (<= 850px)
   - Opens from the subbar hamburger
   - Shows context-aware submenu items (course editor, specialization, programa, sales, students)
*/

(function () {
  'use strict';

  function isMobile() {
    return window.matchMedia && window.matchMedia('(max-width: 850px)').matches;
  }

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.from((root || document).querySelectorAll(sel));
  }

  function isVisible(el) {
    if (!el) return false;
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function getSectionFromUrl() {
    try {
      const section = new URLSearchParams(window.location.search).get('section') || '';
      return section === '' ? 'create-course' : section;
    } catch (_) {
      return 'create-course';
    }
  }

  function getVisibleFormContext() {
    const courseForm = qs('#pcg-course-form-section');
    if (courseForm && isVisible(courseForm)) return 'course';
    const specForm = qs('#pcg-specialization-form-section');
    if (specForm && isVisible(specForm)) return 'specialization';
    const progForm = qs('#pcg-programa-form-section');
    if (progForm && isVisible(progForm)) return 'programa';
    return '';
  }

  function getSources() {
    const ctx = getVisibleFormContext();
    if (ctx === 'course') return qsa('#pcg-course-form-section .pcg-segment');
    if (ctx === 'specialization') return qsa('#pcg-specialization-form-section .pcg-spec-segment');
    if (ctx === 'programa') return qsa('#pcg-programa-form-section .pcg-prog-segment');

    const section = getSectionFromUrl();
    if (section === 'sales') return qsa('#pcg-sales-tabs .pcg-segment[data-sales-tab]');
    if (section === 'students') return qsa('#pcg-students-tabs .pcg-segment[data-students-tab]');
    return [];
  }

  function openPanel() {
    if (!isMobile()) return;

    const overlay = qs('[data-pl-smartphone-menu-overlay]');
    const panel = qs('[data-pl-smartphone-menu-panel]');
    const itemsRoot = qs('[data-pl-smartphone-menu-items]');
    if (!overlay || !panel || !itemsRoot) return;

    itemsRoot.innerHTML = '';

    const sources = getSources();
    if (!sources.length) {
      const sectionLabels = [
        { id: 'create-course', label: 'MIS CURSOS' },
        { id: 'mis-escritos', label: 'MIS ESCRITOS' },
        { id: 'especializacion', label: 'ESPECIALIZACIÓN' },
        { id: 'create-group', label: 'PROGRAMAS' },
        { id: 'sales', label: 'VENTAS' },
        { id: 'students', label: 'ESTUDIANTES' },
        { id: 'profile', label: 'PERFIL' }
      ];
      const currentSection = getSectionFromUrl() || 'create-course';

      sectionLabels.forEach((sec) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pl-smartphone-menu-panel__item';
        if (sec.id === currentSection) btn.classList.add('is-active');
        btn.textContent = sec.label;
        btn.addEventListener('click', function () {
            window.location.href = '?section=' + sec.id;
        });
        itemsRoot.appendChild(btn);
      });
    } else {
      sources.forEach((src) => {
        const label = (src.textContent || '').trim();
        if (!label) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pl-smartphone-menu-panel__item';
        if (src.classList.contains('active')) btn.classList.add('is-active');
        btn.textContent = label;
        btn.addEventListener('click', function () {
          // Trigger underlying tab/segment
          src.click();
          closePanel();
        });
        itemsRoot.appendChild(btn);
      });
    }

    overlay.hidden = false;
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('pl-smartphone-menu-open');
    window.setTimeout(() => {
      overlay.classList.add('is-open');
      panel.classList.add('is-open');
    }, 0);
  }

  function syncActionBar() {
    const bar = qs('[data-pl-smartphone-actionbar]');
    const btn = qs('[data-pl-smartphone-action-btn]');
    if (!bar || !btn) return;

    if (!isMobile()) {
      bar.hidden = true;
      bar.classList.remove('is-visible');
      document.documentElement.classList.remove('pl-smartphone-has-actionbar');
      return;
    }

    // Hide when inside an editor form (course/escrito/spec/programa).
    if (getVisibleFormContext()) {
      bar.hidden = true;
      bar.classList.remove('is-visible');
      document.documentElement.classList.remove('pl-smartphone-has-actionbar');
      return;
    }

    const section = getSectionFromUrl();
    const actions = {
      'create-course': { label: 'CREAR CURSO', target: '#pcg-show-creator-form', visibleRoot: '#pcg-my-courses-section', fn: 'pcgOpenCourseCreate' },
      'mis-escritos': { label: 'CREAR ESCRITO', target: '#pcg-show-escritos-form', visibleRoot: '#pcg-my-escritos-section', fn: 'pcgOpenEscritoCreate' },
      'especializacion': { label: 'CREAR ESPECIALIZACIÓN', target: '#pcg-show-specialization-form', visibleRoot: '#pcg-my-specializations-section', fn: 'pcgOpenSpecializationCreate' },
      'create-group': { label: 'CREAR PROGRAMA', target: '#pcg-show-programa-form', visibleRoot: '#pcg-my-programas-section', fn: 'pcgOpenProgramaCreate' },
    };

    const cfg = actions[section];
    if (!cfg) {
      bar.hidden = true;
      bar.classList.remove('is-visible');
      document.documentElement.classList.remove('pl-smartphone-has-actionbar');
      return;
    }

    const root = qs(cfg.visibleRoot);
    const trigger = qs(cfg.target);
    if (!root || !isVisible(root) || !trigger) {
      bar.hidden = true;
      bar.classList.remove('is-visible');
      document.documentElement.classList.remove('pl-smartphone-has-actionbar');
      return;
    }

    btn.textContent = cfg.label;
    btn.onclick = function (e) {
      if (e && typeof e.preventDefault === 'function') e.preventDefault();
      if (e && typeof e.stopPropagation === 'function') e.stopPropagation();

      // Prefer calling the app's public create function if available (more robust than DOM triggers).
      if (cfg.fn && typeof window[cfg.fn] === 'function') {
        window[cfg.fn]();
        return;
      }

      // Re-query on each click (the dashboard re-renders sections, so nodes may be replaced).
      const freshTrigger = qs(cfg.target);
      if (!freshTrigger) {
        // eslint-disable-next-line no-console
        console.warn('[smartphone-menu] action trigger not found:', cfg.target);
        return;
      }

      // Prefer jQuery trigger so existing handlers run reliably.
      if (window.jQuery) {
        const $t = window.jQuery(cfg.target);
        if ($t && $t.length) {
          $t.trigger('click');
          return;
        }
      }

      // Fallback to native events.
      try {
        freshTrigger.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      } catch (_) {
        // ignore
      }
      freshTrigger.click();
    };

    bar.hidden = false;
    bar.classList.add('is-visible');
    document.documentElement.classList.add('pl-smartphone-has-actionbar');
  }

  function closePanel() {
    const overlay = qs('[data-pl-smartphone-menu-overlay]');
    const panel = qs('[data-pl-smartphone-menu-panel]');
    if (!overlay || !panel) return;

    overlay.classList.remove('is-open');
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('pl-smartphone-menu-open');
    window.setTimeout(() => {
      overlay.hidden = true;
      panel.hidden = true;
    }, 180);
  }

  function openMainPanel() {
    if (!isMobile()) return;

    const overlay = qs('[data-pl-smartphone-main-overlay]');
    const panel = qs('[data-pl-smartphone-main-panel]');
    if (!overlay || !panel) return;

    overlay.hidden = false;
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('pl-smartphone-menu-open');
    window.setTimeout(() => {
      overlay.classList.add('is-open');
      panel.classList.add('is-open');
    }, 0);
  }

  function closeMainPanel() {
    const overlay = qs('[data-pl-smartphone-main-overlay]');
    const panel = qs('[data-pl-smartphone-main-panel]');
    if (!overlay || !panel) return;

    overlay.classList.remove('is-open');
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('pl-smartphone-menu-open');
    window.setTimeout(() => {
      overlay.hidden = true;
      panel.hidden = true;
    }, 180);
  }

  function init() {
    const trigger = qs('#pl-hamburger-sub');
    const overlay = qs('[data-pl-smartphone-menu-overlay]');
    const closeBtn = qs('[data-pl-smartphone-menu-close]');
    const backBtn = qs('[data-pl-subbar-back]');

    if (backBtn) {
      backBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (window.history.length > 1) {
          window.history.back();
        } else {
          // Fallback if opened in a new tab
          const section = getSectionFromUrl() || 'create-course';
          window.location.href = '?section=' + section;
        }
      });
    }

    if (trigger) trigger.addEventListener('click', openPanel);
    if (overlay) overlay.addEventListener('click', closePanel);
    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    const mainTrigger = qs('#pl-hamburger-main');
    const mainOverlay = qs('[data-pl-smartphone-main-overlay]');
    const mainCloseBtn = qs('[data-pl-smartphone-main-close]');

    if (mainTrigger) mainTrigger.addEventListener('click', openMainPanel);
    if (mainOverlay) mainOverlay.addEventListener('click', closeMainPanel);
    if (mainCloseBtn) mainCloseBtn.addEventListener('click', closeMainPanel);

    // Create action bar visibility
    syncActionBar();
    window.addEventListener('resize', syncActionBar, { passive: true });

    // Observe common section/form roots so the bar reacts to view switches.
    const watch = [
      '#pcg-course-form-section',
      '#pcg-my-courses-section',
      '#pcg-escritos-form-section',
      '#pcg-my-escritos-section',
      '#pcg-specialization-form-section',
      '#pcg-my-specializations-section',
      '#pcg-programa-form-section',
      '#pcg-my-programas-section',
    ];

    if (window.MutationObserver) {
      watch.forEach((sel) => {
        const el = qs(sel);
        if (!el) return;
        const obs = new MutationObserver(function () {
          // let other UI settle
          window.setTimeout(syncActionBar, 0);
        });
        obs.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closePanel();
        closeMainPanel();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
