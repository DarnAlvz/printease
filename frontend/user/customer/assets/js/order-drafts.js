(function () {
    "use strict";

    var DB_NAME = "printease-offline-drafts";
    var DB_VERSION = 1;
    var STORE = "pending_orders";
    var flushing = false;

    function getBaseUrl() {
        var scripts = document.getElementsByTagName("script");
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].getAttribute("src") || "";
            if (src.indexOf("order-drafts.js") !== -1) {
                var base = scripts[i].getAttribute("data-base-url");
                if (base) return base.replace(/\/+$/, "") + "/";
                break;
            }
        }
        return "/printease/";
    }

    function openDb() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function () {
                if (!req.result.objectStoreNames.contains(STORE)) {
                    req.result.createObjectStore(STORE, { keyPath: "id" });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }

    function getAllDrafts() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, "readonly");
                var req = tx.objectStore(STORE).getAll();
                req.onsuccess = function () { db.close(); resolve(req.result || []); };
                req.onerror = function () { db.close(); reject(req.error); };
            });
        });
    }

    function putDraft(draft) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, "readwrite");
                tx.objectStore(STORE).put(draft);
                tx.oncomplete = function () { db.close(); resolve(); };
                tx.onerror = function () { db.close(); reject(tx.error); };
                tx.onabort = function () { db.close(); reject(tx.error); };
            });
        });
    }

    function deleteDraft(id) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, "readwrite");
                tx.objectStore(STORE).delete(id);
                tx.oncomplete = function () { db.close(); resolve(); };
                tx.onerror = function () { db.close(); reject(tx.error); };
                tx.onabort = function () { db.close(); reject(tx.error); };
            });
        });
    }

    function updateDraft(id, patch) {
        return getAllDrafts().then(function (drafts) {
            var existing = drafts.filter(function (d) { return d.id === id; })[0];
            if (!existing) return;
            return putDraft(Object.assign({}, existing, patch));
        });
    }

    function randomHex32() {
        var bytes = new Uint8Array(16);
        if (window.crypto && crypto.getRandomValues) {
            crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
        }
        var hex = "";
        for (var i = 0; i < 16; i++) hex += bytes[i].toString(16).padStart(2, "0");
        return hex;
    }

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function formatPickup(value) {
        var d = new Date(value);
        if (Number.isNaN(d.getTime())) return "-";
        return d.toLocaleString([], { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
    }

    function saveDraft(payload) {
        var draft = Object.assign({
            id: randomHex32(),
            createdAt: new Date().toISOString(),
            status: "pending",
            needs_reschedule: false,
            shop_id: "",
            shop_name: "",
            order_service_type: "Document Printing",
            service_id: "",
            service_pricing_id: "",
            detected_page_count: "1",
            copies: "1",
            service_quantity: "1",
            pickup_datetime: "",
            customer_instruction: "",
            file_key: "document_file",
            file: null
        }, payload);

        return putDraft(draft).then(function () {
            refreshPendingUi();
            return draft;
        });
    }

    function buildFormData(draft, tokens) {
        var fd = new FormData();
        fd.append("csrf_token", tokens.csrf_token || "");
        fd.append("order_submit_token", tokens.order_submit_token || "");
        fd.append("submit_order", "1");
        fd.append("shop_id", String(draft.shop_id || ""));
        fd.append("order_service_type", String(draft.order_service_type || "Document Printing"));
        fd.append("service_id", String(draft.service_id || ""));
        fd.append("service_pricing_id", String(draft.service_pricing_id || ""));
        fd.append("detected_page_count", String(draft.detected_page_count || "1"));
        if (String(draft.order_service_type || "Document Printing") === "Document Printing") {
            fd.append("copies", String(draft.copies || "1"));
        } else {
            fd.append("service_quantity", String(draft.service_quantity || "1"));
        }
        fd.append("pickup_datetime", String(draft.pickup_datetime || ""));
        fd.append("customer_instruction", String(draft.customer_instruction || ""));
        if (draft.file && draft.file.blob) {
            fd.append(draft.file_key || "document_file", draft.file.blob, draft.file.name || "file");
        }
        return fd;
    }

    function sendDraft(draft) {
        var base = getBaseUrl();

        return fetch(base + "backend/actions/pending_order_token.php?draft_id=" + encodeURIComponent(draft.id), {
            credentials: "same-origin",
            cache: "no-store",
            headers: { "X-Requested-With": "XMLHttpRequest" }
        }).then(function (tokenRes) {
            if (!tokenRes.ok || /login\.php/i.test(tokenRes.url || "")) {
                return { success: false, reason: "auth" };
            }
            return tokenRes.json().then(function (tokens) {
                if (!tokens || !tokens.success) return { success: false, reason: "auth" };
                return fetch(base + "backend/actions/submit_order.php", {
                    method: "POST",
                    body: buildFormData(draft, tokens),
                    credentials: "same-origin",
                    redirect: "follow"
                }).then(function (response) {
                    var finalUrl = String(response.url || "").replace(/\/+$/, "");
                    if (response.ok && finalUrl.indexOf("orders.php") !== -1) {
                        return { success: true };
                    }
                    if (/login\.php/i.test(finalUrl)) {
                        return { success: false, reason: "auth" };
                    }
                    if (finalUrl.indexOf("explore.php") !== -1) {
                        return { success: false, reason: "rate_limit" };
                    }
                    return { success: false, message: "The request could not be sent and will retry automatically." };
                });
            });
        }).catch(function () {
            return { success: false, reason: "offline" };
        });
    }

    function notify(message, status, options) {
        if (window.customerShowToast) {
            window.customerShowToast(message, status, options);
        }
    }

    function flushPendingOrders(options) {
        if (flushing) return Promise.resolve({ skipped: true });
        flushing = true;
        setBannerBusy(true);

        return getAllDrafts().then(function (drafts) {
            drafts.sort(function (a, b) { return String(a.createdAt || "").localeCompare(String(b.createdAt || "")); });

            return Promise.all(drafts.map(function (draft) {
                if (draft.status === "sending") {
                    draft.status = "pending";
                    return updateDraft(draft.id, { status: "pending" });
                }
                return Promise.resolve();
            })).then(function () {
                function next(index) {
                    if (index >= drafts.length) return Promise.resolve({ sent: 0, failed: 0 });
                    var draft = drafts[index];

                    if (draft.needs_reschedule || draft.status === "sending") {
                        return next(index + 1);
                    }

                    var pickup = new Date(draft.pickup_datetime);
                    if (Number.isNaN(pickup.getTime()) || pickup.getTime() < Date.now()) {
                        return updateDraft(draft.id, { needs_reschedule: true }).then(function () {
                            notify("A saved request needs a new pickup time.", "warning", { title: "Needs reschedule" });
                            return next(index + 1);
                        });
                    }

                    return updateDraft(draft.id, { status: "sending" }).then(function () {
                        return sendDraft(draft).then(function (result) {
                            if (result.success) {
                                return deleteDraft(draft.id).then(function () {
                                    notify("Your request was sent to the shop.", "success", { title: "Draft sent" });
                                    return next(index + 1).then(function (agg) {
                                        agg.sent += 1;
                                        return agg;
                                    });
                                });
                            }

                            return updateDraft(draft.id, { status: "pending" }).then(function () {
                                if (result.reason === "rate_limit") {
                                    notify("You have reached the request limit for now. The rest will retry automatically.", "warning", { title: "Rate limit" });
                                    return { sent: 0, failed: drafts.length - index };
                                }
                                if (result.reason === "auth") {
                                    notify("Please log in again to send your saved requests.", "warning", { title: "Session expired" });
                                    return { sent: 0, failed: drafts.length - index };
                                }
                                if (result.reason !== "offline") {
                                    notify(result.message || "A saved request could not be sent and will retry.", "error", { title: "Send failed" });
                                }
                                return next(index + 1).then(function (agg) {
                                    agg.failed += 1;
                                    return agg;
                                });
                            });
                        }).catch(function () {
                            return updateDraft(draft.id, { status: "pending" }).then(function () {
                                return next(index + 1).then(function (agg) {
                                    agg.failed += 1;
                                    return agg;
                                });
                            });
                        });
                    });
                }

                return next(0);
            });
        }).catch(function () {
            return { sent: 0, failed: 0 };
        }).finally(function () {
            flushing = false;
            setBannerBusy(false);
            refreshPendingUi();
        });
    }

    function renderDraftRow(draft) {
        var isOffline = navigator.onLine === false;
        var statusText, statusClass;

        if (draft.needs_reschedule) {
            statusText = "Needs a new pickup time";
            statusClass = "is-danger";
        } else if (draft.status === "sending") {
            statusText = "Sending...";
            statusClass = "is-busy";
        } else if (isOffline) {
            statusText = "Queued — will send when you are back online";
            statusClass = "is-ok";
        } else {
            statusText = "Queued — will send automatically";
            statusClass = "is-ok";
        }

        var detailParts = [String(draft.order_service_type || "Document Printing")];
        if (draft.file && draft.file.name) detailParts.push(draft.file.name);
        if (String(draft.order_service_type || "Document Printing") === "Document Printing") {
            var pages = parseInt(draft.detected_page_count || "1", 10) || 1;
            var copies = parseInt(draft.copies || "1", 10) || 1;
            if (pages > 1 || copies > 1) detailParts.push(pages + " page" + (pages === 1 ? "" : "s") + " x " + copies + " cop" + (copies === 1 ? "y" : "ies"));
        } else {
            var qty = parseInt(draft.service_quantity || "1", 10) || 1;
            if (qty > 1) detailParts.push(qty + " item" + (qty === 1 ? "" : "s"));
        }

        return '<div class="customer-pending-draft" data-draft-row="' + esc(draft.id) + '">' +
            '<div class="customer-pending-draft__main">' +
            '<strong>' + esc(draft.shop_name || "Print shop") + '</strong>' +
            '<span>' + esc(detailParts.join(" \u00b7 ")) + '</span>' +
            '<span>Pickup: ' + esc(formatPickup(draft.pickup_datetime)) + '</span>' +
            '<span class="customer-pending-draft__status ' + statusClass + '">' + statusText + '</span>' +
            '</div>' +
            '<div class="customer-pending-draft__actions">' +
            '<button type="button" class="customer-pending-draft__btn" data-draft-reschedule="' + esc(draft.id) + '">Reschedule</button>' +
            '<button type="button" class="customer-pending-draft__btn customer-pending-draft__btn--danger" data-draft-remove="' + esc(draft.id) + '">Remove</button>' +
            '</div>' +
            '<div class="customer-pending-draft__reschedule" data-draft-reschedule-form="' + esc(draft.id) + '" hidden>' +
            '<input type="datetime-local" data-draft-pickup="' + esc(draft.id) + '" class="customer-pending-draft__input">' +
            '<button type="button" class="customer-pending-draft__btn" data-draft-pickup-save="' + esc(draft.id) + '">Save</button>' +
            '</div>' +
            '</div>';
    }

    function refreshPendingUi() {
        getAllDrafts().then(function (drafts) {
            var banner = document.querySelector("[data-customer-pending-banner]");
            var copy = document.querySelector("[data-pending-banner-copy]");
            var panel = document.querySelector("[data-pending-drafts-panel]");
            var list = document.querySelector("[data-pending-drafts-list]");
            var isOffline = navigator.onLine === false;

            var pending = drafts.filter(function (d) { return !d.needs_reschedule && d.status !== "sending"; });
            var reschedule = drafts.filter(function (d) { return d.needs_reschedule; });

            if (banner && copy) {
                if (drafts.length > 0) {
                    var text = "";
                    if (isOffline) {
                        text = drafts.length + " request" + (drafts.length === 1 ? "" : "s") + " saved as draft. Will be sent automatically when you\u2019re back online.";
                    } else {
                        text = pending.length + " request" + (pending.length === 1 ? "" : "s") + " waiting to be sent";
                        if (reschedule.length > 0) text += " \u00b7 " + reschedule.length + " need" + (reschedule.length === 1 ? "s" : "") + " a new pickup time";
                        text += ".";
                    }
                    copy.textContent = text;
                    banner.hidden = false;
                } else {
                    banner.hidden = true;
                }
            }

            if (list) list.innerHTML = drafts.map(renderDraftRow).join("");
            if (panel && drafts.length === 0) panel.hidden = true;

            var event = new CustomEvent("printease:offline-drafts-updated", {
                detail: { count: drafts.length, reschedule: reschedule.length }
            });
            window.dispatchEvent(event);
        }).catch(function () { });
    }

    function setBannerBusy(busy) {
        var banner = document.querySelector("[data-customer-pending-banner]");
        if (banner) banner.classList.toggle("is-sending", busy);
    }

    function updatePickup(id, value) {
        return updateDraft(id, { pickup_datetime: value, needs_reschedule: false });
    }

    document.addEventListener("click", function (event) {
        var target = event.target;

        if (target.closest("[data-pending-send-now]")) {
            event.preventDefault();
            flushPendingOrders({});
            return;
        }

        if (target.closest("[data-pending-view-drafts]")) {
            event.preventDefault();
            var panel = document.querySelector("[data-pending-drafts-panel]");
            if (panel) {
                panel.hidden = !panel.hidden;
                if (!panel.hidden) refreshPendingUi();
            }
            return;
        }

        if (target.closest("[data-pending-panel-close]")) {
            var closePanel = document.querySelector("[data-pending-drafts-panel]");
            if (closePanel) closePanel.hidden = true;
            return;
        }

        var removeBtn = target.closest("[data-draft-remove]");
        if (removeBtn) {
            event.preventDefault();
            var id = removeBtn.getAttribute("data-draft-remove");
            deleteDraft(id).then(refreshPendingUi);
            return;
        }

        var rescheduleBtn = target.closest("[data-draft-reschedule]");
        if (rescheduleBtn) {
            event.preventDefault();
            var rid = rescheduleBtn.getAttribute("data-draft-reschedule");
            var form = document.querySelector('[data-draft-reschedule-form="' + rid + '"]');
            if (form) form.hidden = !form.hidden;
            return;
        }

        var saveBtn = target.closest("[data-draft-pickup-save]");
        if (saveBtn) {
            event.preventDefault();
            var sid = saveBtn.getAttribute("data-draft-pickup-save");
            var pickupInput = document.querySelector('[data-draft-pickup="' + sid + '"]');
            if (pickupInput && pickupInput.value) {
                var d = new Date(pickupInput.value);
                if (Number.isNaN(d.getTime()) || d.getTime() < Date.now()) {
                    notify("Please choose a future pickup time.", "error", { title: "Invalid pickup" });
                    return;
                }
                updatePickup(sid, pickupInput.value).then(function () {
                    notify("Pickup time updated.", "success", { title: "Rescheduled" });
                    refreshPendingUi();
                });
            }
            return;
        }
    });

    window.addEventListener("online", function () {
        refreshPendingUi();
        getAllDrafts().then(function (drafts) {
            if (drafts.length > 0) flushPendingOrders({ silent: true });
        }).catch(function () { });
    });

    function runOnReady(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    runOnReady(function () {
        refreshPendingUi();
        if (navigator.onLine !== false) {
            getAllDrafts().then(function (drafts) {
                if (drafts.length > 0) flushPendingOrders({ silent: true });
            }).catch(function () { });
        }
    });

    window.OrderDrafts = {
        saveDraft: saveDraft,
        getAll: getAllDrafts,
        remove: deleteDraft,
        updatePickup: updatePickup,
        flush: function (options) { return flushPendingOrders(options); },
        isOffline: function () { return navigator.onLine === false; },
        refresh: refreshPendingUi
    };
})();
