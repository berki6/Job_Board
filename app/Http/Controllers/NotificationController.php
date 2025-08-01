<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // List notifications
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->where('read', false)->get();

        return view('notifications.index', compact('notifications'));
    }

    // Mark notification as read
    public function markAsRead(Request $request, $notification)
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();
        return redirect()->route('notifications.index')->with('success', 'Notification marked as read.');
    }
}
