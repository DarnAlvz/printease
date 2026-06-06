<?php
require_once __DIR__ . "/../../../backend/includes/auth.php";
checkRole("customer");

require_once __DIR__ . "/../../../backend/config/db.php";
require_once __DIR__ . "/../../../backend/config/app.php";
require_once __DIR__ . "/../../../backend/includes/functions.php";
require_once __DIR__ . "/../../../backend/includes/status_guard.php";
require_once __DIR__ . "/../../../backend/includes/profile_guard.php";
requireCompleteCustomerProfile($conn);
requireVerifiedStatus($conn);

#check if the shop is available for orders
$shop_id = isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0;

$shop_sql = "SELECT * FROM print_shops 
             WHERE shop_id = ? 
             AND permit_status = 'verified'
             LIMIT 1";

$shop_stmt = mysqli_prepare($conn, $shop_sql);
mysqli_stmt_bind_param($shop_stmt, "i", $shop_id);
mysqli_stmt_execute($shop_stmt);
$shop_result = mysqli_stmt_get_result($shop_stmt);
$shop = mysqli_fetch_assoc($shop_result);

if (!$shop) {
    setMessage("Invalid or unverified print shop.");
    header("Location: shops.php");
    exit();
}

if (($shop['shop_status'] ?? 'not_accepting') === 'not_accepting') {
    setMessage("This print shop is currently not accepting orders.");
    header("Location: shops.php");
    exit();
}

// Fetch available services for the selected shop
$service_sql = "SELECT * FROM shop_services 
                WHERE shop_id = ? 
                AND is_available = 1
                ORDER BY paper_size ASC, print_type ASC";

$service_stmt = mysqli_prepare($conn, $service_sql);
mysqli_stmt_bind_param($service_stmt, "i", $shop_id);
mysqli_stmt_execute($service_stmt);
$services = mysqli_stmt_get_result($service_stmt);

// Convert services to an array for easier handling in the form
$service_list = [];
while ($row = mysqli_fetch_assoc($services)) {
    $service_list[] = $row;
}

if (empty($service_list)) {
    setMessage("This shop has no available services yet.");
    header("Location: shops.php");
    exit();
}

// Fetch available shops
$shops = mysqli_query($conn, "SELECT * FROM print_shops WHERE permit_status='verified'");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Place Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen pb-24">

    <div class="max-w-md md:max-w-5xl mx-auto min-h-screen">

        <header class="bg-blue-700 text-white p-5 rounded-b-3xl shadow">
            <h1 class="text-2xl font-bold">Place Order</h1>
            <p class="text-sm opacity-90 mt-1">
                Ordering from <?php echo e($shop['shop_name']); ?>
            </p>
        </header>

        <main class="p-4 md:p-6">
            <?php showMessage(); ?>

            <form action="../../../backend/actions/submit_order.php" method="POST" enctype="multipart/form-data" class="bg-white p-5 md:p-6 rounded-2xl shadow grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="shop_id" value="<?php echo e($shop['shop_id']); ?>">
                <input type="hidden" name="service_id" id="service_id">

                <div>
                    <label class="block text-sm font-semibold mb-1">Upload Document</label>
                    <input type="file" name="document_file" required class="w-full border rounded-xl p-3">
                    <p class="text-xs text-gray-500 mt-1">One file per order only.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Paper Size</label>
                    <select id="paper_size" required class="w-full border rounded-xl p-3"></select>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Paper Type</label>
                    <select id="paper_type" required class="w-full border rounded-xl p-3"></select>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Print Type</label>
                    <select id="print_type" required class="w-full border rounded-xl p-3"></select>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Copies</label>
                    <input type="number" name="copies" id="copies" min="1" value="1" required
                        class="w-full border rounded-xl p-3">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Pickup Date and Time</label>
                    <?php
                    date_default_timezone_set('Asia/Manila');
                    $min_pickup = date('Y-m-d\TH:i');
                    ?>

                    <input type="datetime-local" name="pickup_datetime" min="<?php echo $min_pickup; ?>" required
                        class="w-full border rounded-xl p-3">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Instruction</label>
                    <textarea name="customer_instruction" rows="3" placeholder="Example: Please print back-to-back."
                        class="w-full border rounded-xl p-3"></textarea>
                </div>

                <div class="bg-blue-50 p-4 rounded-xl md:col-span-2">
                    <p class="text-sm text-gray-600">Estimated Total</p>
                    <p class="text-3xl font-bold text-blue-700">₱<span id="total">0.00</span></p>
                </div>

                <button type="submit" name="submit_order"
                    class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold md:col-span-2">
                    Submit Order
                </button>
            </form>
        </main>
    </div>

    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t shadow">
        <div class="max-w-md mx-auto grid grid-cols-5 text-center text-xs">
            <a href="dashboard.php" class="py-3 text-gray-600">Home</a>
            <a href="shops.php" class="py-3 text-gray-600">Shops</a>
            <a href="place_order.php" class="py-3 text-blue-700 font-bold">Order</a>
            <a href="orders.php" class="py-3 text-gray-600">Track</a>
            <a href="profile.php" class="py-3 text-gray-600">Profile</a>
        </div>
    </nav>


    <script>
    const services = <?php echo json_encode($service_list); ?>;

    const paperSize = document.getElementById("paper_size");
    const paperType = document.getElementById("paper_type");
    const printType = document.getElementById("print_type");
    const copies = document.getElementById("copies");
    const total = document.getElementById("total");
    const serviceId = document.getElementById("service_id");

    function uniqueValues(key, filter = {}) {
        return [...new Set(services.filter(s =>
            Object.keys(filter).every(k => s[k] === filter[k])
        ).map(s => s[key]))];
    }

    function fillSelect(select, values) {
        select.innerHTML = values.map(v => `<option value="${v}">${v}</option>`).join("");
    }

    function updateAll() {
        fillSelect(paperSize, uniqueValues("paper_size"));
        updatePaperType();
    }

    function updatePaperType() {
        fillSelect(paperType, uniqueValues("paper_type", { paper_size: paperSize.value }));
        updatePrintType();
    }

    function updatePrintType() {
        fillSelect(printType, uniqueValues("print_type", {
            paper_size: paperSize.value,
            paper_type: paperType.value
        }));
        computeTotal();
    }

    function computeTotal() {
        const selected = services.find(s =>
            s.paper_size === paperSize.value &&
            s.paper_type === paperType.value &&
            s.print_type === printType.value
        );

        if (selected) {
            serviceId.value = selected.service_id;
            total.textContent = (parseFloat(selected.price_per_page) * parseInt(copies.value || 1)).toFixed(2);
        }
    }

    paperSize.onchange = updatePaperType;
    paperType.onchange = updatePrintType;
    printType.onchange = computeTotal;
    copies.oninput = computeTotal;

    updateAll();
</script>

</body>

</html>
