<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/includes/admin_layout.php";

$admin_id = (int) ($_SESSION['user_id'] ?? 0);
$admin_name = (string) ($_SESSION['full_name'] ?? 'Super Admin');
$admin_email = (string) ($_SESSION['email'] ?? 'admin@printease.local');
$auth_provider = (string) ($_SESSION['auth_provider'] ?? 'password');
$uses_google_session = $auth_provider === 'google';
$account_status = 'verified';

$stmt = mysqli_prepare($conn, "SELECT full_name, email, account_status FROM users WHERE user_id = ? AND role = 'super_admin' LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $admin_id);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($admin) {
        $admin_name = (string) ($admin['full_name'] ?? $admin_name);
        $admin_email = (string) ($admin['email'] ?? $admin_email);
        $account_status = (string) ($admin['account_status'] ?? $account_status);
    }
}

adminLayoutStart('settings', 'Settings', 'Manage your administrator account and security.');

?>

<div class="admin-settings-grid">
    <!-- LEFT COLUMN: Profile + Security Notes -->
    <div class="admin-settings-col-left">
        <section class="admin-card admin-settings-profile">
            <div class="admin-settings-profile-banner">
                <div class="admin-settings-avatar"><?php echo e(adminInitials($admin_name)); ?></div>
            </div>
            <div class="admin-settings-profile-body">
                <span class="admin-settings-eyebrow">Administrator Account</span>
                <h2><?php echo e($admin_name); ?></h2>
                <p class="admin-settings-email"><?php echo e($admin_email); ?></p>
                <div class="admin-settings-badges">
                    <span class="admin-status admin-status-info">Super Admin</span>
                    <span class="<?php echo adminStatusClass($account_status); ?>"><?php echo e(ucwords(str_replace('_', ' ', $account_status))); ?></span>
                    <span class="admin-status admin-status-success"><?php echo $uses_google_session ? 'Google Login' : 'Password Login'; ?></span>
                </div>
            </div>
            <div class="admin-settings-meta">
                <div class="admin-settings-meta-item">
                    <span class="admin-settings-meta-icon"><?php echo adminIcon('shield'); ?></span>
                    <div>
                        <strong>Security Status</strong>
                        <span>Audited & Monitored</span>
                    </div>
                </div>
                <div class="admin-settings-meta-item">
                    <span class="admin-settings-meta-icon"><?php echo adminIcon('users'); ?></span>
                    <div>
                        <strong>Role</strong>
                        <span>Super Administrator</span>
                    </div>
                </div>
                <div class="admin-settings-meta-item">
                    <span class="admin-settings-meta-icon"><?php echo adminIcon('activity'); ?></span>
                    <div>
                        <strong>Auth Type</strong>
                        <span><?php echo $uses_google_session ? 'Google OAuth' : 'Email & Password'; ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-card admin-settings-notes">
            <div class="admin-settings-notes-header">
                <span class="admin-settings-notes-icon"><?php echo adminIcon('shield'); ?></span>
                <h2>Security Notes</h2>
            </div>
            <div class="admin-settings-note-list">
                <article>
                    <div class="admin-settings-note-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                    </div>
                    <div>
                        <strong>Remembered sessions are revoked</strong>
                        <p>Changing your password signs out remembered browser sessions for this admin account.</p>
                    </div>
                </article>
                <article>
                    <div class="admin-settings-note-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                    <div>
                        <strong>Use a unique password</strong>
                        <p>Use at least 8 characters and avoid reusing passwords from other systems.</p>
                    </div>
                </article>
                <article>
                    <div class="admin-settings-note-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 8L9 4l-3 8H2"/></svg>
                    </div>
                    <div>
                        <strong>Activity is audited</strong>
                        <p>Password changes are recorded in Activity Logs under Account Security.</p>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <!-- RIGHT COLUMN: Change Password -->
    <div class="admin-settings-col-right">
        <section class="admin-card admin-settings-security">
            <div class="admin-settings-section-head">
                <span><?php echo adminIcon('shield'); ?></span>
                <div>
                    <h2>Change Password</h2>
                    <p>Update the password used for super admin access.</p>
                </div>
            </div>

            <form class="admin-settings-form" id="adminPasswordForm" action="<?php echo BASE_URL; ?>backend/actions/change_admin_password.php" method="POST">
                <?php echo csrfField(); ?>

                <?php if (!$uses_google_session): ?>
                    <label>
                        <span>Current Password</span>
                        <div class="admin-input-wrap">
                            <svg class="admin-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input class="admin-input" type="password" name="current_password" autocomplete="current-password" required>
                            <button type="button" class="admin-pw-toggle" aria-label="Toggle password visibility" tabindex="-1">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M1 1l22 22"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                            </button>
                        </div>
                    </label>
                <?php else: ?>
                    <div class="admin-settings-google-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        <span>You signed in with Google, so your active Google session verifies this password change.</span>
                    </div>
                <?php endif; ?>

                <label>
                    <span>New Password</span>
                    <div class="admin-input-wrap">
                        <svg class="admin-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="admin-input" type="password" name="new_password" id="adminNewPassword" minlength="8" autocomplete="new-password" required>
                        <button type="button" class="admin-pw-toggle" aria-label="Toggle password visibility" tabindex="-1">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M1 1l22 22"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                        </button>
                    </div>
                </label>

                <!-- Password Requirements -->
                <div class="admin-pw-requirements" id="adminPwRequirements">
                    <span class="admin-pw-req-title">Password must have:</span>
                    <ul>
                        <li id="pwReqLength"><span class="admin-pw-req-dot"></span>At least 8 characters</li>
                        <li id="pwReqUpper"><span class="admin-pw-req-dot"></span>One uppercase letter</li>
                        <li id="pwReqNumber"><span class="admin-pw-req-dot"></span>One number</li>
                    </ul>
                </div>

                <label>
                    <span>Confirm New Password</span>
                    <div class="admin-input-wrap">
                        <svg class="admin-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="admin-input" type="password" name="confirm_password" id="adminConfirmPassword" minlength="8" autocomplete="new-password" required>
                        <button type="button" class="admin-pw-toggle" aria-label="Toggle password visibility" tabindex="-1">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M1 1l22 22"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>
                        </button>
                    </div>
                </label>

                <div class="admin-pw-match-msg" id="adminPwMatchMsg"></div>

                <button class="admin-btn admin-btn-update-pw" type="submit" name="change_password">
                    <?php echo adminIcon('shield'); ?>
                    Update Password
                </button>
            </form>
        </section>
    </div>
</div>

<script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
(function () {
    // Password visibility toggles
    document.querySelectorAll('.admin-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = this.closest('.admin-input-wrap').querySelector('input');
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            this.classList.toggle('is-visible', isPassword);
        });
    });

    // Password requirements checker
    var newPw = document.getElementById('adminNewPassword');
    var confirmPw = document.getElementById('adminConfirmPassword');
    var matchMsg = document.getElementById('adminPwMatchMsg');
    var reqLength = document.getElementById('pwReqLength');
    var reqUpper = document.getElementById('pwReqUpper');
    var reqNumber = document.getElementById('pwReqNumber');

    function checkReqs() {
        var val = newPw ? newPw.value : '';
        setReqState(reqLength, val.length >= 8);
        setReqState(reqUpper, /[A-Z]/.test(val));
        setReqState(reqNumber, /[0-9]/.test(val));
        checkMatch();
    }

    function setReqState(el, ok) {
        if (!el) return;
        el.classList.toggle('is-met', ok);
        el.classList.toggle('is-unmet', !ok && el.closest('.admin-pw-requirements').classList.contains('is-active'));
    }

    function checkMatch() {
        if (!confirmPw || !matchMsg) return;
        var cv = confirmPw.value;
        var nv = newPw ? newPw.value : '';
        if (cv.length === 0) {
            matchMsg.textContent = '';
            matchMsg.className = 'admin-pw-match-msg';
        } else if (cv === nv) {
            matchMsg.textContent = '✓ Passwords match';
            matchMsg.className = 'admin-pw-match-msg is-match';
        } else {
            matchMsg.textContent = '✗ Passwords do not match';
            matchMsg.className = 'admin-pw-match-msg is-mismatch';
        }
    }

    if (newPw) {
        newPw.addEventListener('input', function () {
            document.getElementById('adminPwRequirements').classList.add('is-active');
            checkReqs();
        });
    }
    if (confirmPw) {
        confirmPw.addEventListener('input', checkMatch);
    }
})();
</script>

<?php adminLayoutEnd(); ?>
