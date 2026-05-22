<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col items-center justify-center px-4">
    <div class="text-center space-y-6 max-w-md">
        <div class="text-7xl">🧪</div>
        <div>
            <h1 class="text-5xl font-bold text-indigo-400">404</h1>
            <p class="text-xl font-semibold mt-2">Element not found</p>
            <p class="text-gray-500 mt-2 text-sm">
                This combination doesn't exist yet. Maybe you should try combining something else.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ url('/') }}"
               class="bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 px-6 rounded-xl transition text-sm">
                ← Back to home
            </a>
            @auth
            <a href="{{ route('game.index') }}"
               class="bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium py-2.5 px-6 rounded-xl transition text-sm border border-gray-700">
                Play now
            </a>
            @endauth
        </div>
    </div>
</body>
</html>
