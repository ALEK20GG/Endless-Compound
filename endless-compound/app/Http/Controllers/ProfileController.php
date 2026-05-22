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
            ->where('maxplayers', '>', 1)
            ->orderBy('createdat', 'desc')
            ->get(['roid', 'name', 'code', 'maxplayers', 'createdat']);

        $joinedRooms = DB::table('collabs_in')
            ->join('rooms', 'collabs_in.roid', '=', 'rooms.roid')
            ->where('collabs_in.uid', $user->uid)
            ->where('rooms.owner_uid', '!=', $user->uid)
            ->orderBy('collabs_in.joinedat', 'desc')
            ->get(['rooms.roid', 'rooms.name', 'rooms.code', 'rooms.maxplayers', 'collabs_in.joinedat']);

        // Stats
        $totalDiscoveries   = DB::table('compounds')
            ->where('first_discoverer_uid', $user->uid)
            ->count();

        $totalElements = DB::table('room_comps')
            ->join('rooms', 'room_comps.roid', '=', 'rooms.roid')
            ->where(function ($q) use ($user) {
                $q->where('rooms.owner_uid', $user->uid)
                  ->orWhereExists(function ($sub) use ($user) {
                      $sub->select(DB::raw(1))
                          ->from('collabs_in')
                          ->whereColumn('collabs_in.roid', 'rooms.roid')
                          ->where('collabs_in.uid', $user->uid);
                  });
            })
            ->distinct('room_comps.cid')
            ->count('room_comps.cid');

        $recentDiscoveries = DB::table('compounds')
            ->where('first_discoverer_uid', $user->uid)
            ->orderBy('discoveredat', 'desc')
            ->limit(5)
            ->get(['name', 'emoji', 'discoveredat']);

        return view('profile', compact(
            'user', 'ownedRooms', 'joinedRooms',
            'totalDiscoveries', 'totalElements', 'recentDiscoveries'
        ));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:60', 'min:2'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // filter_var per sanificazione esplicita
        $username = filter_var(trim($data['username']), FILTER_SANITIZE_SPECIAL_CHARS);
        $username = strip_tags($username);

        $updates = ['username' => $username];

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
