<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class AffiliateController extends Controller
{
    public function recommend()
    {
        return Inertia::render('Affiliates/Recommend', [
            'referralCode' => auth()->user()->referral_code,
            'points' => auth()->user()->points,
            'referralLink' => route('register', ['ref' => auth()->user()->referral_code]),
        ]);
    }

    public function network()
    {
        $affiliates = auth()->user()->referrals()->select('id', 'name', 'email', 'created_at')->get();

        return Inertia::render('Affiliates/Network', [
            'affiliates' => $affiliates,
            'points' => auth()->user()->points,
        ]);
    }
}
