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
requireVerifiedStatus($conn);

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
                        AND (
                            o.order_code LIKE ?
                            OR u.full_name LIKE ?
                            OR u.email LIKE ?
                            OR p.payment_method LIKE ?
                            OR p.payment_status LIKE ?
                        )
                        ORDER BY o.created_at DESC";
    $transaction_stmt = mysqli_prepare($conn, $transaction_sql);
    $like = "%$search%";
    mysqli_stmt_bind_param($transaction_stmt, "isssss", $shop_id, $like, $like, $like, $like, $like);
} else {
    $transaction_sql = "SELECT p.*, o.order_code, o.paper_size, o.paper_type, o.print_type, o.copies,
                               o.total_amount, o.order_status, o.created_at AS order_created_at,
                               u.full_name, u.email
                        FROM payments p
                        JOIN orders o ON p.order_id = o.order_id
                        JOIN users u ON p.customer_id = u.user_id
                        WHERE o.shop_id = ?
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

ownerLayoutStart('transactions', 'Transactions', 'Track paid order transactions for faster sales reporting.', $notif_count, $shop);
?>

<section class="summary-grid" style="margin-bottom:24px;">
    <article class="metric-card accent-card">
        <div class="metric-head">
            <span class="metric-icon"><?php echo ownerIcon('badge-dollar-sign', 'icon'); ?></span>
            <span class="status-badge status-success">Paid</span>
        </div>
        <strong><?php echo ownerMoney($summary['total_revenue']); ?></strong>
        <p>Total Paid Revenue</p>
    </article>
    <article class="metric-card">
        <div class="metric-head">
            <span>Total Paid Orders</span>
            <span class="metric-icon"><?php echo ownerIcon('receipt-text', 'icon'); ?></span>
        </div>
        <strong><?php echo (int) $summary['total_transactions']; ?></strong>
        <p class="card-note">Payment records</p>
    </article>
    <article class="metric-card">
        <div class="metric-head">
            <span>Average Transaction</span>
            <span class="metric-icon"><?php echo ownerIcon('chart-line', 'icon'); ?></span>
        </div>
        <strong><?php echo ownerMoney($summary['average_transaction']); ?></strong>
        <p class="card-note">
            Latest:
            <?php echo !empty($summary['latest_payment']) ? e(date("M d, Y", strtotime($summary['latest_payment']))) : 'No payments yet'; ?>
        </p>
    </article>
</section>

<section class="filter-card" style="margin-bottom:24px;">
    <form method="GET">
        <div class="search-row">
            <input type="text" name="q" placeholder="Search by order code, customer, method, or status" value="<?php echo e($search); ?>">
            <button type="submit" class="btn btn-primary"><?php echo ownerIcon('search', 'icon'); ?>Search</button>
            <a href="transactions.php" class="btn btn-soft"><?php echo ownerIcon('x', 'icon'); ?>Clear</a>
        </div>
    </form>
</section>

<?php if (empty($transactions)): ?>
    <section class="owner-card empty-state">
        <h2>No transactions found</h2>
        <p>Paid customer orders will appear here once payments are recorded.</p>
    </section>
<?php else: ?>
    <section class="table-card">
        <div class="owner-table-wrap">
            <table class="owner-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Print Details</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <?php $payment_date = $transaction['created_at'] ?? $transaction['order_created_at'] ?? ''; ?>
                        <tr>
                            <td>
                                <strong><?php echo e($transaction['order_code']); ?></strong><br>
                                <span class="muted">Order #<?php echo e($transaction['order_id']); ?></span>
                            </td>
                            <td>
                                <strong><?php echo e($transaction['full_name']); ?></strong><br>
                                <span class="muted"><?php echo e($transaction['email']); ?></span>
                            </td>
                            <td>
                                <span class="chip"><?php echo e($transaction['paper_size']); ?></span>
                                <span class="chip"><?php echo e($transaction['paper_type']); ?></span>
                                <span class="chip"><?php echo e($transaction['print_type']); ?></span>
                                <span class="chip">x<?php echo e($transaction['copies']); ?></span>
                            </td>
                            <td><?php echo e(strtoupper($transaction['payment_method'] ?? 'GCash')); ?></td>
                            <td><strong><?php echo ownerMoney($transaction['amount']); ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo ownerStatusClass($transaction['payment_status']); ?>">
                                    <?php echo e(ownerStatusLabel($transaction['payment_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($payment_date)): ?>
                                    <?php echo e(date("M d, Y", strtotime($payment_date))); ?><br>
                                    <span class="muted"><?php echo e(date("g:i A", strtotime($payment_date))); ?></span>
                                <?php else: ?>
                                    <span class="muted">Not available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="order-mobile-list">
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
                    <p><strong>Date:</strong> <?php echo !empty($payment_date) ? e(date("M d, Y - g:i A", strtotime($payment_date))) : 'Not available'; ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php ownerLayoutEnd(); ?>
