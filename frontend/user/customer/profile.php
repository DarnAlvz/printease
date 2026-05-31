<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$customer_id = $_SESSION['user_id'];

// Fetch current user info including account status
$stmt = mysqli_prepare($conn, "SELECT phone_number, address, profile_picture, valid_id_file, account_status FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

$account_status = $user['account_status'] ?? 'incomplete';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<h1 class="text-2xl font-bold mb-4">Customer Profile</h1>

<?php showMessage(); ?>

<?php if ($account_status === 'verified'): ?>
    <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded mb-4">
        <p class="text-green-800 font-semibold">Account Status: Verified</p>
        <p class="text-green-700">Your account is verified. You have full access to all features.</p>
    </div>
<?php elseif ($account_status === 'pending'): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 p-4 rounded mb-4">
        <p class="text-blue-800 font-semibold">Account Status: Pending Verification</p>
        <p class="text-blue-700">Your profile is submitted. Please wait for Super Admin approval.</p>
    </div>
<?php elseif ($account_status === 'rejected'): ?>
    <div class="bg-red-100 border-l-4 border-red-500 p-4 rounded mb-4">
        <p class="text-red-800 font-semibold">Account Status: Rejected</p>
        <p class="text-red-700">Your account has been rejected. Contact the administrator.</p>
    </div>
<?php else: ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded mb-4">
        <p class="text-yellow-800 font-semibold">Account Status: Incomplete</p>
        <p class="text-yellow-700">Please complete your profile and upload a valid ID to proceed.</p>
    </div>
<?php endif; ?>

<form action="../../../backend/actions/save_customer_profile.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow max-w-lg">
    <label class="block font-semibold mb-1">Phone Number</label>
    <input type="text" name="phone_number" value="<?php echo e($user['phone_number']); ?>" class="w-full border rounded px-3 py-2 mb-4" required>

    <label class="block font-semibold mb-1">Address</label>
    <textarea name="address" class="w-full border rounded px-3 py-2 mb-4" required><?php echo e($user['address']); ?></textarea>

    <label class="block font-semibold mb-1">Profile Picture</label>
    <input type="file" name="profile_picture" class="mb-2">
    <?php if(!empty($user['profile_picture'])): ?>
        <div class="mb-4">
            <img src="<?php echo BASE_URL . e($user['profile_picture']); ?>" class="w-24 h-24 object-cover rounded border">
        </div>
    <?php endif; ?>

    <label class="block font-semibold mb-1">Valid ID (for Super Admin Verification)</label>
    <input type="file" name="valid_id_file" class="mb-2">
    <?php if(!empty($user['valid_id_file'])): ?>
        <div class="mb-4">
            <a href="<?php echo BASE_URL . e($user['valid_id_file']); ?>" target="_blank" class="text-blue-600 underline">View Current ID</a>
        </div>
    <?php endif; ?>

    <button type="submit" name="save_profile" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Profile</button>
</form>

<div class="mt-4">
    <a href="dashboard.php" class="text-gray-600 underline">Back to Dashboard</a>
</div>

</body>
</html>
