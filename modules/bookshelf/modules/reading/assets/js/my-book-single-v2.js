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
});
