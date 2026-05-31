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

if ($shop['shop_status'] !== 'available') {
    setMessage("This print shop is currently not available for orders.");
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

if (mysqli_num_rows($services) == 0) {
    setMessage("This shop has no available services yet.");
    header("Location: shops.php");
    exit();
}

// Fetch available shops
$shops = mysqli_query($conn, "SELECT * FROM print_shops WHERE permit_status='verified'");
?>

<h2>Place Order</h2>

<form action="../../../backend/actions/submit_order.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="shop_id" value="<?php echo e($shop['shop_id']); ?>">
    <input type="hidden" name="service_id" id="service_id">

    <label>Upload Document:</label><br>
    <input type="file" name="document_file" required><br><br>

    <label>Paper Size:</label><br>
    <select id="paper_size" required></select><br><br>

    <label>Paper Type:</label><br>
    <select id="paper_type" required></select><br><br>

    <label>Print Type:</label><br>
    <select id="print_type" required></select><br><br>

    <label>Copies:</label><br>
    <input type="number" name="copies" id="copies" min="1" value="1" required><br><br>

    <label>Pickup Date and Time:</label><br>
    <input type="datetime-local" name="pickup_datetime" required><br><br>

    <label>Instruction:</label><br>
    <textarea name="customer_instruction" placeholder="Example: Please print back-to-back."></textarea><br><br>

    <p><strong>Total:</strong> ₱<span id="total">0.00</span></p>

    <button type="submit" name="submit_order">Submit Order</button>
</form>

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
    fillSelect(paperType, uniqueValues("paper_type", {paper_size: paperSize.value}));
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
