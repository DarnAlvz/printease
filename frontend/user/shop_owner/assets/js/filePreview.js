(function () {
    'use strict';

    const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
    const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const PERMIT_EXTENSIONS = IMAGE_EXTENSIONS.concat(['pdf']);
    const PERMIT_MIME_TYPES = IMAGE_MIME_TYPES.concat(['application/pdf']);
    const PERMIT_MAX_BYTES = 10 * 1024 * 1024;

    function extensionOf(file) {
        const name = (file && file.name) || '';
        const dot = name.lastIndexOf('.');
        return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatSize(bytes) {
        if (!Number.isFinite(bytes) || bytes < 0) {
            return '';
        }
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1024 * 1024) {
            return Math.round(bytes / 1024) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function isPdf(file) {
        return extensionOf(file) === 'pdf' || (file && file.type) === 'application/pdf';
    }

    function isAccepted(config, file) {
        if (config.allowedExtensions && config.allowedExtensions.indexOf(extensionOf(file)) === -1) {
            return false;
        }
        if (config.allowedMimeTypes && config.allowedMimeTypes.indexOf(file.type) === -1) {
            return false;
        }
        if (config.maxBytes && file.size > config.maxBytes) {
            return false;
        }
        return true;
    }

    function ownerFilePreview(config) {
        const input = config.input;
        if (!input) {
            return null;
        }

        const targets = config.targets || [];
        const snapshots = new Map();
        const meta = document.querySelector('[data-file-meta-for="' + input.id + '"]');
        const clearButton = document.querySelector('[data-file-clear-for="' + input.id + '"]');
        const defaultMeta = meta ? meta.textContent : '';
        let previewUrl = '';

        targets.forEach(function (target) {
            const element = document.querySelector(target.selector);
            if (element) {
                snapshots.set(target.selector, element.outerHTML);
            }
        });

        function revokePreviewUrl() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }
        }

        function restoreTargets() {
            revokePreviewUrl();
            snapshots.forEach(function (markup, selector) {
                const element = document.querySelector(selector);
                if (element) {
                    element.outerHTML = markup;
                }
            });
        }

        function showPreview(file) {
            revokePreviewUrl();
            previewUrl = URL.createObjectURL(file);

            targets.forEach(function (target) {
                const element = document.querySelector(target.selector);
                if (!element) {
                    return;
                }
                const markup = target.build(file, previewUrl);
                if (target.outer) {
                    element.outerHTML = markup;
                } else {
                    element.innerHTML = markup;
                }
            });
        }

        function setMeta(text) {
            if (meta) {
                meta.textContent = text;
            }
        }

        function setClearVisible(visible) {
            if (!clearButton) {
                return;
            }
            if (visible) {
                clearButton.removeAttribute('hidden');
            } else {
                clearButton.setAttribute('hidden', 'hidden');
            }
        }

        function reset() {
            restoreTargets();
            setMeta(defaultMeta);
            setClearVisible(false);
        }

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];

            if (!file) {
                reset();
                return;
            }

            if (!isAccepted(config, file)) {
                input.value = '';
                reset();
                if (typeof window.ownerShowToast === 'function') {
                    window.ownerShowToast(config.errorMessage, 'error');
                }
                return;
            }

            showPreview(file);
            setMeta(file.name + ' · ' + formatSize(file.size) + ' · not saved yet');
            setClearVisible(true);
        });

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.dispatchEvent(new Event('change'));
            });
        }

        window.addEventListener('beforeunload', revokePreviewUrl);

        return { clear: reset };
    }

    ownerFilePreview.isPdf = isPdf;
    ownerFilePreview.escapeHtml = escapeHtml;
    ownerFilePreview.pdfBadge = function (file, url) {
        const name = escapeHtml(file.name);
        return '<a class="permit-preview permit-preview-pdf" href="' + url + '" target="_blank" rel="noopener"' +
            ' title="Open ' + name + ' in a new tab" aria-label="Open ' + name + ' in a new tab">' +
            '<span class="permit-preview-pdf-label">PDF</span></a>';
    };

    function permitPreviewBlock(file, url) {
        const thumb = isPdf(file)
            ? ownerFilePreview.pdfBadge(file, url)
            : '<img src="' + url + '" class="permit-preview image-view-trigger"' +
                ' alt="Selected business permit preview" data-lightbox-img' +
                ' data-file-ext="' + escapeHtml(extensionOf(file)) + '">';

        return '<div class="permit-preview-block is-pending">' + thumb +
            '<div><strong>' + escapeHtml(file.name) + '</strong>' +
            '<span>Preview of the file you selected. Save to replace your current permit.</span></div></div>';
    }

    window.ownerFilePreview = ownerFilePreview;

    ownerFilePreview({
        input: document.getElementById('shop_logo'),
        allowedExtensions: IMAGE_EXTENSIONS,
        allowedMimeTypes: IMAGE_MIME_TYPES,
        errorMessage: 'Please select a valid JPG, PNG, or WebP shop logo.',
        targets: [
            {
                selector: '[data-shop-logo-preview="profile"]',
                build: function (file, url) {
                    return '<img src="' + url + '" class="shop-logo-profile" alt="Selected shop logo preview">';
                }
            },
            {
                selector: '[data-shop-logo-preview="sidebar"]',
                outer: true,
                build: function (file, url) {
                    return '<img src="' + url + '" class="owner-brand-logo" alt="Selected shop logo preview"' +
                        ' data-shop-logo-preview="sidebar">';
                }
            },
            {
                selector: '[data-shop-logo-preview="topbar"]',
                outer: true,
                build: function (file, url) {
                    return '<img src="' + url + '" class="owner-user-logo" alt="Selected shop logo preview"' +
                        ' data-shop-logo-preview="topbar">';
                }
            }
        ]
    });

    ownerFilePreview({
        input: document.getElementById('business_permit_file'),
        allowedExtensions: PERMIT_EXTENSIONS,
        allowedMimeTypes: PERMIT_MIME_TYPES,
        maxBytes: PERMIT_MAX_BYTES,
        errorMessage: 'Please select a valid JPG, PNG, WebP, or PDF business permit file up to 10MB.',
        targets: [
            {
                selector: '[data-file-preview="business_permit_file"]',
                build: permitPreviewBlock
            }
        ]
    });

    ownerFilePreview({
        input: document.getElementById('gcash_qr_file'),
        allowedExtensions: IMAGE_EXTENSIONS,
        allowedMimeTypes: IMAGE_MIME_TYPES,
        errorMessage: 'Please select a valid JPG, PNG, or WebP GCash QR code.',
        targets: [
            {
                selector: '[data-file-preview="gcash_qr_file"]',
                build: function (file, url) {
                    return '<div class="shop-logo-panel is-pending">' +
                        '<img src="' + url + '" class="shop-logo-preview image-view-trigger"' +
                        ' alt="Selected GCash QR preview" data-lightbox-img>' +
                        '<div><h3>Selected GCash QR</h3>' +
                        '<p class="card-note">Preview of the file you selected. Save to replace your current QR code.</p></div></div>';
                }
            }
        ]
    });
})();
