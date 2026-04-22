/**
 * Course Creator - Students Dashboard UI Logic
 * Extracted from students.php for modularity in Learni.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        initStudentsTabs();
        initStudentProfileTabs();
        initStudentDetailNavigation();
    });

    /**
     * Main Students Dashboard Tabs
     */
    function initStudentsTabs() {
        const tabs = document.getElementById('pcg-students-tabs');
        if (!tabs) return;

        const container = tabs.closest('.pcg-section-container') || document;

        const setActive = (tab) => {
            $(tabs).find('.pcg-segment').removeClass('active');
            const segment = tabs.querySelector('.pcg-segment[data-students-tab="' + tab + '"]');
            if (segment) segment.classList.add('active');

            $(container).find('[data-students-panel]').hide();
            const panel = container.querySelector('[data-students-panel="' + tab + '"]');
            if (panel) $(panel).show();

            window.dispatchEvent(new CustomEvent('pcg:sales-tab-changed', { detail: { tab } }));
        };

        tabs.addEventListener('click', (e) => {
            const seg = e.target && e.target.closest ? e.target.closest('.pcg-segment') : null;
            if (!seg) return;
            const tab = seg.getAttribute('data-students-tab');
            if (!tab) return;
            setActive(tab);
        }, true);

        setActive('general');
    }

    /**
     * Student Profile Detail Sub-Tabs (Courses, Books, Patronage)
     */
    function initStudentProfileTabs() {
        const root = document.querySelector('[data-students-panel="profile"] .pcg-students-profile');
        if (!root) return;

        const tabs = Array.from(root.querySelectorAll('[data-profile-tab]'));
        const panels = Array.from(root.querySelectorAll('[data-profile-panel]'));

        const setActive = (tab) => {
            tabs.forEach((btn) => {
                const active = btn.getAttribute('data-profile-tab') === tab;
                btn.classList.toggle('is-active', active);
            });

            panels.forEach((panel) => {
                const active = panel.getAttribute('data-profile-panel') === tab;
                if (active) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                }
            });
        };

        root.addEventListener('click', (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('[data-profile-tab]') : null;
            if (!btn) return;
            const tab = btn.getAttribute('data-profile-tab');
            if (!tab) return;
            setActive(tab);
        });

        setActive('courses');
    }

    /**
     * Student List to Detail Navigation & AJAX
     */
    function initStudentDetailNavigation() {
        const panel = document.querySelector('[data-students-panel="profile"]');
        if (!panel) return;

        const index = panel.querySelector('.pcg-students-profile-index');
        const detail = panel.querySelector('.pcg-students-profile-detail');
        const backBtn = panel.querySelector('[data-pcg-student-profile-back]');
        
        if (!index || !detail) return;

        // AJAX and Rendering Helpers
        const applyResponsiveLabels = (tbody) => {
            if (!tbody) return;
            const table = tbody.closest('table');
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            
            Array.from(tbody.querySelectorAll('tr')).forEach(tr => {
                Array.from(tr.children).forEach((cell, idx) => {
                    cell.setAttribute('data-label', headers[idx] || '');
                });
            });
        };

        const renderCourseRow = (c) => `
            <tr>
                <td><div class="pcg-students-profile__cell-course"><span class="pcg-students-profile__course-title">${c.title}</span></div></td>
                <td><div class="pcg-students-profile__progress">
                    <div class="pcg-students-profile__progress-bar"><div class="pcg-students-profile__progress-fill" style="width: ${c.progress}%"></div></div>
                    <div class="pcg-students-profile__progress-label">${c.progress >= 100 ? 'Completado' : c.progress + '% completo'}</div>
                </div></td>
                <td class="pcg-students-profile__pct"><div class="pcg-students-profile__score-wrap"><div class="pcg-students-profile__score-val">${c.first_quiz}</div><div class="pcg-students-profile__score-date">${c.first_quiz_date || ''}</div></div></td>
                <td class="pcg-students-profile__pct pcg-students-profile__pct--strong"><div class="pcg-students-profile__score-wrap"><div class="pcg-students-profile__score-val">${c.final_quiz}</div><div class="pcg-students-profile__score-date">${c.final_quiz_date || ''}</div></div></td>
                <td class="pcg-students-profile__pct">${c.days_delta}</td>
            </tr>`;

        const showDetail = (id, info) => {
            const nameEl = panel.querySelector('[data-pcg-student-profile-name]');
            const emailEl = panel.querySelector('[data-pcg-student-profile-email]');
            const avatarEl = panel.querySelector('[data-pcg-student-profile-avatar]');
            const coursesBody = panel.querySelector('[data-profile-panel="courses"] tbody');
            const booksBody = panel.querySelector('[data-profile-panel="books"] tbody');

            nameEl.textContent = info.name || '—';
            emailEl.textContent = info.email || '';
            
            // Metrics Update
            panel.querySelector('[data-pcg-student-val-courses]').textContent = info.metrics.courses || '$0.00';
            panel.querySelector('[data-pcg-student-val-books]').textContent = info.metrics.books || '$0.00';
            panel.querySelector('[data-pcg-student-val-patronage]').textContent = info.metrics.patronage || '$0.00';
            panel.querySelector('[data-pcg-student-val-total]').textContent = info.metrics.total || '$0.00';

            if (avatarEl) {
                avatarEl.innerHTML = info.avatar 
                    ? `<img src="${info.avatar}" alt="${info.name}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`
                    : '<svg width="44" height="44" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" /></svg>';
            }

            coursesBody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px; color:#666;">Cargando cursos...</td></tr>';
            index.hidden = true;
            detail.hidden = false;

            if (typeof pcgStudentsData !== 'undefined') {
                $.ajax({
                    url: pcgStudentsData.ajaxUrl,
                    data: {
                        action: pcgStudentsData.studentDetailAction,
                        nonce: pcgStudentsData.studentDetailNonce,
                        student_user_id: id
                    },
                    success: function(res) {
                        if (res.success && res.data) {
                            coursesBody.innerHTML = res.data.courses.length ? res.data.courses.map(renderCourseRow).join('') : '<tr><td colspan="5">No hay cursos registrados.</td></tr>';
                            applyResponsiveLabels(coursesBody);
                        }
                    }
                });
            }
        };

        panel.addEventListener('click', (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('[data-pcg-student-open]') : null;
            if (btn) {
                e.preventDefault();
                const id = btn.getAttribute('data-pcg-student-open');
                const info = {
                    name: btn.getAttribute('data-student-name'),
                    email: btn.getAttribute('data-student-email'),
                    avatar: btn.getAttribute('data-student-avatar'),
                    metrics: {
                        courses: btn.getAttribute('data-student-val-courses'),
                        books: btn.getAttribute('data-student-val-books'),
                        patronage: btn.getAttribute('data-student-val-patronage'),
                        total: btn.getAttribute('data-student-val-total')
                    }
                };
                showDetail(id, info);
            }

            if (e.target.closest('[data-pcg-student-profile-back]')) {
                index.hidden = false;
                detail.hidden = true;
            }
        });
    }

})(jQuery);
