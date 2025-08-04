<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Show payment form for featured job
    public function create(Job $job, Request $request)
    {
        $user = $request->user();
        if (! $user->can('create_payment', $job)) {
            abort(403, 'Unauthorized');
        }
        $intent = $request->user()->createSetupIntent();

        return view('payments.create', compact('job', 'intent'));
    }

    // Process payment
    public function store(Request $request, Job $job)
    {
        $user = $request->user();
        if (! $user->can('create_payment', $job)) {
            abort(403, 'Unauthorized');
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $payment = $user->charge(1000, $request->payment_method, ['description' => "Feature job: {$job->title}"]);
            $job->update(['is_featured' => true]);

            Payment::create([
                'user_id' => $user->id,
                'job_id' => $job->id,
                'stripe_id' => $payment->id,
                'amount' => $payment->amount / 100,
                'status' => 'completed',
            ]);

            Cache::forget('jobs_page_'.$request->page);

            return redirect()->route('employer.dashboard')->with('success', 'Payment successful, job featured');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Payment failed: '.$e->getMessage());
        }
    }

    // List payment history
    public function index(Request $request)
    {
        $payments = $request->user()->payments()->with('job')->get();

        return view('payments.index', compact('payments'));
    }
}
