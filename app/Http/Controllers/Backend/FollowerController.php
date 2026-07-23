<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Follower;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FollowerController extends Controller
{
    public function follow(User $user)
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'No puedes seguirte a ti mismo.');
        }

        Follower::firstOrCreate([
            'user_id' => Auth::id(),
            'followed_id' => $user->id,
        ]);

        return back();
    }

    public function unfollow(User $user)
    {
        Follower::where('user_id', Auth::id())
            ->where('followed_id', $user->id)
            ->delete();

        return back();
    }
}
