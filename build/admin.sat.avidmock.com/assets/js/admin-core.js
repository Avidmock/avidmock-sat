/**
 * AdminCore — Utility library for admin.sat.avidmock.com
 * No external dependencies. Pure vanilla JS.
 */
var AdminCore = (function () {
    'use strict';

    // ---- TOAST NOTIFICATION SYSTEM ----

    var toastContainer = null;

    function getToastContainer() {
        if (toastContainer) return toastContainer;
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        toastContainer.setAttribute('role', 'status');
        document.body.appendChild(toastContainer);
        return toastContainer;
    }

    function showToast(message, type) {
        type = type || 'info';
        var container = getToastContainer();
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        toast.setAttribute('role', 'alert');

        var icons = {
            success: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 9l2.5 2.5L12.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            error: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 6.5l5 5M11.5 6.5l-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            warning: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l7.5 13H1.5L9 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 7v3M9 13v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            info: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M9 8v5M9 5.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
        };

        toast.innerHTML =
            '<span class="toast__icon">' + (icons[type] || icons.info) + '</span>' +
            '<span class="toast__msg">' + escapeHtml(message) + '</span>' +
            '<button class="toast__close" aria-label="Dismiss">&times;</button>';

        toast.querySelector('.toast__close').addEventListener('click', function () {
            dismissToast(toast);
        });

        container.appendChild(toast);

        // Force reflow then add visible class
        toast.offsetHeight; // eslint-disable-line no-unused-expressions
        toast.classList.add('toast--visible');

        var timer = setTimeout(function () {
            dismissToast(toast);
        }, 5000);

        toast._timer = timer;
    }

    function dismissToast(toast) {
        if (toast._dismissed) return;
        toast._dismissed = true;
        clearTimeout(toast._timer);
        toast.classList.remove('toast--visible');
        toast.addEventListener('transitionend', function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        });
    }

    // ---- CONFIRM DIALOG ----

    function confirmAction(message, callback) {
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop is-open';

        var modal = document.createElement('div');
        modal.className = 'modal';
        modal.setAttribute('role', 'alertdialog');
        modal.setAttribute('aria-modal', 'true');

        modal.innerHTML =
            '<h3 class="modal__title">Confirm Action</h3>' +
            '<div class="modal__body">' + escapeHtml(message) + '</div>' +
            '<div class="modal__actions">' +
                '<button class="btn btn--ghost" data-action="cancel">Cancel</button>' +
                '<button class="btn btn--danger" data-action="confirm">Confirm</button>' +
            '</div>';

        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        var confirmBtn = modal.querySelector('[data-action="confirm"]');
        var cancelBtn = modal.querySelector('[data-action="cancel"]');

        confirmBtn.focus();

        function close(confirmed) {
            backdrop.classList.remove('is-open');
            backdrop.addEventListener('transitionend', function () {
                if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
            });
            if (confirmed && typeof callback === 'function') {
                callback();
            }
        }

        confirmBtn.addEventListener('click', function () { close(true); });
        cancelBtn.addEventListener('click', function () { close(false); });
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) close(false);
        });
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') {
                close(false);
                document.removeEventListener('keydown', handler);
            }
        });
    }

    // ---- AJAX FORM SUBMISSION ----

    function submitForm(form, options) {
        options = options || {};
        var url = form.getAttribute('action') || window.location.href;
        var method = (form.getAttribute('method') || 'POST').toUpperCase();
        var data = new FormData(form);

        // Inject CSRF token if present in meta
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta && !data.has('csrf_token')) {
            data.append('csrf_token', csrfMeta.getAttribute('content'));
        }

        var fetchOpts = {
            method: method,
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        };

        // Show loading state
        var submitBtn = form.querySelector('[type="submit"]');
        var originalText = '';
        if (submitBtn) {
            originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner" style="width:16px;height:16px;display:inline-block"></span>';
        }

        return fetch(url, fetchOpts)
            .then(function (res) {
                if (!res.ok) throw new Error('Server responded with ' + res.status);
                var ct = res.headers.get('content-type') || '';
                return ct.indexOf('json') !== -1 ? res.json() : res.text();
            })
            .then(function (result) {
                if (typeof options.onSuccess === 'function') options.onSuccess(result);
                else showToast('Saved successfully.', 'success');
                return result;
            })
            .catch(function (err) {
                if (typeof options.onError === 'function') options.onError(err);
                else showToast(err.message || 'An error occurred.', 'error');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
    }

    // ---- TABLE SORT ----

    function initSortableTable(table) {
        if (!table) return;
        var headers = table.querySelectorAll('th[data-sort]');
        for (var i = 0; i < headers.length; i++) {
            (function (th, idx) {
                th.addEventListener('click', function () {
                    sortTable(table, th, idx);
                });
            })(headers[i], i);
        }
    }

    function sortTable(table, th, colIndex) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        var isAsc = th.classList.contains('sort-asc');

        // Reset other headers
        var allTh = table.querySelectorAll('th');
        for (var i = 0; i < allTh.length; i++) {
            allTh[i].classList.remove('sort-asc', 'sort-desc');
        }

        th.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
        var dir = isAsc ? -1 : 1;

        // Remap colIndex to actual column (account for columns without data-sort)
        var allHeaders = Array.prototype.slice.call(table.querySelectorAll('thead th'));
        var realIndex = allHeaders.indexOf(th);

        rows.sort(function (a, b) {
            var aCell = a.children[realIndex];
            var bCell = b.children[realIndex];
            if (!aCell || !bCell) return 0;
            var aText = (aCell.getAttribute('data-sort-value') || aCell.textContent).trim().toLowerCase();
            var bText = (bCell.getAttribute('data-sort-value') || bCell.textContent).trim().toLowerCase();

            // Numeric comparison
            var aNum = parseFloat(aText);
            var bNum = parseFloat(bText);
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return (aNum - bNum) * dir;
            }
            return aText.localeCompare(bText) * dir;
        });

        for (var j = 0; j < rows.length; j++) {
            tbody.appendChild(rows[j]);
        }
    }

    // ---- SIDEBAR MOBILE TOGGLE ----

    function initSidebarToggle() {
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var sidebar = document.querySelector('.admin-sidebar');
        if (!toggle || !sidebar) return;

        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (sidebar.classList.contains('is-open') &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)) {
                sidebar.classList.remove('is-open');
            }
        });
    }

    // ---- UNSAVED CHANGES DETECTION ----

    function initUnsavedChanges() {
        var forms = document.querySelectorAll('[data-watch-changes]');
        if (!forms.length) return;

        var bar = document.createElement('div');
        bar.className = 'unsaved-bar';
        bar.innerHTML = 'You have unsaved changes.';
        document.body.appendChild(bar);

        var hasChanges = false;
        var initialData = {};

        for (var i = 0; i < forms.length; i++) {
            var form = forms[i];
            initialData[i] = new FormData(form);

            form.addEventListener('input', function () {
                if (!hasChanges) {
                    hasChanges = true;
                    bar.classList.add('is-visible');
                }
            });

            form.addEventListener('submit', function () {
                hasChanges = false;
                bar.classList.remove('is-visible');
            });
        }

        window.addEventListener('beforeunload', function (e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    // ---- CSRF TOKEN INJECTION FOR FETCH ----

    function csrfFetch(url, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.credentials = options.credentials || 'same-origin';

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            options.headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');
        }
        options.headers['X-Requested-With'] = 'XMLHttpRequest';

        return fetch(url, options);
    }

    // ---- CHART HELPERS (Canvas API) ----

    function drawBarChart(canvas, data) {
        if (!canvas || !data || !data.labels || !data.values) return;
        var ctx = canvas.getContext('2d');
        var w = canvas.width = canvas.parentElement.clientWidth;
        var h = canvas.height = Math.min(320, canvas.parentElement.clientHeight || 320);
        var padding = { top: 20, right: 20, bottom: 40, left: 50 };
        var chartW = w - padding.left - padding.right;
        var chartH = h - padding.top - padding.bottom;

        var maxVal = Math.max.apply(null, data.values) || 1;
        maxVal = Math.ceil(maxVal * 1.1);
        var barCount = data.values.length;
        var barWidth = Math.max(8, (chartW / barCount) * 0.6);
        var gap = (chartW - barWidth * barCount) / (barCount + 1);
        var color = data.color || '#1fe290';

        ctx.clearRect(0, 0, w, h);

        // Y-axis grid lines
        ctx.strokeStyle = 'rgba(232,243,241,0.06)';
        ctx.lineWidth = 1;
        var steps = 5;
        for (var s = 0; s <= steps; s++) {
            var y = padding.top + (chartH / steps) * s;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(w - padding.right, y);
            ctx.stroke();

            // Label
            ctx.fillStyle = 'rgba(232,243,241,0.4)';
            ctx.font = '11px "DM Mono", monospace';
            ctx.textAlign = 'right';
            ctx.fillText(
                formatNumber(Math.round(maxVal - (maxVal / steps) * s)),
                padding.left - 8,
                y + 4
            );
        }

        // Bars
        for (var i = 0; i < barCount; i++) {
            var barH = (data.values[i] / maxVal) * chartH;
            var x = padding.left + gap + i * (barWidth + gap);
            var barY = padding.top + chartH - barH;

            ctx.fillStyle = color;
            ctx.globalAlpha = 0.85;
            roundRect(ctx, x, barY, barWidth, barH, 4);
            ctx.fill();
            ctx.globalAlpha = 1;

            // X label
            ctx.fillStyle = 'rgba(232,243,241,0.4)';
            ctx.font = '10px "DM Sans", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(
                data.labels[i] || '',
                x + barWidth / 2,
                h - padding.bottom + 16
            );
        }
    }

    function roundRect(ctx, x, y, w, h, r) {
        if (h <= 0) return;
        r = Math.min(r, h / 2, w / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h);
        ctx.lineTo(x, y + h);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    // ---- DEBOUNCE / THROTTLE ----

    function debounce(fn, delay) {
        var timer;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function throttle(fn, limit) {
        var inThrottle = false;
        return function () {
            var context = this;
            var args = arguments;
            if (!inThrottle) {
                fn.apply(context, args);
                inThrottle = true;
                setTimeout(function () {
                    inThrottle = false;
                }, limit);
            }
        };
    }

    // ---- FORMAT HELPERS ----

    function formatDate(dateStr, opts) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        opts = opts || { month: 'short', day: 'numeric', year: 'numeric' };
        return d.toLocaleDateString('en-US', opts);
    }

    function formatNumber(num) {
        if (num == null) return '0';
        return Number(num).toLocaleString('en-US');
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        var now = Date.now();
        var then = new Date(dateStr).getTime();
        if (isNaN(then)) return dateStr;
        var diff = Math.floor((now - then) / 1000);

        if (diff < 60)    return 'just now';
        if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return formatDate(dateStr);
    }

    // ---- UTILITIES ----

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ---- INITIALIZATION ----

    function init() {
        initSidebarToggle();
        initUnsavedChanges();

        // Auto-show PHP session toast if present
        var toastEl = document.querySelector('[data-toast]');
        if (toastEl) {
            showToast(
                toastEl.getAttribute('data-toast-message') || '',
                toastEl.getAttribute('data-toast-type') || 'info'
            );
        }
    }

    // Run init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ---- PUBLIC API ----

    return {
        showToast: showToast,
        confirmAction: confirmAction,
        submitForm: submitForm,
        initSortableTable: initSortableTable,
        csrfFetch: csrfFetch,
        drawBarChart: drawBarChart,
        debounce: debounce,
        throttle: throttle,
        formatDate: formatDate,
        formatNumber: formatNumber,
        timeAgo: timeAgo,
        escapeHtml: escapeHtml
    };
})();
