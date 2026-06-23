(function () {
    const script = document.currentScript || document.querySelector('script[data-printease-live]');
    const baseUrl = script && script.dataset.baseUrl ? script.dataset.baseUrl : '/printease/';
    const endpoints = {
        liveSearch: baseUrl + 'backend/actions/live_search.php',
        notifications: baseUrl + 'backend/actions/notification_feed.php',
        markRead: baseUrl + 'backend/actions/mark_notification_read.php',
        ownerOrders: baseUrl + 'backend/actions/owner_order_feed.php'
    };
    const timers = new WeakMap();
    const cssEscape = window.CSS && typeof window.CSS.escape === 'function'
        ? window.CSS.escape.bind(window.CSS)
        : function (value) { return String(value).replace(/["\\]/g, '\\$&'); };
    const imageProofTypes = new Set(['jpg', 'jpeg', 'png', 'webp', 'jfif']);
    let proofZoom = 1;

    function toInt(value) {
        return Math.max(0, Number.parseInt(value, 10) || 0);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
        });
    }

    function ensureProofViewerStyles() {
        if (document.getElementById('printEaseProofViewerStyles')) return;
        const style = document.createElement('style');
        style.id = 'printEaseProofViewerStyles';
        style.textContent =
            '.proof-viewer-modal{position:fixed;inset:0;z-index:12050;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(3,7,18,.72)}' +
            'body.proof-viewer-open{overflow:hidden}' +
            '.proof-viewer-modal.is-open{display:flex}' +
            '.proof-viewer-panel{width:min(980px,100%);height:min(86vh,820px);background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.35);display:grid;grid-template-rows:auto 1fr;overflow:hidden}' +
            '.proof-viewer-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid #e5e7eb}' +
            '.proof-viewer-header strong{font-size:15px;color:#111827}' +
            '.proof-viewer-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}' +
            '.proof-viewer-actions button{border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:8px;min-width:38px;min-height:34px;padding:0 10px;font-weight:800;cursor:pointer}' +
            '.proof-viewer-actions button:hover,.proof-viewer-actions button:focus-visible{background:#eef6ff;border-color:#93c5fd;outline:none}' +
            '.proof-viewer-close{color:#dc2626!important}' +
            '.proof-viewer-stage{min-height:0;overflow:auto;background:#f8fafc;display:flex;align-items:flex-start;justify-content:center;padding:18px}' +
            '.proof-viewer-image{display:block;max-width:100%;max-height:100%;width:auto;height:auto;transform-origin:top center;transition:transform .12s ease;border-radius:10px;box-shadow:0 10px 30px rgba(15,23,42,.16)}' +
            '.proof-viewer-frame{width:100%;height:100%;min-height:70vh;border:0;background:#fff}' +
            '.proof-viewer-modal.is-frame .proof-viewer-stage{padding:0;display:block}' +
            '.proof-viewer-modal.is-frame [data-proof-zoom]{display:none}' +
            '.proof-viewer-modal.is-frame .proof-viewer-reset{display:none}' +
            '@media (max-width:640px){.proof-viewer-modal{padding:10px}.proof-viewer-panel{height:92vh;border-radius:10px}.proof-viewer-header{align-items:flex-start;flex-direction:column}.proof-viewer-actions{width:100%}.proof-viewer-actions button{flex:1}.proof-viewer-stage{padding:10px}}';
        document.head.appendChild(style);
    }

    function ensureProofViewer() {
        ensureProofViewerStyles();
        let modal = document.getElementById('proofViewerModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'proofViewerModal';
        modal.className = 'proof-viewer-modal';
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<section class="proof-viewer-panel" role="dialog" aria-modal="true" aria-labelledby="proofViewerTitle">' +
                '<header class="proof-viewer-header">' +
                    '<strong id="proofViewerTitle">Payment Proof</strong>' +
                    '<div class="proof-viewer-actions">' +
                        '<button type="button" data-proof-zoom="out" aria-label="Zoom out">-</button>' +
                        '<button type="button" class="proof-viewer-reset" data-proof-reset>Reset</button>' +
                        '<button type="button" data-proof-zoom="in" aria-label="Zoom in">+</button>' +
                        '<button type="button" class="proof-viewer-close" data-proof-close>Close</button>' +
                    '</div>' +
                '</header>' +
                '<div class="proof-viewer-stage" data-proof-stage>' +
                    '<img class="proof-viewer-image" data-proof-image alt="Payment proof">' +
                    '<iframe class="proof-viewer-frame" data-proof-frame title="Payment proof"></iframe>' +
                '</div>' +
            '</section>';
        document.body.appendChild(modal);
        return modal;
    }

    function proofTypeFromUrl(url, explicitType) {
        const explicit = String(explicitType || '').replace(/^\./, '').toLowerCase();
        if (explicit) return explicit;
        try {
            const parsed = new URL(url, window.location.href);
            const filename = parsed.pathname.split('/').pop() || '';
            return filename.includes('.') ? filename.split('.').pop().toLowerCase() : '';
        } catch (error) {
            const clean = String(url || '').split('?')[0].split('#')[0];
            return clean.includes('.') ? clean.split('.').pop().toLowerCase() : '';
        }
    }

    function setProofZoom(nextZoom) {
        const modal = ensureProofViewer();
        const image = modal.querySelector('[data-proof-image]');
        proofZoom = Math.max(0.5, Math.min(3, nextZoom));
        if (image) {
            image.style.transform = 'scale(' + proofZoom + ')';
            image.style.marginBottom = proofZoom > 1 ? ((proofZoom - 1) * 100) + '%' : '';
        }
    }

    function closeProofViewer() {
        const modal = document.getElementById('proofViewerModal');
        if (!modal) return;
        const image = modal.querySelector('[data-proof-image]');
        const frame = modal.querySelector('[data-proof-frame]');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('proof-viewer-open');
        if (image) image.removeAttribute('src');
        if (frame) frame.removeAttribute('src');
    }

    function openProofViewer(url, type) {
        if (!url) return;
        const modal = ensureProofViewer();
        const image = modal.querySelector('[data-proof-image]');
        const frame = modal.querySelector('[data-proof-frame]');
        const stage = modal.querySelector('[data-proof-stage]');
        const resolvedType = proofTypeFromUrl(url, type);
        const isImage = imageProofTypes.has(resolvedType);
        const cacheJoiner = String(url).includes('?') ? '&' : '?';
        const displayUrl = String(url) + cacheJoiner + 't=' + Date.now();

        modal.classList.toggle('is-frame', !isImage);
        if (image) {
            image.hidden = !isImage;
            image.removeAttribute('src');
            image.style.transform = 'scale(1)';
            image.style.marginBottom = '';
        }
        if (frame) {
            frame.hidden = isImage;
            frame.removeAttribute('src');
        }
        if (stage) stage.scrollTo({ top: 0, left: 0 });

        proofZoom = 1;
        if (isImage && image) {
            image.src = displayUrl;
        } else if (frame) {
            frame.src = displayUrl;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('proof-viewer-open');
    }

    function buildUrl(base, params) {
        const url = new URL(base, window.location.href);
        Object.keys(params).forEach(function (key) {
            if (params[key] === null || params[key] === undefined || params[key] === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, params[key]);
            }
        });
        return url;
    }

    function replaceLiveRegions(regions) {
        Object.keys(regions || {}).forEach(function (name) {
            const current = document.querySelector('[data-live-region="' + cssEscape(name) + '"]');
            if (!current) return;
            const template = document.createElement('template');
            template.innerHTML = regions[name].trim();
            const next = template.content.firstElementChild;
            if (next) current.replaceWith(next);
        });
    }

    function findSearchInput(form) {
        return form.querySelector('input[type="search"], input[name="search"], input[name="q"], input[name="order_code"]');
    }

    function refreshLiveForm(form, options) {
        if (!form || form.dataset.liveLoading === 'true') return Promise.resolve(false);

        const target = form.dataset.liveTarget;
        if (!target) return Promise.resolve(false);

        const formData = new FormData(form);
        const params = { target: target };
        formData.forEach(function (value, key) {
            params[key] = value;
        });
        if (formData.has('page')) {
            params.page = '1';
        }

        const endpointUrl = buildUrl(endpoints.liveSearch, params);
        form.dataset.liveLoading = 'true';

        return fetch(endpointUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.success) return false;
                replaceLiveRegions(data.regions);

                if (!options || options.updateHistory !== false) {
                    const pageUrl = buildUrl(form.getAttribute('action') || window.location.href, Object.fromEntries(formData.entries()));
                    window.history.replaceState({}, '', pageUrl);
                }
                return true;
            })
            .catch(function () { return false; })
            .finally(function () {
                form.dataset.liveLoading = 'false';
            });
    }

    function setupLiveSearch() {
        document.querySelectorAll('[data-live-search-form]').forEach(function (form) {
            const input = findSearchInput(form);
            if (!input) return;

            input.addEventListener('input', function () {
                const min = toInt(form.dataset.liveMin || '3') || 3;
                const query = input.value.trim();
                window.clearTimeout(timers.get(form));

                if (query.length > 0 && query.length < min) {
                    return;
                }

                timers.set(form, window.setTimeout(function () {
                    refreshLiveForm(form);
                }, 200));
            });
        });
    }

    function setBadge(container, count, className) {
        if (!container) return;
        let badge = container.querySelector(className);
        if (!badge && count > 0) {
            badge = document.createElement('span');
            if (className.startsWith('.')) {
                badge.className = className.slice(1);
            }
            container.appendChild(badge);
        }
        if (badge) {
            badge.textContent = count;
            badge.hidden = count === 0;
        }
    }

    function renderOwnerNotifications(items) {
        const popover = document.getElementById('ownerNotificationPopover');
        if (!popover) return;
        let list = popover.querySelector('.notification-popover-list');
        const empty = popover.querySelector('.notification-popover-empty');
        if (!items.length) {
            if (list) list.remove();
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        if (!list) {
            list = document.createElement('div');
            list.className = 'notification-popover-list';
            popover.appendChild(list);
        }
        list.innerHTML = items.map(function (item) {
            const tag = item.target_url ? 'a' : 'article';
            const href = item.target_url ? ' href="' + escapeHtml(item.target_url) + '"' : '';
            return '<' + tag + ' class="notification-popover-item' + (item.target_url ? ' notification-popover-link' : '') + '"' + href +
                ' data-notification-id="' + item.id + '" data-is-read="' + item.is_read + '">' +
                '<span class="notification-popover-icon"></span><div><p>' + escapeHtml(item.message) + '</p>' +
                '<time>' + escapeHtml(item.created_at) + '</time></div></' + tag + '>';
        }).join('');
    }

    function renderAdminNotifications(items) {
        const popover = document.getElementById('adminNotificationPopover');
        if (!popover) return;
        let list = popover.querySelector('.admin-notification-list');
        const empty = popover.querySelector('.admin-empty.compact');
        if (!items.length) {
            if (list) list.remove();
            if (empty) empty.hidden = false;
            return;
        }
        if (empty) empty.hidden = true;
        if (!list) {
            list = document.createElement('div');
            list.className = 'admin-notification-list';
            popover.appendChild(list);
        }
        list.innerHTML = items.map(function (item) {
            const href = item.target_url || 'notifications.php';
            return '<a href="' + escapeHtml(href) + '" data-notification-id="' + item.id + '" data-is-read="' + item.is_read + '">' +
                '<strong>' + escapeHtml(item.title || 'Notification') + '</strong><span>' + escapeHtml(item.message) + '</span></a>';
        }).join('');
    }

    function applyNotificationCount(count) {
        const ownerToggle = document.getElementById('ownerNotificationToggle');
        setBadge(ownerToggle, count, '.notification-badge');
        const ownerText = document.getElementById('ownerNotificationUnreadText');
        if (ownerText) ownerText.textContent = count + ' unread';

        const adminToggle = document.getElementById('adminNotificationToggle');
        setBadge(adminToggle, count, 'span');
        const adminHeaderText = document.querySelector('#adminNotificationPopover header span');
        if (adminHeaderText) adminHeaderText.textContent = count + ' unread';

        const customerLink = document.querySelector('.customer-notification-link');
        setBadge(customerLink, count, 'span');

        const pageUnreadBadge = document.getElementById('unread-badge');
        if (pageUnreadBadge) pageUnreadBadge.textContent = count + ' unread';
    }

    function refreshNotifications() {
        return fetch(endpoints.notifications, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                applyNotificationCount(toInt(data.unread_count));
                if (data.role === 'shop_owner') renderOwnerNotifications(data.items || []);
                if (data.role === 'super_admin') renderAdminNotifications(data.items || []);
            })
            .catch(function () {});
    }

    function setupNotificationPolling() {
        if (!document.getElementById('ownerNotificationToggle') &&
            !document.getElementById('adminNotificationToggle') &&
            !document.querySelector('.customer-notification-link')) {
            return;
        }

        function schedule() {
            window.setTimeout(function () {
                refreshNotifications().finally(schedule);
            }, document.hidden ? 60000 : 12000);
        }
        schedule();
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshNotifications();
        });
    }

    function markNotificationRead(item, href) {
        const notificationId = item.dataset.notificationId;
        if (!notificationId) return;

        fetch(endpoints.markRead, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'notification_id=' + encodeURIComponent(notificationId)
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.success) {
                    applyNotificationCount(toInt(data.unread_count));
                    document.querySelectorAll('[data-notification-id="' + cssEscape(notificationId) + '"]').forEach(function (match) {
                        match.dataset.isRead = '1';
                        match.classList.remove('is-unread');
                    });
                }
                if (href) window.location.href = href;
            })
            .catch(function () {
                if (href) window.location.href = href;
            });
    }

    function setupNotificationDelegation() {
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented) return;
            const item = event.target.closest('[data-notification-id]');
            if (!item || item.dataset.isRead !== '0') return;
            const href = item.getAttribute('href');
            if (href) event.preventDefault();
            markNotificationRead(item, href);
        });
    }

    function setupOwnerOrderPolling() {
        const orderForm = document.querySelector('[data-live-target="owner_orders"]');
        if (!orderForm) return;
        let signature = '';

        function canRefreshOrders() {
            return !document.querySelector('.order-modal.is-open') && !document.querySelector('.orders-status-action.is-loading');
        }

        function poll() {
            fetch(endpoints.ownerOrders, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !data.success || !data.signature) return;
                    if (!signature) {
                        signature = data.signature;
                        return;
                    }
                    if (signature !== data.signature && canRefreshOrders()) {
                        signature = data.signature;
                        refreshLiveForm(orderForm, { updateHistory: false });
                    }
                })
                .catch(function () {})
                .finally(function () {
                    window.setTimeout(poll, document.hidden ? 60000 : 12000);
                });
        }
        poll();
    }

    function setupDelegatedModals() {
        document.addEventListener('click', function (event) {
            const userButton = event.target.closest('[data-user-view]');
            if (userButton) {
                const modal = document.getElementById('adminUserModal');
                if (!modal) return;
                modal.querySelector('#adminUserModalTitle').textContent = userButton.dataset.name || 'User Details';
                modal.querySelector('[data-user-modal-email]').textContent = userButton.dataset.email || 'N/A';
                modal.querySelector('[data-user-modal-role]').textContent = userButton.dataset.role || 'N/A';
                modal.querySelector('[data-user-modal-status]').textContent = userButton.dataset.status || 'Pending';
                modal.querySelector('[data-user-modal-orders]').textContent = userButton.dataset.orders || '0';
                modal.querySelector('[data-user-modal-last-activity]').textContent = userButton.dataset.lastActivity || 'N/A';
                modal.querySelector('[data-user-modal-created]').textContent = userButton.dataset.created || 'N/A';
                const documentLink = modal.querySelector('[data-user-modal-document]');
                if (documentLink) {
                    documentLink.href = userButton.dataset.documentUrl || '#';
                    documentLink.textContent = userButton.dataset.documentLabel || 'No document uploaded';
                    documentLink.classList.toggle('is-disabled', !userButton.dataset.documentUrl);
                }
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            const shopButton = event.target.closest('[data-shop-view]');
            if (shopButton) {
                const modal = document.getElementById('adminShopModal');
                if (!modal) return;
                modal.querySelector('#adminShopModalTitle').textContent = shopButton.dataset.shopName || 'Shop Details';
                modal.querySelector('[data-shop-modal-owner]').textContent = shopButton.dataset.ownerName || 'N/A';
                modal.querySelector('[data-shop-modal-email]').textContent = shopButton.dataset.ownerEmail || 'N/A';
                modal.querySelector('[data-shop-modal-address]').textContent = shopButton.dataset.address || 'N/A';
                modal.querySelector('[data-shop-modal-status]').textContent = shopButton.dataset.status || 'Pending';
                modal.querySelector('[data-shop-modal-shop-status]').textContent = shopButton.dataset.shopStatus || 'N/A';
                modal.querySelector('[data-shop-modal-created]').textContent = shopButton.dataset.created || 'N/A';
                const permit = modal.querySelector('[data-shop-modal-permit]');
                if (permit) {
                    permit.href = shopButton.dataset.permit || '#';
                    permit.classList.toggle('is-disabled', !shopButton.dataset.permit);
                    permit.textContent = shopButton.dataset.permit ? 'View Business Permit' : 'No permit uploaded';
                }
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            const activityItem = event.target.closest('[data-activity-view]');
            if (activityItem) {
                const modal = document.getElementById('adminActivityModal');
                if (!modal) return;
                modal.querySelector('#adminActivityModalTitle').textContent = activityItem.dataset.module || 'Activity Log Details';
                modal.querySelector('[data-activity-modal-status]').textContent = activityItem.dataset.status || 'Info';
                modal.querySelector('[data-activity-modal-actor]').textContent = activityItem.dataset.actor || 'N/A';
                modal.querySelector('[data-activity-modal-email]').textContent = activityItem.dataset.email || 'N/A';
                modal.querySelector('[data-activity-modal-role]').textContent = activityItem.dataset.role || 'N/A';
                modal.querySelector('[data-activity-modal-timestamp]').textContent = activityItem.dataset.timestamp || 'N/A';
                modal.querySelector('[data-activity-modal-module]').textContent = activityItem.dataset.module || 'N/A';
                modal.querySelector('[data-activity-modal-action]').textContent = activityItem.dataset.action || 'N/A';
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
            }

            const orderOpen = event.target.closest('[data-order-modal-target]');
            if (orderOpen) {
                const modal = document.getElementById(orderOpen.dataset.orderModalTarget);
                if (modal) {
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('order-modal-open');
                }
            }

            const orderClose = event.target.closest('[data-order-modal-close]');
            if (orderClose) {
                const modal = orderClose.closest('.order-modal');
                if (modal) {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    if (!document.querySelector('.order-modal.is-open')) {
                        document.body.classList.remove('order-modal-open');
                    }
                }
            }

            const rejectToggle = event.target.closest('.reject-toggle');
            if (rejectToggle) {
                const target = document.getElementById(rejectToggle.dataset.target);
                if (target) target.hidden = !target.hidden;
            }

            const proofButton = event.target.closest('.proof-toggle, .proof-view-btn');
            if (proofButton) {
                event.preventDefault();
                openProofViewer(proofButton.dataset.proofUrl || proofButton.dataset.proof || '', proofButton.dataset.proofType || '');
            }

            const zoomButton = event.target.closest('[data-proof-zoom]');
            if (zoomButton) {
                setProofZoom(proofZoom + (zoomButton.dataset.proofZoom === 'in' ? 0.25 : -0.25));
            }

            if (event.target.closest('[data-proof-reset]')) {
                setProofZoom(1);
                const stage = document.querySelector('#proofViewerModal [data-proof-stage]');
                if (stage) stage.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
            }

            if (event.target.closest('[data-proof-close]') || event.target.id === 'proofViewerModal') {
                closeProofViewer();
            }

            if (event.target.closest('[data-user-modal-close], [data-shop-modal-close], [data-activity-modal-close]')) {
                const modal = event.target.closest('.admin-user-modal, .admin-shop-modal, .admin-activity-modal');
                if (modal) {
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                }
            }
        });

        document.addEventListener('submit', function (event) {
            const form = event.target.closest('[data-accept-download-form]');
            if (!form || form.dataset.downloadStarted === 'true') return;
            event.preventDefault();
            const urls = Array.from(form.querySelectorAll('[data-download-url]')).map(function (input) {
                return input.value;
            }).filter(Boolean);
            form.dataset.downloadStarted = 'true';
            form.classList.add('is-loading');
            urls.forEach(function (url, index) {
                window.setTimeout(function () {
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = '';
                    link.target = '_blank';
                    link.rel = 'noopener';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }, index * 150);
            });
            const formData = new FormData(form);
            if (!formData.has('update_order')) formData.append('update_order', '1');
            window.setTimeout(function () {
                fetch(form.action, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Order update failed.');
                        const orderForm = document.querySelector('[data-live-target="owner_orders"]');
                        if (orderForm) refreshLiveForm(orderForm, { updateHistory: false });
                    })
                    .catch(function () {
                        form.dataset.downloadStarted = 'false';
                        form.classList.remove('is-loading');
                        if (window.ownerShowToast) window.ownerShowToast('Failed to update order status. Please try again.', 'error');
                    });
            }, Math.max(450, urls.length * 180));
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            closeProofViewer();
            document.querySelectorAll('.admin-user-modal.is-open, .admin-shop-modal.is-open, .admin-activity-modal.is-open, .order-modal.is-open').forEach(function (modal) {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            });
            document.body.classList.remove('order-modal-open');
        });
    }

    setupLiveSearch();
    setupNotificationPolling();
    setupNotificationDelegation();
    setupOwnerOrderPolling();
    setupDelegatedModals();

    window.printEaseRefreshLiveSearch = function (target) {
        const form = document.querySelector('[data-live-target="' + cssEscape(target) + '"]');
        if (form) refreshLiveForm(form, { updateHistory: false });
    };
})();
