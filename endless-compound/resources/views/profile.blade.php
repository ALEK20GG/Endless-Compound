<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile – Endless Compound</title>
    @vite(['resources/css/app.css', 'resources/css/style.css'])
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col">

    <nav class="bg-gray-900 border-b border-gray-800 px-6 py-3 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="text-lg font-bold text-indigo-400 tracking-wide">⚗️ Endless Compound</a>
        <div class="flex items-center gap-4 text-sm">
            <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-white transition">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-gray-500 hover:text-white transition">Log out</button>
            </form>
        </div>
    </nav>

    <div class="flex-1 max-w-2xl mx-auto w-full px-4 py-10 space-y-8">

        <h1 class="text-2xl font-bold">Your Profile</h1>

        {{-- Success message --}}
        @if(session('success'))
            <div class="bg-green-900 border border-green-700 text-green-300 text-sm px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        {{-- Edit profile form --}}
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold">Edit Profile</h2>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Username</label>
                    <input type="text" name="username"
                           value="{{ old('username', $user->username) }}"
                           maxlength="60" required
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 @error('username') border-red-500 @enderror">
                    @error('username')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Email</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-500 cursor-not-allowed">
                    <p class="text-gray-600 text-xs mt-1">Email cannot be changed.</p>
                </div>

                <div class="border-t border-gray-800 pt-4">
                    <p class="text-sm text-gray-400 mb-3">Change password (leave blank to keep current)</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">New password</label>
                            <input type="password" name="password" minlength="8"
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 @error('password') border-red-500 @enderror">
                            @error('password')
                                <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-1">Confirm new password</label>
                            <input type="password" name="password_confirmation"
                                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                    Save changes
                </button>
            </form>
        </div>

        {{-- Recent rooms --}}
        @if($ownedRooms->isNotEmpty() || $joinedRooms->isNotEmpty())
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 space-y-4">
            <h2 class="text-lg font-semibold">Your Rooms</h2>

            @if($ownedRooms->isNotEmpty())
                <div class="space-y-2">
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Created by you</p>
                    @foreach($ownedRooms as $room)
                        <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
                            <div>
                                <a href="{{ route('game.room', $room->roid) }}"
                                   class="text-sm font-medium text-white hover:text-indigo-400 transition">
                                    {{ e($room->name) }}
                                </a>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-xs text-gray-500">{{ $room->maxplayers }} players max</span>
                                    @if($room->code)
                                        <span class="text-xs font-mono bg-gray-700 px-2 py-0.5 rounded text-indigo-300">
                                            {{ $room->code }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('profile.room.leave', $room->roid) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-gray-600 hover:text-red-400 transition text-xs"
                                        onclick="return confirm('Remove this room from your list?')">
                                    Remove
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($joinedRooms->isNotEmpty())
                <div class="space-y-2">
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Joined rooms</p>
                    @foreach($joinedRooms as $room)
                        <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
                            <div>
                                <a href="{{ route('game.room', $room->roid) }}"
                                   class="text-sm font-medium text-white hover:text-indigo-400 transition">
                                    {{ e($room->name) }}
                                </a>
                                @if($room->code)
                                    <span class="text-xs font-mono bg-gray-700 px-2 py-0.5 rounded text-indigo-300 ml-2">
                                        {{ $room->code }}
                                    </span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('profile.room.leave', $room->roid) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-gray-600 hover:text-red-400 transition text-xs"
                                        onclick="return confirm('Leave this room?')">
                                    Leave
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

    </div>

</body>
</html>
