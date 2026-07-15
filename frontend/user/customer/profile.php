<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../components/head.php";
require_once __DIR__ . "/../../components/customer_layout.php";
require_once __DIR__ . "/../../components/customer_toasts.php";

$customer_id = $_SESSION['user_id'];

// Fetch current user info including account status
$stmt = mysqli_prepare($conn, "SELECT full_name, email, phone_number, address, profile_picture, valid_id_file, account_status, created_at FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$account_status = $user['account_status'] ?? 'incomplete';
$profile_picture_url = !empty($user['profile_picture']) ? BASE_URL . e($user['profile_picture']) : '';
$valid_id_url = !empty($user['valid_id_file']) ? BASE_URL . e($user['valid_id_file']) : '';
$valid_id_extension = strtolower(pathinfo((string) ($user['valid_id_file'] ?? ''), PATHINFO_EXTENSION));
$valid_id_is_pdf = $valid_id_extension === 'pdf';
$valid_id_is_image = in_array($valid_id_extension, ['jpg', 'jpeg', 'png', 'webp'], true);
$customer_name = (string) ($user['full_name'] ?? ($_SESSION['full_name'] ?? 'Customer'));
$customer_email = (string) ($user['email'] ?? ($_SESSION['email'] ?? ''));
$created_at = !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : 'N/A';

if (empty($_SESSION['customer_password_csrf'])) {
    $_SESSION['customer_password_csrf'] = bin2hex(random_bytes(32));
}

$reopen_password_modal = !empty($_SESSION['reopen_customer_password_modal']);
unset($_SESSION['reopen_customer_password_modal']);
$uses_google_session = ($_SESSION['auth_provider'] ?? 'password') === 'google';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Customer Profile</title>
    <?php renderCustomerHead(); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/tailwind.css">
    <style>
        .customer-modal[hidden] {
            display: none;
        }

        .customer-modal {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(15, 23, 42, .62);
            opacity: 0;
            transition: opacity .2s ease;
        }

        .customer-modal.is-visible {
            opacity: 1;
        }

        .customer-modal-panel {
            width: min(100%, 460px);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            transform: translateY(12px) scale(.98);
            transition: transform .2s ease;
        }

        .customer-modal.is-visible .customer-modal-panel {
            transform: translateY(0) scale(1);
        }

        body.customer-modal-open {
            overflow: hidden;
        }

        .customer-valid-id-panel {
            width: min(100%, 720px);
        }

        .customer-valid-id-preview {
            display: grid;
            place-items: center;
            min-height: 55vh;
            max-height: 68vh;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #f8fafc;
        }

        .customer-valid-id-preview img,
        .customer-valid-id-preview iframe {
            width: 100%;
            border: 0;
            border-radius: 14px;
        }

        .customer-valid-id-preview img {
            height: auto;
            max-height: 68vh;
            object-fit: contain;
        }

        .customer-valid-id-preview iframe {
            min-height: 62vh;
            background: #fff;
        }

        @media (prefers-reduced-motion: reduce) {
            .customer-modal,
            .customer-modal-panel {
                transition-duration: .01ms;
            }
        }
    </style>
</head>

<body class="customer-body bg-gray-100 min-h-screen pb-24">
    <?php customerToastRender(); ?>

    <div class="max-w-md md:max-w-5xl mx-auto min-h-screen">
        <?php renderCustomerLayout(['title' => 'Customer Profile', 'subtitle' => 'Manage your account details and verification files.']); ?>

        <main class="p-4 md:p-6">
            <?php
            $statusClass = match ($account_status) {
                'verified' => 'bg-green-100 border-green-500 text-green-800',
                'pending' => 'bg-blue-100 border-blue-500 text-blue-800',
                'rejected' => 'bg-red-100 border-red-500 text-red-800',
                default => 'bg-yellow-100 border-yellow-500 text-yellow-800'
            };

            $statusText = match ($account_status) {
                'verified' => 'Your account is verified. You have full access.',
                'pending' => 'Your profile is submitted. Please wait for Super Admin approval.',
                'rejected' => 'Your account has been rejected. Contact the administrator.',
                default => 'Please complete your profile and upload a valid ID.'
            };
            ?>

            <div class="<?php echo $statusClass; ?> border-l-4 p-4 rounded-xl shadow mb-5">
                <p class="font-bold">Account Status: <?php echo e(ucfirst($account_status)); ?></p>
                <p class="text-sm mt-1"><?php echo e($statusText); ?></p>
            </div>

            <section class="customer-profile-summary bg-white p-5 md:p-6 rounded-2xl shadow mb-5">
                <button type="button" id="customerEditProfileTrigger" class="customer-profile-card-edit"
                    aria-label="Edit profile">
                    <?php echo customerIcon('edit'); ?>
                </button>
                <div class="customer-profile-hero-band" aria-hidden="true"></div>
                <div class="customer-profile-identity">
                    <div class="customer-profile-avatar">
                        <?php if ($profile_picture_url !== ''): ?>
                            <img src="<?php echo $profile_picture_url; ?>" alt="<?php echo e($customer_name); ?> profile picture">
                        <?php else: ?>
                            <span><?php echo e(customerInitials($customer_name)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="customer-profile-eyebrow">Customer Account</span>
                        <h2><?php echo e($customer_name); ?></h2>
                        <p><?php echo customerIcon('mail'); ?><?php echo e($customer_email !== '' ? $customer_email : 'No email recorded'); ?></p>
                    </div>
                </div>

                <div class="customer-profile-summary-grid" data-profile-view-mode>
                    <article>
                        <span class="customer-profile-detail-icon"><?php echo customerIcon('phone'); ?></span>
                        <span>
                            <small>Phone</small>
                            <strong><?php echo e($user['phone_number'] ?: 'Not set'); ?></strong>
                        </span>
                    </article>
                    <article>
                        <span class="customer-profile-detail-icon"><?php echo customerIcon('pin'); ?></span>
                        <span>
                            <small>Address</small>
                            <strong><?php echo e($user['address'] ?: 'Not set'); ?></strong>
                        </span>
                    </article>
                    <article>
                        <span class="customer-profile-detail-icon"><?php echo customerIcon('orders'); ?></span>
                        <span>
                            <small>Valid ID</small>
                            <?php if ($valid_id_url !== ''): ?>
                                <button type="button" class="text-blue-600 font-semibold text-left"
                                    data-valid-id-modal-open aria-haspopup="dialog"
                                    aria-controls="customerValidIdModal">View uploaded ID</button>
                            <?php else: ?>
                                <strong>Not uploaded</strong>
                            <?php endif; ?>
                        </span>
                    </article>
                    <article>
                        <span class="customer-profile-detail-icon"><?php echo customerIcon('clock'); ?></span>
                        <span>
                            <small>Member Since</small>
                            <strong><?php echo e($created_at); ?></strong>
                        </span>
                    </article>
                </div>

                <form action="../../../backend/actions/save_customer_profile.php" method="POST"
                    enctype="multipart/form-data"
                    id="customerProfileEditForm"
                    class="customer-profile-edit-form"
                    hidden>

                    <div class="customer-profile-form-head">
                        <div>
                            <span class="customer-profile-eyebrow">Edit Profile</span>
                            <h2 class="text-lg font-bold text-gray-900">Update your details</h2>
                        </div>
                        <button type="button" id="customerCancelEditProfile" class="customer-profile-cancel-button">Cancel</button>
                    </div>

                    <div class="customer-profile-photo-field">
                        <input type="file" id="customerProfilePictureInput" name="profile_picture"
                            accept="image/jpeg,image/png,image/webp" hidden>
                        <button type="button" class="customer-profile-photo-button" id="customerProfilePictureButton"
                            aria-label="Change profile picture">
                            <span class="customer-profile-photo-preview" data-profile-picture-preview>
                                <?php if ($profile_picture_url !== ''): ?>
                                    <img src="<?php echo $profile_picture_url; ?>" alt="<?php echo e($customer_name); ?> profile picture">
                                <?php else: ?>
                                    <b><?php echo e(customerInitials($customer_name)); ?></b>
                                <?php endif; ?>
                                <span class="customer-profile-photo-overlay"><?php echo customerIcon('profile'); ?></span>
                            </span>
                            <span class="customer-profile-photo-copy">
                                <strong>Change photo</strong>
                                <small data-profile-picture-status>JPG, PNG, or WebP only.</small>
                            </span>
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Full Name</label>
                        <input type="text" name="full_name" value="<?php echo e($customer_name); ?>"
                            class="w-full border rounded-xl p-3" minlength="2" maxlength="100"
                            autocomplete="name" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Phone Number</label>
                        <input type="text" name="phone_number" value="<?php echo e($user['phone_number'] ?? ''); ?>"
                            class="w-full border rounded-xl p-3" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Address</label>

                        <textarea id="address" name="address" rows="3" class="w-full border rounded-xl p-3"
                            placeholder="Click Use My Current Location or type your complete address"
                            required><?php echo e($user['address'] ?? ''); ?></textarea>

                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">

                        <button type="button" onclick="useCurrentLocation()"
                            class="mt-2 bg-green-600 text-white py-2 px-4 rounded-xl font-semibold">
                            Use My Current Location
                        </button>

                        <p id="locationStatus" class="text-sm text-gray-500 mt-2"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">Valid ID</label>
                        <input type="file" name="valid_id_file" accept="image/jpeg,image/png,image/webp,application/pdf"
                            class="w-full border rounded-xl p-3">
                        <p class="text-xs text-gray-500 mt-1">Upload only if you need to replace your verification document.</p>
                        <?php if ($valid_id_url !== ''): ?>
                            <button type="button" data-valid-id-modal-open
                                class="inline-block mt-2 text-blue-600 font-semibold">
                                View Current ID
                            </button>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="save_profile"
                        class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">
                        Save Profile
                    </button>
                </form>

            </section>

            <section class="customer-profile-preferences bg-white p-5 md:p-6 rounded-2xl shadow mb-5"
                aria-labelledby="customerAppearanceTitle">
                <div>
                    <span class="customer-profile-eyebrow">Appearance</span>
                    <h2 class="text-lg font-bold text-gray-900" id="customerAppearanceTitle">Display Mode</h2>
                    <p class="text-sm text-gray-600 mt-1">Choose the customer app theme for this browser.</p>
                </div>
                <button type="button" class="customer-profile-theme-toggle customer-theme-toggle" data-customer-theme-toggle
                    aria-label="Switch to dark mode" aria-pressed="false">
                    <span class="customer-theme-toggle__sun"><?php echo customerIcon('sun'); ?></span>
                    <span class="customer-theme-toggle__moon"><?php echo customerIcon('moon'); ?></span>
                </button>
            </section>

            <section class="bg-white p-5 md:p-6 rounded-2xl shadow mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                aria-labelledby="accountSecurityTitle">
                <div>
                    <h2 class="text-lg font-bold text-gray-900" id="accountSecurityTitle">Account Security</h2>
                    <p class="text-sm text-gray-600 mt-1">Update the password used to access your PrintEase account.</p>
                </div>
                <button type="button" id="customerChangePasswordTrigger"
                    class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-3 rounded-xl font-semibold whitespace-nowrap focus:outline-none focus:ring-4 focus:ring-blue-200"
                    aria-haspopup="dialog" aria-controls="customerChangePasswordModal">
                    Change Password
                </button>
            </section>

            <section class="customer-profile-session bg-white p-5 md:p-6 rounded-2xl shadow mt-5"
                aria-labelledby="customerSessionTitle">
                <div>
                    <h2 class="text-lg font-bold text-gray-900" id="customerSessionTitle">Account Session</h2>
                    <p class="text-sm text-gray-600 mt-1">End your current PrintEase session on this device.</p>
                </div>

                <div class="customer-profile-actions">
                    <a class="customer-profile-logout" href="<?php echo BASE_URL; ?>backend/actions/logout.php">
                        <span class="customer-profile-action-icon"><?php echo customerIcon('logout'); ?></span>
                        <span>
                            <strong>Logout</strong>
                            <small>End your current session</small>
                        </span>
                    </a>
                </div>
            </section>
        </main>
    </div>

    <div class="customer-modal" id="customerChangePasswordModal" role="dialog" aria-modal="true"
        aria-labelledby="customerChangePasswordTitle" data-reopen="<?php echo $reopen_password_modal ? 'true' : 'false'; ?>"
        hidden>
        <section class="customer-modal-panel bg-white rounded-2xl shadow-2xl p-5 md:p-6" tabindex="-1">
            <header class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-xl font-bold text-gray-900" id="customerChangePasswordTitle">Change Password</h2>
                    <p class="text-sm text-gray-600 mt-1">Update the password used for standard email sign-in.</p>
                </div>
                <button type="button" data-password-modal-close
                    class="w-10 h-10 rounded-full text-gray-600 hover:bg-gray-100 text-2xl leading-none focus:outline-none focus:ring-4 focus:ring-blue-100"
                    aria-label="Close change password dialog">&times;</button>
            </header>

            <form action="../../../backend/actions/change_customer_password.php" method="POST"
                id="customerChangePasswordForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['customer_password_csrf']); ?>">

                <?php if (!$uses_google_session): ?>
                    <div>
                        <label for="customer_current_password" class="block text-sm font-semibold mb-1">Current Password</label>
                        <div class="flex rounded-xl border bg-white focus-within:ring-2 focus-within:ring-blue-200 focus-within:border-blue-600">
                            <input id="customer_current_password" type="password" name="current_password"
                                autocomplete="current-password" required
                                class="min-w-0 flex-1 rounded-l-xl p-3 outline-none">
                            <button type="button" data-password-toggle="customer_current_password"
                                class="customer-password-toggle" aria-label="Show current password"
                                aria-pressed="false"><span data-show-icon><?php echo customerIcon('eye'); ?></span><span data-hide-icon hidden><?php echo customerIcon('eye-off'); ?></span></button>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="bg-blue-50 text-blue-800 p-3 rounded-xl text-sm">You signed in with Google, so your active Google session verifies this password change.</p>
                <?php endif; ?>

                <div>
                    <label for="customer_new_password" class="block text-sm font-semibold mb-1">New Password</label>
                    <div class="flex rounded-xl border bg-white focus-within:ring-2 focus-within:ring-blue-200 focus-within:border-blue-600">
                        <input id="customer_new_password" type="password" name="new_password" minlength="8"
                            autocomplete="new-password" required aria-describedby="customerNewPasswordHelp"
                            class="min-w-0 flex-1 rounded-l-xl p-3 outline-none">
                        <button type="button" data-password-toggle="customer_new_password"
                            class="customer-password-toggle" aria-label="Show new password"
                            aria-pressed="false"><span data-show-icon><?php echo customerIcon('eye'); ?></span><span data-hide-icon hidden><?php echo customerIcon('eye-off'); ?></span></button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1" id="customerNewPasswordHelp">Minimum of 8 characters.</p>
                </div>

                <div>
                    <label for="customer_confirm_password" class="block text-sm font-semibold mb-1">Confirm New Password</label>
                    <div class="flex rounded-xl border bg-white focus-within:ring-2 focus-within:ring-blue-200 focus-within:border-blue-600">
                        <input id="customer_confirm_password" type="password" name="confirm_password" minlength="8"
                            autocomplete="new-password" required
                            class="min-w-0 flex-1 rounded-l-xl p-3 outline-none">
                        <button type="button" data-password-toggle="customer_confirm_password"
                            class="customer-password-toggle" aria-label="Show password confirmation"
                            aria-pressed="false"><span data-show-icon><?php echo customerIcon('eye'); ?></span><span data-hide-icon hidden><?php echo customerIcon('eye-off'); ?></span></button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" data-password-modal-close
                        class="px-5 py-3 rounded-xl font-semibold border text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" name="change_password"
                        class="px-5 py-3 rounded-xl font-semibold bg-blue-700 text-white hover:bg-blue-800">Update Password</button>
                </div>
            </form>
        </section>
    </div>

    <?php if ($valid_id_url !== ''): ?>
        <div class="customer-modal" id="customerValidIdModal" role="dialog" aria-modal="true"
            aria-labelledby="customerValidIdTitle" hidden>
            <section class="customer-modal-panel customer-valid-id-panel bg-white rounded-2xl shadow-2xl p-5 md:p-6"
                tabindex="-1">
                <header class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900" id="customerValidIdTitle">Valid ID</h2>
                        <p class="text-sm text-gray-600 mt-1">Review your uploaded verification document.</p>
                    </div>
                    <button type="button" data-valid-id-modal-close
                        class="w-10 h-10 rounded-full text-gray-600 hover:bg-gray-100 text-2xl leading-none focus:outline-none focus:ring-4 focus:ring-blue-100"
                        aria-label="Close valid ID preview">&times;</button>
                </header>

                <div class="customer-valid-id-preview">
                    <?php if ($valid_id_is_image): ?>
                        <img src="<?php echo $valid_id_url; ?>" alt="Uploaded valid ID preview">
                    <?php elseif ($valid_id_is_pdf): ?>
                        <iframe src="<?php echo $valid_id_url; ?>#toolbar=0" title="Uploaded valid ID PDF preview"></iframe>
                    <?php else: ?>
                        <p class="text-sm text-gray-600 p-5 text-center">Preview is not available for this file type.</p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-4">
                    <a href="<?php echo $valid_id_url; ?>" target="_blank" rel="noopener noreferrer"
                        class="px-5 py-3 rounded-xl font-semibold bg-blue-700 text-white text-center hover:bg-blue-800">
                        Open full document
                    </a>
                    <button type="button" data-valid-id-modal-close
                        class="px-5 py-3 rounded-xl font-semibold border text-gray-700 hover:bg-gray-50">Close</button>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <?php renderCustomerLayoutEnd('profile'); ?>

    <script src="assets/js/location.js"></script>
    <script>
        (function () {
            const editTrigger = document.getElementById('customerEditProfileTrigger');
            const editForm = document.getElementById('customerProfileEditForm');
            const cancelEdit = document.getElementById('customerCancelEditProfile');
            const profileCard = document.querySelector('.customer-profile-summary');
            const viewMode = document.querySelector('[data-profile-view-mode]');
            const photoButton = document.getElementById('customerProfilePictureButton');
            const photoInput = document.getElementById('customerProfilePictureInput');
            const photoPreview = document.querySelector('[data-profile-picture-preview]');
            const photoStatus = document.querySelector('[data-profile-picture-status]');

            if (!editTrigger || !editForm || !cancelEdit) return;

            function openEditor() {
                if (viewMode) viewMode.hidden = true;
                editForm.hidden = false;
                if (profileCard) profileCard.classList.add('is-editing');
                editTrigger.setAttribute('aria-expanded', 'true');
                (profileCard || editForm).scrollIntoView({ behavior: 'smooth', block: 'start' });
                const firstInput = photoButton || editForm.querySelector('input, textarea, button');
                if (firstInput) firstInput.focus({ preventScroll: true });
            }

            function closeEditor() {
                editForm.hidden = true;
                if (viewMode) viewMode.hidden = false;
                if (profileCard) profileCard.classList.remove('is-editing');
                editTrigger.setAttribute('aria-expanded', 'false');
                editTrigger.focus();
            }

            editTrigger.setAttribute('aria-controls', 'customerProfileEditForm');
            editTrigger.setAttribute('aria-expanded', 'false');
            editTrigger.addEventListener('click', openEditor);
            cancelEdit.addEventListener('click', closeEditor);

            if (photoButton && photoInput && photoPreview && photoStatus) {
                photoButton.addEventListener('click', function () {
                    photoInput.click();
                });

                photoInput.addEventListener('change', function () {
                    const file = photoInput.files && photoInput.files[0];
                    if (!file) {
                        photoStatus.textContent = 'JPG, PNG, or WebP only.';
                        return;
                    }

                    photoStatus.textContent = file.name + ' selected. Save profile to apply.';

                    if (file.type && file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.addEventListener('load', function () {
                            let image = photoPreview.querySelector('img');
                            const initials = photoPreview.querySelector('b');
                            if (!image) {
                                image = document.createElement('img');
                                image.alt = 'Selected profile picture preview';
                                photoPreview.prepend(image);
                            }
                            if (initials) initials.hidden = true;
                            image.src = reader.result;
                        });
                        reader.readAsDataURL(file);
                    }
                });
            }
        })();

        (function () {
            const modal = document.getElementById('customerValidIdModal');
            if (!modal) return;

            const panel = modal.querySelector('.customer-modal-panel');
            const triggers = document.querySelectorAll('[data-valid-id-modal-open]');
            let previousFocus = null;

            function focusableElements() {
                return Array.from(modal.querySelectorAll(
                    'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]'
                )).filter(function (element) {
                    return !element.hidden;
                });
            }

            function openModal() {
                previousFocus = document.activeElement;
                modal.hidden = false;
                document.body.classList.add('customer-modal-open');
                window.requestAnimationFrame(function () {
                    modal.classList.add('is-visible');
                    (panel || modal).focus();
                });
            }

            function closeModal() {
                modal.classList.remove('is-visible');
                document.body.classList.remove('customer-modal-open');
                window.setTimeout(function () {
                    modal.hidden = true;
                    if (previousFocus && typeof previousFocus.focus === 'function') {
                        previousFocus.focus();
                    }
                }, 200);
            }

            triggers.forEach(function (trigger) {
                trigger.addEventListener('click', openModal);
            });

            modal.querySelectorAll('[data-valid-id-modal-close]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            modal.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeModal();
                    return;
                }

                if (event.key !== 'Tab') return;
                const focusable = focusableElements();
                if (focusable.length === 0) {
                    event.preventDefault();
                    if (panel) panel.focus();
                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        })();

        (function () {
            const trigger = document.getElementById('customerChangePasswordTrigger');
            const modal = document.getElementById('customerChangePasswordModal');
            const panel = modal ? modal.querySelector('.customer-modal-panel') : null;
            const form = document.getElementById('customerChangePasswordForm');
            const newPassword = document.getElementById('customer_new_password');
            const confirmation = document.getElementById('customer_confirm_password');
            let previousFocus = null;

            if (!trigger || !modal || !panel || !form || !newPassword || !confirmation) {
                return;
            }

            function focusableElements() {
                return Array.from(modal.querySelectorAll(
                    'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]'
                )).filter(function (element) {
                    return !element.hidden;
                });
            }

            function openModal() {
                previousFocus = document.activeElement;
                modal.hidden = false;
                document.body.classList.add('customer-modal-open');
                window.requestAnimationFrame(function () {
                    modal.classList.add('is-visible');
                    const firstInput = modal.querySelector('input[type="password"]');
                    (firstInput || panel).focus();
                });
            }

            function closeModal() {
                modal.classList.remove('is-visible');
                document.body.classList.remove('customer-modal-open');
                window.setTimeout(function () {
                    modal.hidden = true;
                    form.reset();
                    confirmation.setCustomValidity('');
                    if (previousFocus && typeof previousFocus.focus === 'function') {
                        previousFocus.focus();
                    } else {
                        trigger.focus();
                    }
                }, 200);
            }

            function validateConfirmation() {
                confirmation.setCustomValidity(
                    confirmation.value !== newPassword.value ? 'The new passwords do not match.' : ''
                );
            }

            trigger.addEventListener('click', openModal);
            modal.querySelectorAll('[data-password-modal-close]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });

            modal.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const input = document.getElementById(button.dataset.passwordToggle);
                    if (!input) return;
                    const showPassword = input.type === 'password';
                    input.type = showPassword ? 'text' : 'password';
                    const showIcon = button.querySelector('[data-show-icon]');
                    const hideIcon = button.querySelector('[data-hide-icon]');
                    if (showIcon) showIcon.hidden = showPassword;
                    if (hideIcon) hideIcon.hidden = !showPassword;
                    button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                    button.setAttribute('aria-label', (showPassword ? 'Hide ' : 'Show ') + input.name.replaceAll('_', ' '));
                });
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            modal.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeModal();
                    return;
                }

                if (event.key !== 'Tab') return;
                const focusable = focusableElements();
                if (focusable.length === 0) {
                    event.preventDefault();
                    panel.focus();
                    return;
                }

                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            newPassword.addEventListener('input', validateConfirmation);
            confirmation.addEventListener('input', validateConfirmation);
            form.addEventListener('submit', validateConfirmation);

            if (modal.dataset.reopen === 'true') {
                openModal();
            }
        })();
    </script>
</body>

</html>
