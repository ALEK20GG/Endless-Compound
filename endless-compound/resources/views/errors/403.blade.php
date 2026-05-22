<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 – Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col items-center justify-center px-4">
    <div class="text-center space-y-6 max-w-md">
        <div class="text-7xl">🔒</div>
        <div>
            <h1 class="text-5xl font-bold text-red-400">403</h1>
            <p class="text-xl font-semibold mt-2">Access denied</p>
            <p class="text-gray-500 mt-2 text-sm">
                You don't have permission to access this room or resource.
            </p>
        </div>
        <a href="{{ url('/') }}"
           class="inline-block bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 px-6 rounded-xl transition text-sm">
            ← Back to home
        </a>
    </div>
</body>
</html>
