<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

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
    public function markAsRead(Notification $notification)
    {
        $this->authorize('read_notification', $notification);
        $notification->update(['read' => true]);
        return redirect()->route('notifications.index')->with('success', 'Notification marked as read');
    }
}
