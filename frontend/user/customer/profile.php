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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

    <div class="max-w-md md:max-w-5xl mx-auto min-h-screen">
        <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
            <h1 class="text-2xl font-bold">Customer Profile</h1>
            <p class="text-sm opacity-90 mt-1">Manage your account details and verification files.</p>
        </header>

        <main class="p-4 md:p-6">
            <?php showMessage(); ?>

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

            <form action="../../../backend/actions/save_customer_profile.php" method="POST"
                enctype="multipart/form-data"
                class="bg-white p-5 md:p-6 rounded-2xl shadow grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-semibold mb-1">Phone Number</label>
                    <input type="text" name="phone_number" value="<?php echo e($user['phone_number']); ?>"
                        class="w-full border rounded-xl p-3" required>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Profile Picture</label>
                    <input type="file" name="profile_picture" class="w-full border rounded-xl p-3">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-1">Address</label>

                    <textarea id="address" name="address" rows="3" class="w-full border rounded-xl p-3"
                        placeholder="Click Use My Current Location or type your complete address"
                        required><?php echo e($user['address']); ?></textarea>

                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">

                    <button type="button" onclick="useCurrentLocation()"
                        class="mt-2 bg-green-600 text-white py-2 px-4 rounded-xl font-semibold">
                        Use My Current Location
                    </button>

                    <p id="locationStatus" class="text-sm text-gray-500 mt-2"></p>
                </div>

                <?php if (!empty($user['profile_picture'])): ?>
                    <div>
                        <p class="text-sm font-semibold mb-2">Current Profile Picture</p>
                        <img src="<?php echo BASE_URL . e($user['profile_picture']); ?>"
                            class="w-24 h-24 object-cover rounded-xl border">
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-semibold mb-1">Valid ID</label>
                    <input type="file" name="valid_id_file" class="w-full border rounded-xl p-3">
                    <?php if (!empty($user['valid_id_file'])): ?>
                        <a href="<?php echo BASE_URL . e($user['valid_id_file']); ?>" target="_blank"
                            class="inline-block mt-2 text-blue-600 font-semibold">
                            View Current ID
                        </a>
                    <?php endif; ?>
                </div>

                <button type="submit" name="save_profile"
                    class="md:col-span-2 w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">
                    Save Profile
                </button>
            </form>
        </main>
    </div>

    <nav
        class="fixed bottom-0 left-0 right-0 bg-white border-t shadow md:static md:shadow-none md:border md:rounded-2xl md:mt-6">
        <div class="max-w-md md:max-w-6xl mx-auto grid grid-cols-5 text-center text-xs">
            <a href="dashboard.php" class="py-3 text-gray-600">Home</a>
            <a href="shops.php" class="py-3 text-blue-700 font-bold">Shops</a>
            <a href="shopLocation.php" class="py-3 text-gray-600">Map</a>
            <a href="orders.php" class="py-3 text-gray-600">Track</a>
            <a href="profile.php" class="py-3 text-gray-600">Profile</a>
        </div>
    </nav>

    <script src="assets/js/location.js"></script>
</body>

</html>