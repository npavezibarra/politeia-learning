document.addEventListener("DOMContentLoaded", function () {
	var tabs = document.querySelectorAll(".tabs .tab[data-tab]");
	var panels = document.querySelectorAll(".prs-tab-content[data-tab]");
	if (!tabs.length || !panels.length) return;

	function activateTab(target) {
		tabs.forEach(function (tab) {
			var isActive = tab.getAttribute("data-tab") === target;
			tab.classList.toggle("active", isActive);
			tab.setAttribute("aria-selected", isActive ? "true" : "false");
		});
		panels.forEach(function (panel) {
			panel.classList.toggle("is-active", panel.getAttribute("data-tab") === target);
		});
	}

	tabs.forEach(function (tab) {
		tab.addEventListener("click", function () {
			activateTab(tab.getAttribute("data-tab"));
		});
	});

	var params = new URLSearchParams(window.location.search || '');
	if (params.get('prs_start_session') === '1') {
		document.dispatchEvent(new CustomEvent('prs-session-modal:open', { detail: { focusClose: true } }));
	}

	var identity = document.getElementById("book-identity");
	var header = document.querySelector("#prs-book-header .header");
	var slot = document.getElementById("prs-book-identity-slot");
	if (identity && header && slot) {
		var mediaQuery = window.matchMedia("(max-width: 980px)");
		var syncIdentityPlacement = function () {
			if (mediaQuery.matches) {
				if (!slot.contains(identity)) {
					slot.appendChild(identity);
				}
			} else if (!header.contains(identity)) {
				header.insertBefore(identity, header.firstElementChild);
			}
		};

		syncIdentityPlacement();
		if (mediaQuery.addEventListener) {
			mediaQuery.addEventListener("change", syncIdentityPlacement);
		} else if (mediaQuery.addListener) {
			mediaQuery.addListener(syncIdentityPlacement);
		}
	}

	var content = document.querySelector(".content");
	var sidebar = document.querySelector(".sidebar");
	var bookContent = document.getElementById("prs-book-content");
	var bookHeader = document.getElementById("prs-book-header");
	var bookDetails = document.getElementById("book-details-section");
	if (content && sidebar && bookContent && bookHeader && bookDetails) {
		var contentMarker = document.getElementById("prs-book-content-placeholder");
		if (!contentMarker) {
			contentMarker = document.createElement("div");
			contentMarker.id = "prs-book-content-placeholder";
			contentMarker.className = "prs-layout-placeholder";
			bookContent.parentNode.insertBefore(contentMarker, bookContent);
		}

		var detailsMarker = document.getElementById("prs-book-details-placeholder");
		if (!detailsMarker) {
			detailsMarker = document.createElement("div");
			detailsMarker.id = "prs-book-details-placeholder";
			detailsMarker.className = "prs-layout-placeholder";
			bookDetails.parentNode.insertBefore(detailsMarker, bookDetails);
		}

		var layoutQuery = window.matchMedia("(max-width: 980px)");
		var syncMobileLayout = function () {
			if (layoutQuery.matches) {
				if (bookHeader.parentNode) {
					bookHeader.parentNode.insertBefore(bookDetails, bookHeader.nextSibling);
				}
			} else {
				if (sidebar.contains(detailsMarker)) {
					sidebar.insertBefore(bookDetails, detailsMarker);
				}
			}
		};

		syncMobileLayout();
		if (layoutQuery.addEventListener) {
			layoutQuery.addEventListener("change", syncMobileLayout);
		} else if (layoutQuery.addListener) {
			layoutQuery.addListener(syncMobileLayout);
		}
	}

	// Book stats: activity date selector (month range)
	(function initPrsActivitySelector() {
		var bars = document.querySelector(".prs-dash-bars[data-months]");
		if (!bars) return;

		var fromInput = document.getElementById("prs-activity-from");
		var toInput = document.getElementById("prs-activity-to");
		var rangeLabel = document.getElementById("prs-activity-range-label");

		var raw = bars.getAttribute("data-months") || "[]";
		var months = [];
		try { months = JSON.parse(raw) || []; } catch (e) { months = []; }
		if (!Array.isArray(months) || !months.length) return;

		function monthKeyToDate(key) {
			if (!key || typeof key !== "string") return null;
			var parts = key.split("-");
			if (parts.length !== 2) return null;
			var year = parseInt(parts[0], 10);
			var month = parseInt(parts[1], 10);
			if (!year || !month || month < 1 || month > 12) return null;
			return new Date(year, month - 1, 1);
		}

		function clampKey(key, minKey, maxKey) {
			if (!key) return minKey;
			return (key < minKey) ? minKey : (key > maxKey) ? maxKey : key;
		}

		var minKey = String(months[0].key || "");
		var maxKey = String(months[months.length - 1].key || "");

		function formatRangeLabel(fromKey, toKey) {
			var fromDate = monthKeyToDate(fromKey);
			var toDate = monthKeyToDate(toKey);
			if (!fromDate || !toDate) return "";
			var fromTxt = fromDate.toLocaleDateString(undefined, { month: "short", year: "numeric" });
			var toTxt = toDate.toLocaleDateString(undefined, { month: "short", year: "numeric" });
			return fromTxt + " – " + toTxt;
		}

		function render(fromKey, toKey) {
			var from = clampKey(fromKey, minKey, maxKey);
			var to = clampKey(toKey, minKey, maxKey);
			if (from > to) {
				var tmp = from;
				from = to;
				to = tmp;
			}

			var filtered = months.filter(function (m) {
				var key = String(m.key || "");
				return key >= from && key <= to;
			});
			if (!filtered.length) filtered = months.slice();

			var maxMinutes = 0;
			filtered.forEach(function (m) {
				var minutes = parseInt(m.minutes || 0, 10) || 0;
				if (minutes > maxMinutes) maxMinutes = minutes;
			});

			var html = "";
			filtered.forEach(function (m) {
				var minutes = parseInt(m.minutes || 0, 10) || 0;
				var height = 14;
				if (maxMinutes > 0) {
					height = Math.round(12 + ((minutes / maxMinutes) * 88));
				}
				var tooltip = (minutes > 0) ? (minutes + " min") : "0 min";
				var isPeak = (minutes === maxMinutes && maxMinutes > 0);
				html += ""
					+ "<div class=\"prs-dash-bars__col\">"
					+ "  <div class=\"prs-dash-bars__bar-wrap\">"
					+ "    <div class=\"prs-dash-bars__bar" + (isPeak ? " is-peak" : "") + "\" style=\"height:" + height + "%\" data-tooltip=\"" + tooltip.replace(/\"/g, "&quot;") + "\"></div>"
					+ "  </div>"
					+ "  <div class=\"prs-dash-bars__tick" + (isPeak ? " is-peak" : "") + "\">" + String(m.label || "") + "</div>"
					+ "</div>";
			});

			bars.innerHTML = html;

			if (rangeLabel) {
				rangeLabel.textContent = formatRangeLabel(from, to);
			}
		}

		function syncFromInputs() {
			var fromKey = fromInput ? String(fromInput.value || "") : minKey;
			var toKey = toInput ? String(toInput.value || "") : maxKey;
			render(fromKey, toKey);
		}

		if (fromInput) {
			fromInput.addEventListener("change", syncFromInputs);
		}
		if (toInput) {
			toInput.addEventListener("change", syncFromInputs);
		}

		syncFromInputs();
	})();
});
