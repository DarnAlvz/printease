<?php
include "../../../backend/includes/auth.php";
checkRole("customer");

include "../../../backend/config/db.php";
include "../../../backend/config/app.php";
include "../../../backend/includes/functions.php";
include "../../../backend/includes/status_guard.php";
requireVerifiedStatus($conn);

// Fetch available shops
$shops = mysqli_query($conn, "SELECT * FROM print_shops WHERE permit_status='verified'");
?>

<h2>Place Order</h2>

<form action="../../../backend/actions/place_order_process.php" method="POST" enctype="multipart/form-data">
    <select name="shop_id" required>
        <?php while($shop = mysqli_fetch_assoc($shops)): ?>
            <option value="<?php echo $shop['shop_id']; ?>"><?php echo e($shop['shop_name']); ?></option>
        <?php endwhile; ?>
    </select><br><br>

    <input type="text" name="paper_size" placeholder="Paper Size" required><br><br>
    <input type="text" name="print_type" placeholder="Print Type" required><br><br>
    <input type="number" name="copies" placeholder="Copies" required><br><br>
    <input type="number" name="total_amount" placeholder="Total Amount" required><br><br>
    
    <label>Upload File</label>
    <input type="file" name="order_file" required><br><br>

    <button type="submit" name="place_order">Place Order</button>
</form>