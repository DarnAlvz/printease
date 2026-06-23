<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/includes/owner_layout.php";

requireCompleteShopProfile($conn);
$owner_access = requireVerifiedStatus($conn, true);
$owner_is_verified = !empty($owner_access['allowed']);
$owner_toast = $owner_is_verified ? null : $owner_access;

$owner_id = $_SESSION['user_id'];
$search = trim($_GET['q'] ?? '');

$notif_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = mysqli_prepare($conn, $notif_sql);
mysqli_stmt_bind_param($notif_stmt, "i", $owner_id);
mysqli_stmt_execute($notif_stmt);
$notif_count = (mysqli_fetch_assoc(mysqli_stmt_get_result($notif_stmt))['total'] ?? 0);

$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop = mysqli_fetch_assoc(mysqli_stmt_get_result($shop_stmt));

if (!$shop) {
    die("Please complete your shop profile first.");
}

$shop_id = $shop['shop_id'];

$summary_sql = "SELECT
                    COUNT(*) AS total_transactions,
                    COALESCE(SUM(p.amount), 0) AS total_revenue,
                    COALESCE(AVG(p.amount), 0) AS average_transaction,
                    MAX(o.created_at) AS latest_payment
                FROM payments p
                JOIN orders o ON p.order_id = o.order_id
                WHERE o.shop_id = ? AND p.payment_status = 'paid'";
$summary_stmt = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($summary_stmt, "i", $shop_id);
mysqli_stmt_execute($summary_stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summary_stmt)) ?: [
    'total_transactions' => 0,
    'total_revenue' => 0,
    'average_transaction' => 0,
    'latest_payment' => null,
];

if ($search !== '') {
    $transaction_sql = "SELECT p.*, o.order_code, o.paper_size, o.paper_type, o.print_type, o.copies,
                               o.total_amount, o.order_status, o.created_at AS order_created_at,
                               u.full_name, u.email
                        FROM payments p
                        JOIN orders o ON p.order_id = o.order_id
                        JOIN users u ON p.customer_id = u.user_id
                        WHERE o.shop_id = ?
                        AND p.payment_status = 'paid'
                        AND (
                            LOWER(o.order_code) LIKE ?
                            OR LOWER(u.full_name) LIKE ?
                            OR LOWER(u.email) LIKE ?
                            OR LOWER(p.payment_method) LIKE ?
                        )
                        ORDER BY o.created_at DESC";
    $transaction_stmt = mysqli_prepare($conn, $transaction_sql);
    $like = '%' . strtolower($search) . '%';
    mysqli_stmt_bind_param($transaction_stmt, "issss", $shop_id, $like, $like, $like, $like);
} else {
    $transaction_sql = "SELECT p.*, o.order_code, o.paper_size, o.paper_type, o.print_type, o.copies,
                               o.total_amount, o.order_status, o.created_at AS order_created_at,
                               u.full_name, u.email
                        FROM payments p
                        JOIN orders o ON p.order_id = o.order_id
                        JOIN users u ON p.customer_id = u.user_id
                        WHERE o.shop_id = ?
                        AND p.payment_status = 'paid'
                        ORDER BY o.created_at DESC";
    $transaction_stmt = mysqli_prepare($conn, $transaction_sql);
    mysqli_stmt_bind_param($transaction_stmt, "i", $shop_id);
}

mysqli_stmt_execute($transaction_stmt);
$transaction_result = mysqli_stmt_get_result($transaction_stmt);
$transactions = [];
while ($transaction = mysqli_fetch_assoc($transaction_result)) {
    $transactions[] = $transaction;
}

ownerLayoutStart('transactions', 'Transactions', '', $notif_count, $shop, $owner_toast);
?>

<section class="transactions-ui">
    <div class="transactions-prepaid-pill">
        <?php echo ownerIcon('circle-check', 'icon-sm'); ?>
        <span>All orders are prepaid via GCash</span>
    </div>

    <section class="transactions-stat-grid" aria-label="Transaction summary">
        <article class="transactions-stat-card">
            <span class="transactions-stat-icon revenue"><?php echo ownerIcon('badge-dollar-sign', 'icon'); ?></span>
            <div>
                <p>Total Revenue</p>
                <strong><?php echo ownerMoney($summary['total_revenue']); ?></strong>
            </div>
        </article>
        <article class="transactions-stat-card">
            <span class="transactions-stat-icon paid"><?php echo ownerIcon('circle-check', 'icon'); ?></span>
            <div>
                <p>Total Paid Orders</p>
                <strong><?php echo (int) $summary['total_transactions']; ?></strong>
            </div>
        </article>
        <article class="transactions-stat-card">
            <span class="transactions-stat-icon processing"><?php echo ownerIcon('clock', 'icon'); ?></span>
            <div>
                <p>Average Transaction</p>
                <strong><?php echo ownerMoney($summary['average_transaction']); ?></strong>
            </div>
        </article>
    </section>

    <section class="transactions-filter-card">
        <form method="GET" class="transactions-search-form" data-live-search-form data-live-target="owner_transactions" data-live-min="1">
            <label class="transactions-search-box">
                <?php echo ownerIcon('search', 'icon'); ?>
                <input type="text" name="q" placeholder="Search by order code, customer, or payment method" value="<?php echo e($search); ?>">
            </label>
            <button type="submit" class="transactions-submit-hidden">Search</button>
            <?php if ($search !== ''): ?>
                <a href="transactions.php" class="transactions-clear-link" aria-label="Clear search"><?php echo ownerIcon('x', 'icon-sm'); ?></a>
            <?php endif; ?>
        </form>
    </section>

    <?php if (empty($transactions)): ?>
        <section class="owner-card empty-state transactions-empty-state" data-live-region="owner-transaction-results">
            <h2>No transactions found</h2>
            <p>Paid customer orders will appear here once payments are recorded.</p>
        </section>
    <?php else: ?>
        <section class="transactions-table-card" data-live-region="owner-transaction-results">
            <div class="owner-table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Order Code</th>
                            <th>Customer</th>
                            <th>Print Details</th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Date Paid</th>
                            <th>Payment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <?php $payment_date = $transaction['created_at'] ?? $transaction['order_created_at'] ?? ''; ?>
                            <tr>
                                <td><strong class="transactions-order-code"><?php echo e($transaction['order_code']); ?></strong></td>
                                <td>
                                    <span class="transactions-customer">
                                        <?php echo ownerIcon('circle-check', 'icon-sm'); ?>
                                        <strong><?php echo e($transaction['full_name']); ?></strong>
                                    </span>
                                    <span class="transactions-subtext"><?php echo e($transaction['email']); ?></span>
                                </td>
                                <td>
                                    <div class="transactions-chip-row">
                                        <span><?php echo e($transaction['paper_size']); ?></span>
                                        <span><?php echo e($transaction['paper_type']); ?></span>
                                        <span><?php echo e($transaction['print_type']); ?></span>
                                        <span>x<?php echo e($transaction['copies']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="transactions-method-chip"><?php echo e(strtoupper($transaction['payment_method'] ?? 'GCash')); ?></span>
                                </td>
                                <td><strong class="transactions-amount"><?php echo ownerMoney($transaction['amount']); ?></strong></td>
                                <td>
                                    <?php if (!empty($payment_date)): ?>
                                        <?php echo e(date("Y-m-d", strtotime($payment_date))); ?>
                                    <?php else: ?>
                                        <span class="muted">Not available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge transactions-status <?php echo ownerStatusClass($transaction['payment_status']); ?>">
                                        <?php echo e(ownerStatusLabel($transaction['payment_status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="order-mobile-list transactions-mobile-list">
                <?php foreach ($transactions as $transaction): ?>
                    <?php $payment_date = $transaction['created_at'] ?? $transaction['order_created_at'] ?? ''; ?>
                    <article class="owner-card order-card-mobile">
                        <div class="card-head">
                            <h2><?php echo e($transaction['order_code']); ?></h2>
                            <span class="status-badge <?php echo ownerStatusClass($transaction['payment_status']); ?>">
                                <?php echo e(ownerStatusLabel($transaction['payment_status'])); ?>
                            </span>
                        </div>
                        <p><strong>Customer:</strong> <?php echo e($transaction['full_name']); ?></p>
                        <p><strong>Amount:</strong> <?php echo ownerMoney($transaction['amount']); ?></p>
                        <p><strong>Method:</strong> <?php echo e(strtoupper($transaction['payment_method'] ?? 'GCash')); ?></p>
                        <p><strong>Details:</strong> <?php echo e($transaction['paper_size']); ?>, <?php echo e($transaction['paper_type']); ?>, <?php echo e($transaction['print_type']); ?>, x<?php echo e($transaction['copies']); ?></p>
                        <p><strong>Date:</strong> <?php echo !empty($payment_date) ? e(date("Y-m-d", strtotime($payment_date))) : 'Not available'; ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="transactions-total-bar" aria-label="Transaction totals" data-live-region="owner-transaction-totals">
        <div>
            <span>Total Orders</span>
            <strong><?php echo (int) $summary['total_transactions']; ?></strong>
        </div>
        <div>
            <span>Total Amount</span>
            <strong><?php echo ownerMoney($summary['total_revenue']); ?></strong>
        </div>
        <div>
            <span>Average Order</span>
            <strong><?php echo ownerMoney($summary['average_transaction']); ?></strong>
        </div>
    </section>
</section>

<?php ownerLayoutEnd(); ?>
