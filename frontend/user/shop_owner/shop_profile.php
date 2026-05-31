<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("shop_owner");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";

$owner_id = $_SESSION['user_id'];

// Fetch current shop profile and permit status
$shop_sql = "SELECT * FROM print_shops WHERE owner_id = ? LIMIT 1";
$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $owner_id);
mysqli_stmt_execute($shop_stmt);
$shop_result = mysqli_stmt_get_result($shop_stmt);
$shop = mysqli_fetch_assoc($shop_result);

$permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : 'incomplete';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Shop Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<h1 class="text-2xl font-bold mb-4">Shop Profile</h1>

<?php showMessage(); ?>

<?php if ($permit_status === 'verified'): ?>
    <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded mb-4">
        <p class="text-green-800 font-semibold">Permit Status: Verified</p>
        <p class="text-green-700">Your business permit is verified. You have full access.</p>
    </div>
<?php elseif ($permit_status === 'pending'): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 p-4 rounded mb-4">
        <p class="text-blue-800 font-semibold">Permit Status: Pending Verification</p>
        <p class="text-blue-700">Your business permit is submitted. Please wait for Super Admin approval.</p>
    </div>
<?php elseif ($permit_status === 'rejected'): ?>
    <div class="bg-red-100 border-l-4 border-red-500 p-4 rounded mb-4">
        <p class="text-red-800 font-semibold">Permit Status: Rejected</p>
        <p class="text-red-700">Your business permit has been rejected. Contact the administrator or upload a new one.</p>
    </div>
<?php endif; ?>

<?php if ($shop): ?>
    <div class="bg-white p-5 rounded shadow mb-6">
        <h2 class="text-xl font-bold"><?php echo e($shop['shop_name']); ?></h2>
        <p>Address: <?php echo e($shop['shop_address']); ?></p>
        <p>Contact: <?php echo e($shop['contact_number']); ?></p>
        <p>Shop Status: <?php echo e($shop['shop_status']); ?></p>
        <p>Permit Status: <strong><?php echo e($shop['permit_status'] ?? 'pending'); ?></strong></p>
        <?php if (!empty($shop['business_permit_file'])): ?>
            <img src="<?php echo PERMITS_URL . e($shop['business_permit_file']); ?>" class="w-64 h-auto border rounded shadow mt-3">
        <?php endif; ?>
    </div>
<?php endif; ?>

<h2 class="text-xl font-bold mb-4"><?php echo $shop ? 'Update' : 'Complete'; ?> Shop Profile</h2>

<form action="../../../backend/actions/save_shop_profile.php" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow max-w-lg">
    <label class="block font-semibold mb-1">Shop Name</label>
    <input type="text" name="shop_name" value="<?php echo e($shop['shop_name'] ?? ''); ?>" placeholder="Shop Name" required class="w-full border rounded px-3 py-2 mb-4">

    <label class="block font-semibold mb-1">Shop Address</label>
    <textarea name="shop_address" placeholder="Shop Address" required class="w-full border rounded px-3 py-2 mb-4"><?php echo e($shop['shop_address'] ?? ''); ?></textarea>

    <label class="block font-semibold mb-1">Contact Number</label>
    <input type="text" name="contact_number" value="<?php echo e($shop['contact_number'] ?? ''); ?>" placeholder="Contact Number" required class="w-full border rounded px-3 py-2 mb-4">

    <label class="block font-semibold mb-1">Shop Status</label>
    <select name="shop_status" required class="w-full border rounded px-3 py-2 mb-4">
        <option value="available" <?php if(($shop['shop_status'] ?? '')=='available') echo 'selected'; ?>>Available</option>
        <option value="busy" <?php if(($shop['shop_status'] ?? '')=='busy') echo 'selected'; ?>>Busy</option>
        <option value="not_accepting" <?php if(($shop['shop_status'] ?? '')=='not_accepting') echo 'selected'; ?>>Not Accepting Orders</option>
    </select>

    <label class="block font-semibold mb-1">Upload Business Permit:</label>
    <input type="file" name="business_permit_file" class="mb-4">
    <?php if (!empty($shop['business_permit_file'])): ?>
        <p class="text-sm text-gray-600 mb-4">Current permit on file. Upload a new one to replace it.</p>
    <?php endif; ?>

    <button type="submit" name="save_profile" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Shop Profile</button>
</form>

<div class="mt-4">
    <a href="dashboard.php" class="text-gray-600 underline">Back to Dashboard</a>
</div>

</body>
</html>
