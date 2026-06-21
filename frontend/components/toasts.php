<?php

function appToastIcon($status)
{
    return match ($status) {
        'success' => '<path d="M20 6 9 17l-5-5"/>',
        'error' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/>',
        'warning' => '<path d="M12 3 2.6 19h18.8L12 3Z"/><path d="M12 9v4m0 3h.01"/>',
        default => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>',
    };
}

function renderAppToasts($role = 'customer')
{
    $role = in_array($role, ['customer', 'owner', 'admin'], true) ? $role : 'customer';
    $toasts = array_slice(consumeToasts(), -3);
    ?>
    <style>
        .app-toast-stack {
            --role-accent: #08b7d0;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10060;
            display: grid;
            gap: 12px;
            width: min(420px, calc(100vw - 32px));
            pointer-events: none;
        }
        .app-toast-stack[data-role="customer"] { top: 88px; bottom: auto; }
        .app-toast-stack[data-role="owner"] { --role-accent: #2563eb; top: 84px; }
        .app-toast-stack[data-role="admin"] { --role-accent: #070566; top: 76px; }
        .app-toast {
            --toast-accent: var(--role-accent);
            --toast-surface: #effbff;
            --toast-text: #075985;
            position: relative;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            overflow: hidden;
            padding: 14px 14px 17px;
            border: 1px solid color-mix(in srgb, var(--toast-accent) 28%, transparent);
            border-left: 4px solid var(--toast-accent);
            border-radius: 14px;
            background: var(--toast-surface);
            color: var(--toast-text);
            box-shadow: 0 18px 45px rgba(15, 23, 42, .2);
            opacity: 0;
            transform: translateX(24px);
            transition: opacity .25s ease, transform .25s ease;
            pointer-events: auto;
        }
        .app-toast.is-visible { opacity: 1; transform: translateX(0); }
        .app-toast.success { --toast-accent: #16a34a; --toast-surface: #f0fdf4; --toast-text: #166534; }
        .app-toast.error { --toast-accent: #dc2626; --toast-surface: #fef2f2; --toast-text: #991b1b; }
        .app-toast.warning { --toast-accent: #d97706; --toast-surface: #fffbeb; --toast-text: #92400e; }
        html[data-customer-theme="dark"] .app-toast-stack[data-role="customer"] .app-toast {
            --toast-surface: #082033;
            --toast-text: #e0f7ff;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .42);
        }
        html[data-customer-theme="dark"] .app-toast-stack[data-role="customer"] .app-toast.success { --toast-surface: #06281a; --toast-text: #bbf7d0; }
        html[data-customer-theme="dark"] .app-toast-stack[data-role="customer"] .app-toast.error { --toast-surface: #330b12; --toast-text: #fecdd3; }
        html[data-customer-theme="dark"] .app-toast-stack[data-role="customer"] .app-toast.warning { --toast-surface: #2d1d05; --toast-text: #fde68a; }
        .app-toast-icon {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--toast-accent) 12%, transparent);
            color: var(--toast-accent);
        }
        .app-toast-icon svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .app-toast-copy { min-width: 0; font-size: 14px; line-height: 1.4; }
        .app-toast-title { display: block; margin-bottom: 2px; color: inherit; font-size: 14px; font-weight: 800; }
        .app-toast-message { display: block; font-weight: 600; overflow-wrap: anywhere; }
        .app-toast-action { display: inline-flex; margin-top: 7px; color: var(--toast-accent); font-size: 13px; font-weight: 800; text-decoration: underline; text-underline-offset: 3px; }
        .app-toast-close { display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 999px; background: rgba(15, 23, 42, .08); color: inherit; font-size: 20px; cursor: pointer; }
        .app-toast-progress { position: absolute; right: 0; bottom: 0; left: 0; height: 3px; background: var(--toast-accent); transform-origin: left center; animation: app-toast-progress 5s linear forwards; animation-play-state: paused; }
        @keyframes app-toast-progress { to { transform: scaleX(0); } }
        @media (max-width: 820px) {
            .app-toast-stack { top: 16px; right: 16px; left: 16px; width: auto; }
            .app-toast-stack[data-role="customer"] {
                top: calc(76px + env(safe-area-inset-top));
                bottom: auto;
            }
        }
        @media (prefers-reduced-motion: reduce) { .app-toast { transition-duration: .01ms; } .app-toast-progress { animation: none; } }
    </style>
    <div class="app-toast-stack" id="appToastStack" data-role="<?php echo e($role); ?>" aria-live="polite" aria-atomic="false">
        <?php foreach ($toasts as $toast): ?>
            <article class="app-toast <?php echo e($toast['status']); ?>" role="<?php echo $toast['status'] === 'error' ? 'alert' : 'status'; ?>">
                <span class="app-toast-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><?php echo appToastIcon($toast['status']); ?></svg></span>
                <span class="app-toast-copy">
                    <?php if ($toast['title'] !== ''): ?><strong class="app-toast-title"><?php echo e($toast['title']); ?></strong><?php endif; ?>
                    <span class="app-toast-message"><?php echo e($toast['message']); ?></span>
                    <?php if ($toast['action_label'] !== '' && $toast['action_url'] !== ''): ?><a class="app-toast-action" href="<?php echo e($toast['action_url']); ?>"><?php echo e($toast['action_label']); ?></a><?php endif; ?>
                </span>
                <button type="button" class="app-toast-close" aria-label="Dismiss notification">&times;</button>
                <span class="app-toast-progress" aria-hidden="true"></span>
            </article>
        <?php endforeach; ?>
    </div>
    <script>
        (function () {
            const stack = document.getElementById('appToastStack');
            if (!stack) return;
            const icons = {
                success: '<path d="M20 6 9 17l-5-5"/>',
                error: '<circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/>',
                warning: '<path d="M12 3 2.6 19h18.8L12 3Z"/><path d="M12 9v4m0 3h.01"/>',
                info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>'
            };
            function activate(toast) {
                const progress = toast.querySelector('.app-toast-progress');
                let remaining = 5000, startedAt = Date.now(), timer = null, paused = true;
                function close() { clearTimeout(timer); toast.classList.remove('is-visible'); setTimeout(function () { toast.remove(); }, 250); }
                function resume() { if (!paused || toast.matches(':hover') || toast.contains(document.activeElement)) return; paused = false; startedAt = Date.now(); if (progress) progress.style.animationPlayState = 'running'; timer = setTimeout(close, remaining); }
                function pause() { if (paused) return; clearTimeout(timer); paused = true; remaining = Math.max(0, remaining - (Date.now() - startedAt)); if (progress) progress.style.animationPlayState = 'paused'; }
                toast.querySelector('.app-toast-close').addEventListener('click', close);
                toast.addEventListener('mouseenter', pause); toast.addEventListener('mouseleave', resume);
                toast.addEventListener('focusin', pause); toast.addEventListener('focusout', function () { setTimeout(resume, 0); });
                requestAnimationFrame(function () { toast.classList.add('is-visible'); }); resume();
            }
            window.appShowToast = function (message, status, options) {
                options = options || {}; status = ['success', 'error', 'warning', 'info'].includes(status) ? status : 'info';
                while (stack.children.length >= 3) stack.firstElementChild.remove();
                const toast = document.createElement('article'); toast.className = 'app-toast ' + status; toast.setAttribute('role', status === 'error' ? 'alert' : 'status');
                const action = options.actionLabel && options.actionUrl ? '<a class="app-toast-action"></a>' : '';
                toast.innerHTML = '<span class="app-toast-icon"><svg viewBox="0 0 24 24" aria-hidden="true">' + icons[status] + '</svg></span><span class="app-toast-copy"><strong class="app-toast-title"></strong><span class="app-toast-message"></span>' + action + '</span><button type="button" class="app-toast-close" aria-label="Dismiss notification">&times;</button><span class="app-toast-progress" aria-hidden="true"></span>';
                const title = toast.querySelector('.app-toast-title'); title.textContent = options.title || ''; if (!options.title) title.remove();
                toast.querySelector('.app-toast-message').textContent = message;
                const link = toast.querySelector('.app-toast-action'); if (link) { link.textContent = options.actionLabel; link.href = options.actionUrl; }
                stack.appendChild(toast); activate(toast);
            };
            window.customerShowToast = window.ownerShowToast = window.adminShowToast = window.appShowToast;
            Array.from(stack.querySelectorAll('.app-toast')).forEach(activate);
        })();
    </script>
    <?php
}
