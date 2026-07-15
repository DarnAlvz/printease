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
    <section class="admin-card admin-settings-summary">
        <div class="admin-settings-avatar"><?php echo e(adminInitials($admin_name)); ?></div>
        <div>
            <span class="admin-settings-eyebrow">Administrator Account</span>
            <h2><?php echo e($admin_name); ?></h2>
            <p><?php echo e($admin_email); ?></p>
            <div class="admin-settings-badges">
                <span class="admin-status admin-status-info">Super Admin</span>
                <span class="<?php echo adminStatusClass($account_status); ?>"><?php echo e(ucwords(str_replace('_', ' ', $account_status))); ?></span>
                <span class="admin-status admin-status-success"><?php echo $uses_google_session ? 'Google Login' : 'Password Login'; ?></span>
            </div>
        </div>
    </section>

    <section class="admin-card admin-settings-security">
        <div class="admin-settings-section-head">
            <span><?php echo adminIcon('shield'); ?></span>
            <div>
                <h2>Change Password</h2>
                <p>Update the password used for super admin access.</p>
            </div>
        </div>

        <form class="admin-settings-form" action="<?php echo BASE_URL; ?>backend/actions/change_admin_password.php" method="POST">
            <?php echo csrfField(); ?>

            <?php if (!$uses_google_session): ?>
                <label>
                    <span>Current Password</span>
                    <input class="admin-input" type="password" name="current_password" autocomplete="current-password" required>
                </label>
            <?php else: ?>
                <div class="admin-settings-note">You signed in with Google, so your active Google session verifies this password change.</div>
            <?php endif; ?>

            <label>
                <span>New Password</span>
                <input class="admin-input" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            </label>

            <label>
                <span>Confirm New Password</span>
                <input class="admin-input" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            </label>

            <button class="admin-btn" type="submit" name="change_password">
                <?php echo adminIcon('shield'); ?>
                Update Password
            </button>
        </form>
    </section>

    <section class="admin-card admin-settings-notes">
        <h2>Security Notes</h2>
        <div class="admin-settings-note-list">
            <article>
                <strong>Remembered sessions are revoked</strong>
                <p>Changing your password signs out remembered browser sessions for this admin account.</p>
            </article>
            <article>
                <strong>Use a unique password</strong>
                <p>Use at least 8 characters and avoid reusing passwords from other systems.</p>
            </article>
            <article>
                <strong>Activity is audited</strong>
                <p>Password changes are recorded in Activity Logs under Account Security.</p>
            </article>
        </div>
    </section>
</div>

<?php adminLayoutEnd(); ?>
