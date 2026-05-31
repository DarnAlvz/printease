<!DOCTYPE html>
<html>
<head>
    <title>Register - PrintEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white p-8 rounded shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold mb-4 text-center">Create Account</h2>

    <form action="../../backend/actions/register_process.php" method="POST">
        <input class="w-full border p-2 mb-3" type="text" name="full_name" placeholder="Full Name" required>

        <input class="w-full border p-2 mb-3" type="email" name="email" placeholder="Email" required>

        <input class="w-full border p-2 mb-3" type="password" name="password" placeholder="Password" required>

        <select class="w-full border p-2 mb-3" name="role" required>
            <option value="customer">Customer</option>
            <option value="shop_owner">Shop Owner</option>
        </select>

        <button class="w-full bg-blue-600 text-white p-2 rounded" type="submit" name="register">
            Register
        </button>
    </form>
</div>

</body>
</html>