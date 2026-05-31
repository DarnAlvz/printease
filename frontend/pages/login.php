<!DOCTYPE html>
<html>

<head>
    <title>Login - PrintEase</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4 text-center">Login</h2>

        <form action="../../backend/actions/login_process.php" method="POST">
            <input class="w-full border p-2 mb-3" type="email" name="email" placeholder="Email" required>

            <input class="w-full border p-2 mb-3" type="password" name="password" placeholder="Password" required>

            <button class="w-full bg-blue-600 text-white p-2 rounded" type="submit" name="login">
                Login
            </button>
        </form>

        <p class="text-center mt-4">
            No account? <a class="text-blue-600" href="register.php">Register</a>
        </p>
    </div>

</body>

</html>