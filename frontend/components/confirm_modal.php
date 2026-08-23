<?php

function renderAppConfirmModal()
{
    ?>
    <style>
        .app-confirm-modal {
            position: fixed;
            inset: 0;
            z-index: 11150;
            padding: 24px;
            background: rgba(3, 4, 94, .38);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
        }

        .app-confirm-modal.is-open {
            display: flex;
        }

        .app-confirm-modal[data-tone="danger"] {
            --confirm-accent: #ef4444;
            --confirm-accent-deep: #b91c1c;
        }

        .app-confirm-modal[data-tone="warning"] {
            --confirm-accent: #f59e0b;
            --confirm-accent-deep: #d97706;
        }

        .app-confirm-modal__panel {
            width: min(420px, 100%);
            padding: 32px 28px 24px;
            border: 1px solid #b7efff;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(3, 4, 94, .24);
            text-align: center;
            animation: app-confirm-pop .22s ease;
        }

        @keyframes app-confirm-pop {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.97);
            }
        }

        .app-confirm-modal__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            margin-bottom: 16px;
            color: var(--confirm-accent);
            background: color-mix(in srgb, var(--confirm-accent) 14%, #fff);
        }

        .app-confirm-modal__icon svg {
            width: 28px;
            height: 28px;
        }

        .app-confirm-modal__panel h3 {
            margin: 0 0 10px;
            color: #03045e;
            font-size: 18px;
            font-weight: 800;
        }

        .app-confirm-modal__panel p {
            margin: 0 0 24px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .app-confirm-modal__actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .app-confirm-modal__actions .btn,
        .app-confirm-modal__actions button.btn {
            min-height: 42px;
            padding: 0 22px;
            border-radius: 11px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: none;
        }

        .app-confirm-modal button.app-confirm-modal__cancel-btn {
            color: #0077b6;
            background: #e7f7ff;
        }

        .app-confirm-modal button.app-confirm-modal__cancel-btn:hover {
            background: #d4f1ff;
        }

        .app-confirm-modal button.app-confirm-modal__confirm-btn {
            color: #fff;
            background: linear-gradient(135deg, var(--confirm-accent), var(--confirm-accent-deep));
            box-shadow: 0 8px 14px color-mix(in srgb, var(--confirm-accent) 28%, transparent);
        }

        .app-confirm-modal button.app-confirm-modal__confirm-btn:hover {
            filter: brightness(1.05);
        }

        .app-confirm-modal button.app-confirm-modal__confirm-btn:focus-visible,
        .app-confirm-modal button.app-confirm-modal__cancel-btn:focus-visible {
            outline: 2px solid var(--confirm-accent);
            outline-offset: 2px;
        }

        @media (max-width: 480px) {
            .app-confirm-modal {
                padding: 16px;
            }

            .app-confirm-modal__actions .btn,
            .app-confirm-modal__actions button.btn {
                flex: 1 1 auto;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .app-confirm-modal__panel {
                animation: none;
            }
        }
    </style>
    <div class="app-confirm-modal" id="appConfirmModal" data-tone="danger" aria-hidden="true">
        <div class="app-confirm-modal__panel" role="alertdialog" aria-modal="true" aria-labelledby="appConfirmTitle"
            aria-describedby="appConfirmMessage">
            <span class="app-confirm-modal__icon" id="appConfirmIcon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m15 9-6 6" />
                    <path d="m9 9 6 6" />
                </svg>
            </span>
            <h3 id="appConfirmTitle">Are you sure?</h3>
            <p id="appConfirmMessage"></p>
            <div class="app-confirm-modal__actions">
                <button type="button" class="btn app-confirm-modal__cancel-btn" id="appConfirmCancel">Cancel</button>
                <button type="button" class="btn app-confirm-modal__confirm-btn" id="appConfirmOk">Confirm</button>
            </div>
        </div>
    </div>
    <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
        (function () {
            const modal = document.getElementById('appConfirmModal');
            if (!modal || window.appConfirm) return;

            const titleEl = document.getElementById('appConfirmTitle');
            const messageEl = document.getElementById('appConfirmMessage');
            const iconEl = document.getElementById('appConfirmIcon');
            const okBtn = document.getElementById('appConfirmOk');
            const cancelBtn = document.getElementById('appConfirmCancel');
            const icons = {
                danger: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
                warning: '<path d="M12 3 2.6 19h18.8L12 3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'
            };
            let pendingResolve = null;
            let lastFocus = null;

            function finish(result) {
                if (!modal.classList.contains('is-open')) return;
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                if (lastFocus && typeof lastFocus.focus === 'function') {
                    try { lastFocus.focus(); } catch (error) { /* element may be gone */ }
                }
                lastFocus = null;
                if (pendingResolve) {
                    const resolve = pendingResolve;
                    pendingResolve = null;
                    resolve(result);
                }
            }

            window.appConfirm = function (options) {
                options = options || {};
                finish(false);

                const tone = options.tone === 'warning' ? 'warning' : 'danger';
                titleEl.textContent = options.title || 'Are you sure?';
                messageEl.textContent = options.message || '';
                messageEl.hidden = !options.message;
                okBtn.textContent = options.confirmText || 'Yes, continue';
                cancelBtn.textContent = options.cancelText || 'Cancel';
                modal.dataset.tone = tone;
                iconEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + icons[tone] + '</svg>';

                lastFocus = document.activeElement instanceof Element ? document.activeElement : null;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                okBtn.focus();

                return new Promise(function (resolve) {
                    pendingResolve = resolve;
                });
            };

            window.customerConfirm = window.ownerConfirm = window.adminConfirm = window.appConfirm;

            okBtn.addEventListener('click', function () { finish(true); });
            cancelBtn.addEventListener('click', function () { finish(false); });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) finish(false);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) finish(false);
            });
        })();
    </script>
    <?php
}
