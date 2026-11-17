<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** The signed-in user's in-app notifications (the bell in the sidebar). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications', [
            'notifications' => $request->user()->notifications()->limit(100)->get(),
        ]);
    }

    /** Marks one notification read and opens the WBS step it is about. */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('home'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications.index')->with('status', 'All notifications marked as read.');
    }
}
