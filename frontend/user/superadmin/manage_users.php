<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("super_admin");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$sql = "SELECT u.*, ps.business_permit_file, ps.permit_status 
        FROM users u 
        LEFT JOIN print_shops ps ON u.user_id = ps.owner_id 
        WHERE u.role != 'super_admin' 
        ORDER BY u.created_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

    <h1 class="text-2xl font-bold mb-4">Manage Users</h1>
    <?php showMessage(); ?>

    <div class="bg-white p-5 rounded shadow overflow-x-auto">
        <table class="w-full border-collapse border">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2 text-left">Name</th>
                    <th class="border p-2 text-left">Email</th>
                    <th class="border p-2 text-left">Role</th>
                    <th class="border p-2 text-left">Status</th>
                    <th class="border p-2 text-left">Documents</th>
                    <th class="border p-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($result)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="border p-2"><?php echo e($user['full_name']); ?></td>
                        <td class="border p-2"><?php echo e($user['email']); ?></td>
                        <td class="border p-2"><?php echo e(ucfirst(str_replace('_', ' ', $user['role']))); ?></td>
                        <td class="border p-2">
                            <?php
                            $status = $user['account_status'] ?? 'pending';
                            $badge_color = match ($status) {
                                'verified' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-blue-100 text-blue-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800'
                            };
                            ?>
                            <span class="px-2 py-1 rounded text-sm font-medium <?php echo $badge_color; ?>">
                                <?php echo e(ucfirst($status)); ?>
                            </span>
                        </td>
                        <td class="border p-2">
                            <?php if ($user['role'] === 'customer' && !empty($user['valid_id_file'])): ?>
                                <a href="<?php echo BASE_URL . e($user['valid_id_file']); ?>" target="_blank"
                                    class="text-blue-600 underline text-sm">View Valid ID</a>
                            <?php elseif ($user['role'] === 'shop_owner'): ?>
                                <?php if (!empty($user['business_permit_file'])): ?>
                                    <a href="<?php echo PERMITS_URL . e($user['business_permit_file']); ?>" target="_blank"
                                        class="text-blue-600 underline text-sm">View Permit</a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">No permit uploaded</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm">No documents</span>
                            <?php endif; ?>
                        </td>
                        <td class="border p-2">
                            <form action="<?php echo BASE_URL; ?>backend/actions/update_user_status.php" method="POST"
                                class="flex items-center gap-2">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">

                                <select name="account_status" class="border rounded px-2 py-1 text-sm">
                                    <option value="pending" <?php if ($status === 'pending')
                                        echo 'selected'; ?>>Pending
                                    </option>
                                    <option value="verified" <?php if ($status === 'verified')
                                        echo 'selected'; ?>>Verified
                                    </option>
                                    <option value="rejected" <?php if ($status === 'rejected')
                                        echo 'selected'; ?>>Rejected
                                    </option>
                                </select>

                                <button type="submit" name="update_user_status"
                                    class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <a href="dashboard.php" class="bg-gray-700 text-white px-4 py-2 rounded">Back to Dashboard</a>
    </div>

</body>

</html>
