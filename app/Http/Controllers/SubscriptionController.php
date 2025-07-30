<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\CardException;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request)
    {
        return view('subscribe', [
            'intent' => $request->user()->createSetupIntent(),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'paymentMethodId' => 'required|string',
        ]);

        try {
            $user = $request->user();

            $user->newSubscription('premium', 'price_1Rp1zVFIcfi7ZhWBIcwGdyTV')
                ->create($request->paymentMethodId);

            return redirect()->route('auto.apply')->with('success', 'You are now subscribed to premium!');
        } catch (CardException $e) {
            // Card was declined or payment failed
            Log::error('Subscription creation failed: '.$e->getMessage());

            return back()->withErrors([
                'payment' => 'Your card was declined. Please use a different payment method.',
            ]);
        } catch (\Exception $e) {
            // General exception handling
            Log::error('Subscription creation failed: '.$e->getMessage());

            return back()->withErrors([
                'payment' => 'An error occurred while processing your subscription. Please try again.',
            ]);
        }
    }
}
