<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        // Recent rooms: owned + joined via collabs_in
        $ownedRooms = DB::table('rooms')
            ->where('owner_uid', $user->uid)
            ->where('maxplayers', '>', 1) // only multiplayer rooms
            ->orderBy('createdat', 'desc')
            ->get(['roid', 'name', 'code', 'maxplayers', 'createdat']);

        $joinedRooms = DB::table('collabs_in')
            ->join('rooms', 'collabs_in.roid', '=', 'rooms.roid')
            ->where('collabs_in.uid', $user->uid)
            ->where('rooms.owner_uid', '!=', $user->uid) // exclude own rooms
            ->orderBy('collabs_in.joinedat', 'desc')
            ->get(['rooms.roid', 'rooms.name', 'rooms.code', 'rooms.maxplayers', 'collabs_in.joinedat']);

        return view('profile', compact('user', 'ownedRooms', 'joinedRooms'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'min:2'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updates = ['username' => trim(strip_tags($data['username']))];

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('uid', $user->uid)->update($updates);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function leaveRoom(Request $request, int $roid)
    {
        $uid = auth()->id();

        // Remove from collabs_in (for joined rooms)
        DB::table('collabs_in')
            ->where('uid', $uid)
            ->where('roid', $roid)
            ->delete();

        return back()->with('success', 'Room removed from your list.');
    }
}
