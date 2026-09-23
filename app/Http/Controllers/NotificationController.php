<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()->paginate(20);
        $unreadIds = $notifications->getCollection()->whereNull('read_at')->pluck('id')->all();

        $response = view('notifications.index', [
            'notifications' => $notifications,
            'unreadIds' => array_flip($unreadIds),
        ]);

        if ($unreadIds !== []) {
            $user->unreadNotifications()->whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        return $response;
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
