<?php
function shopOwnerVerificationResult($conn)
{
    $user_id = $_SESSION['user_id'] ?? 0;

    $sql = "SELECT permit_status FROM print_shops WHERE owner_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $shop = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $permit_status = $shop ? ($shop['permit_status'] ?? 'pending') : null;

    if (!$shop || $permit_status === null) {
        return [
            'allowed' => false,
            'status' => 'incomplete',
            'message' => 'Please complete your shop profile before accessing this feature.',
            'redirect' => '../../frontend/user/shop_owner/shop_profile.php',
        ];
    }

    if ($permit_status === 'verified') {
        return [
            'allowed' => true,
            'status' => $permit_status,
            'message' => '',
            'redirect' => '',
        ];
    }

    return [
        'allowed' => false,
        'status' => $permit_status,
        'message' => $permit_status === 'pending'
            ? 'Your business permit is pending verification by the Admin.'
            : 'Your business permit has been rejected. Please contact the administrator.',
        'redirect' => '',
    ];
}

function requireVerifiedStatus($conn, $soft = false)
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../frontend/pages/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    if ($role === 'super_admin')
        return true;

    // ------------------ CUSTOMER ------------------
    if ($role === 'customer') {
        $sql = "SELECT account_status FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$user)
            die("User not found.");
        $status = $user['account_status'] ?? 'pending';

        if ($status === 'verified')
            return true;

        showToast(
            $status === 'pending'
            ? "Your account is pending verification by the Admin."
            : "Your account has been rejected. Please contact support.",
            $status,
            "dashboard.php"
        );
    }

    // ------------------ SHOP OWNER ------------------
    if ($role === 'shop_owner') {
        $verification = shopOwnerVerificationResult($conn);

        if ($soft) {
            if ($verification['redirect'] !== '') {
                $_SESSION['message'] = $verification['message'];
                header("Location: " . $verification['redirect']);
                exit();
            }

            return $verification;
        }

        if ($verification['redirect'] !== '') {
            $_SESSION['message'] = $verification['message'];
            header("Location: " . $verification['redirect']);
            exit();
        }

        if ($verification['allowed'])
            return true;

        return showToast(
            $verification['message'],
            $verification['status'],
            "dashboard.php"
        );
    }

    die("Access denied.");
}

// ------------------ TOAST FUNCTION ------------------

function showToast($message, $type = 'pending', $redirect = '')
{
    ?>
    <!-- Toast container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none"></div>

    <style>
        /* Toast base style */
        .toast {
            min-width: 220px;
            max-width: 380px;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.4s ease;
            pointer-events: auto;
        }

        /* Toast type colors */
        .toast.pending {
            background: linear-gradient(90deg,#facc15,#fbbf24);
            color: #5a3d00;
        }
        .toast.rejected {
            background: linear-gradient(90deg,#ef4444,#dc2626);
            color: #ffffff;
        }

        /* Fade in/out animation */
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>

    <script>
    (function () {
        const container = document.getElementById("toast-container");
        if (!container) return;

        // Create toast
        const toast = document.createElement("div");
        toast.className = "toast <?php echo $type; ?>";
        toast.innerText = "<?php echo addslashes($message); ?>";
        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => toast.classList.add("show"));

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => {
                toast.remove();
            }, 400);
        }, 5000);

        // Optional: close on click
        toast.addEventListener('click', () => toast.remove());
    })();
    </script>
    <?php
    exit();
}

?>
