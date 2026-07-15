<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/shop_guard.php";
require_once __DIR__ . "/includes/owner_layout.php";

$owner_access = requireVerifiedStatus($conn, true);
$owner_is_verified = !empty($owner_access['allowed']);
$owner_toast = $owner_is_verified ? null : $owner_access;

$owner_id = $_SESSION['user_id'];

$notif_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0;

$shop_stmt = mysqli_prepare($conn, "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

$sql = "SELECT p.*, o.order_code, u.full_name
        FROM payments p
        JOIN orders o ON p.order_id = o.order_id
        JOIN users u ON p.customer_id = u.user_id
        WHERE o.shop_id = ?
        ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $shop['shop_id']);
mysqli_stmt_execute($stmt);
$payments = mysqli_stmt_get_result($stmt);

ownerLayoutStart('payments', 'Payment Verification', 'Review customer payment proofs.', $notif_count, $shop, $owner_toast);
?>

<div class="owner-card">
    <h2>Customer Payments</h2>

    <?php if (mysqli_num_rows($payments) == 0): ?>
        <p class="muted">No payment proofs yet.</p>
    <?php else: ?>
        <?php while ($payment = mysqli_fetch_assoc($payments)): ?>
            <?php
            $ocr_status = $payment['payment_reference_match'] ?: 'not_detected';
            $ocr_class = match ($ocr_status) {
                'detected' => 'status-success',
                'partial' => 'status-warning',
                'not_detected' => 'status-warning',
                default => 'status-info',
            };
            $proof_url = !empty($payment['proof_of_payment_file']) ? BASE_URL . e($payment['proof_of_payment_file']) : '';
            $proof_ext = strtolower(pathinfo((string) ($payment['proof_of_payment_file'] ?? ''), PATHINFO_EXTENSION));
            ?>
            <div style="border:1px solid #ddd; padding:15px; margin-top:15px; border-radius:12px;">
                <p><strong>Order:</strong> <?php echo e($payment['order_code']); ?></p>
                <p><strong>Customer:</strong> <?php echo e($payment['full_name']); ?></p>
                <p><strong>Amount:</strong> &#8369;<?php echo e(number_format($payment['amount'], 2)); ?></p>
                <p><strong>Detected Reference No.:</strong> <?php echo e($payment['ocr_reference_number'] ?: $payment['reference_number'] ?: 'Not detected'); ?></p>
                <p><strong>Detected Payment Date:</strong> <?php echo !empty($payment['ocr_payment_date']) ? e(date('M d, Y', strtotime($payment['ocr_payment_date']))) : 'Not detected'; ?></p>
                <p><strong>Proof Submitted:</strong> <?php echo !empty($payment['created_at']) ? e(date('M d, Y - g:i A', strtotime($payment['created_at']))) : 'Not available'; ?></p>
                <p><strong>OCR Status:</strong> <span class="status-badge <?php echo e($ocr_class); ?>"><?php echo e(ucwords(str_replace('_', ' ', $ocr_status))); ?></span></p>
                <p><strong>Payment Status:</strong> <?php echo e($payment['payment_status']); ?></p>
                <p><strong>Verification:</strong> <?php echo ($payment['verification_status'] ?? '') === 'pending' ? 'For Verification' : e($payment['verification_status']); ?></p>
                <?php if (($payment['verification_status'] ?? '') === 'rejected'): ?>
                    <p><strong>Rejection Reason:</strong> <?php echo e($payment['rejection_reason'] ?: 'No reason provided.'); ?></p>
                <?php endif; ?>

                <?php if (!empty($payment['proof_of_payment_file'])): ?>
                    <div style="margin-top:12px;">
                        <p><strong>Proof of Payment:</strong></p>
                        <?php if (in_array($proof_ext, ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true)): ?>
                            <a href="<?php echo $proof_url; ?>" target="_blank">
                                <img src="<?php echo $proof_url; ?>" alt="Payment proof for <?php echo e($payment['order_code']); ?>"
                                    style="max-width:320px; width:100%; max-height:360px; object-fit:contain; border:1px solid #ddd; border-radius:12px; margin-top:8px;">
                            </a>
                        <?php else: ?>
                            <a href="<?php echo $proof_url; ?>" target="_blank">View Proof</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($owner_is_verified && ($payment['verification_status'] ?? '') === 'pending'): ?>
                    <form action="<?php echo BASE_URL; ?>backend/actions/verify_payment.php" method="POST" style="margin-top:12px;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="payment_id" value="<?php echo e($payment['payment_id']); ?>">

                        <button type="submit" name="verify_payment" class="btn btn-primary">
                            Mark as Paid
                        </button>
                    </form>

                    <form action="<?php echo BASE_URL; ?>backend/actions/verify_payment.php" method="POST" style="margin-top:12px;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="payment_id" value="<?php echo e($payment['payment_id']); ?>">
                        <label for="payment-rejection-<?php echo e($payment['payment_id']); ?>" style="display:block; font-weight:600; margin-bottom:6px;">
                            Reason for rejection
                        </label>
                        <textarea id="payment-rejection-<?php echo e($payment['payment_id']); ?>" name="rejection_reason"
                            placeholder="Tell the customer what needs to be corrected" maxlength="500" required
                            style="width:100%; max-width:520px; min-height:90px;"></textarea><br>

                        <button type="submit" name="reject_payment" class="btn btn-danger">
                            Reject Payment
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php ownerLayoutEnd(); ?>
