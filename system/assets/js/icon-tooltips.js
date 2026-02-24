(function () {
    "use strict";

    var ICON_LABELS = {
        "fa-eye": "View",
        "fa-edit": "Edit",
        "fa-pen": "Edit",
        "fa-pencil": "Edit",
        "fa-trash": "Delete",
        "fa-trash-alt": "Delete",
        "fa-boxes": "Update Stock",
        "fa-box": "Stock",
        "fa-plus": "Add",
        "fa-minus": "Remove",
        "fa-download": "Download",
        "fa-upload": "Upload",
        "fa-print": "Print",
        "fa-sync": "Refresh",
        "fa-sync-alt": "Refresh",
        "fa-filter": "Filter",
        "fa-search": "Search",
        "fa-cog": "Settings",
        "fa-cogs": "Settings",
        "fa-bolt": "Quick Actions",
        "fa-star": "Featured",
        "fa-power-off": "Toggle Status",
        "fa-barcode": "Print Barcode",
        "fa-sign-out-alt": "Logout",
        "fa-sign-in-alt": "Login",
        "fa-user-plus": "Sign Up",
        "bi-eye": "View",
        "bi-pencil": "Edit",
        "bi-pencil-square": "Edit",
        "bi-trash": "Delete",
        "bi-plus": "Add",
        "bi-download": "Download",
        "bi-upload": "Upload",
        "bi-printer": "Print",
        "bi-arrow-repeat": "Refresh",
        "bi-funnel": "Filter",
        "bi-search": "Search",
        "bi-gear": "Settings",
        "bi-box-arrow-right": "Logout",
        "bi-box-arrow-in-right": "Login"
    };

    function getLabelFromIcon(el) {
        var icon = el.querySelector("i, svg");
        if (!icon) return "";

        var classList = Array.prototype.slice.call(icon.classList || []);
        for (var i = 0; i < classList.length; i++) {
            if (ICON_LABELS[classList[i]]) {
                return ICON_LABELS[classList[i]];
            }
        }
        return "";
    }

    function hasVisibleText(el) {
        var clone = el.cloneNode(true);
        var icons = clone.querySelectorAll("i, svg");
        for (var i = 0; i < icons.length; i++) {
            icons[i].remove();
        }
        return clone.textContent.trim().length > 0;
    }

    function hasNonTooltipBootstrapToggle(el) {
        var toggle = (el.getAttribute("data-bs-toggle") || "").trim().toLowerCase();
        if (!toggle) return false;
        return toggle !== "tooltip" && toggle !== "popover";
    }

    function applyTooltip(el) {
        if (!el || el.dataset.iconTooltipBound === "1") return;

        // Avoid conflicts with other Bootstrap components (dropdown, modal, etc.)
        if (hasNonTooltipBootstrapToggle(el) || el.classList.contains("dropdown-toggle")) {
            return;
        }

        var existing = el.getAttribute("title") || el.getAttribute("aria-label") || el.getAttribute("data-bs-title");
        var label = existing || "";

        if (!label && hasVisibleText(el)) {
            label = el.textContent.trim().replace(/\s+/g, " ");
        }

        if (!label) {
            label = getLabelFromIcon(el);
        }

        if (!label) return;

        el.setAttribute("title", label);
        if (!el.getAttribute("aria-label")) {
            el.setAttribute("aria-label", label);
        }
        el.dataset.iconTooltipBound = "1";

        if (window.bootstrap && window.bootstrap.Tooltip) {
            window.bootstrap.Tooltip.getOrCreateInstance(el, {
                container: "body",
                trigger: "hover focus"
            });
        }
    }

    function sanitizeDropdownToggles(root) {
        var scope = root || document;
        var items = scope.querySelectorAll("[data-bs-toggle='dropdown'], .dropdown-toggle");
        for (var i = 0; i < items.length; i++) {
            var el = items[i];
            if (window.bootstrap && window.bootstrap.Tooltip) {
                var instance = window.bootstrap.Tooltip.getInstance(el);
                if (instance) {
                    instance.dispose();
                }
            }
            // Prevent this helper from trying to bind tooltip on dropdown toggles.
            el.dataset.iconTooltipBound = "1";
            // Remove tooltip-specific attrs so legacy [title] initializers do not rebind.
            el.removeAttribute("title");
            el.removeAttribute("data-bs-title");
            el.removeAttribute("data-bs-original-title");
            el.removeAttribute("aria-describedby");
        }
    }

    function scan(root) {
        var scope = root || document;
        var items = scope.querySelectorAll("button, a, .btn, [role='button'], .dropdown-item");
        for (var i = 0; i < items.length; i++) {
            var el = items[i];
            if (!el.querySelector("i, svg")) continue;
            if (!hasVisibleText(el) || !el.getAttribute("title")) {
                applyTooltip(el);
            }
        }
    }

    function init() {
        sanitizeDropdownToggles(document);
        scan(document);

        document.addEventListener("mouseover", function (e) {
            var target = e.target.closest("button, a, .btn, [role='button'], .dropdown-item");
            if (target && target.querySelector("i, svg")) {
                applyTooltip(target);
            }
        });

        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var mutation = mutations[i];
                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var node = mutation.addedNodes[j];
                        if (node.nodeType === 1) {
                            sanitizeDropdownToggles(node);
                            scan(node);
                        }
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
