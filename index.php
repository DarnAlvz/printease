<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrintEase E-Printing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-100 text-gray-900">
    <main class="min-h-screen flex items-center justify-center px-6 py-10">
        <section class="w-full max-w-5xl grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
            <div>
                <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-blue-600">
                    Calbayog City E-Printing
                </p>

                <h1 class="text-4xl font-bold leading-tight text-gray-950 sm:text-5xl">
                    PrintEase E-Printing System
                </h1>

                <p class="mt-5 max-w-xl text-lg leading-8 text-gray-600">
                    A simple web and mobile-based platform for customers to place printing orders and for print shops to manage requests with ease.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a class="inline-flex justify-center rounded bg-blue-600 px-6 py-3 font-semibold text-white shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        href="frontend/pages/login.php">
                        Login
                    </a>

                    <a class="inline-flex justify-center rounded px-6 py-3 font-semibold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        href="frontend/pages/register.php">
                        Create an account
                    </a>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-md">
                <div class="grid gap-4">
                    <div class="rounded border border-gray-200 p-4">
                        <h2 class="font-semibold text-gray-950">For Customers</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Submit print jobs online and track your order updates from your account.
                        </p>
                    </div>

                    <div class="rounded border border-gray-200 p-4">
                        <h2 class="font-semibold text-gray-950">For Print Shops</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Receive orders, update statuses, and keep customer requests organized.
                        </p>
                    </div>

                    <div class="rounded border border-gray-200 p-4">
                        <h2 class="font-semibold text-gray-950">Built for Local Service</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Designed for e-printing workflows across Calbayog City print shops.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
