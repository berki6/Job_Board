<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request)
    {
        return view('subscribe', [
            'intent' => $request->user()->createSetupIntent()
        ]);
    }

    public function create(Request $request)
    {
        $request->validate(['paymentMethodId' => 'required|string']);

        $request->user()->newSubscription('premium', 'price_1Rp1zVFIcfi7ZhWBIcwGdyTV')
            ->create($request->paymentMethodId);

        return redirect()->route('auto.apply')->with('success', 'You are now subscribed to premium!');
    }
}

