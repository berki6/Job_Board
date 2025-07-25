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
        $request->user()->newSubscription('premium', 'price_xxx')
            ->create($request->paymentMethodId);

        return redirect()->route('dashboard')->with('success', 'Subscribed to premium!');
    }
}

