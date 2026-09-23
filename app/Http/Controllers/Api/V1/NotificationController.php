<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->paginate(20);
        $unreadIds = $notifications->getCollection()->whereNull('read_at')->pluck('id')->all();

        $response = response()->json([
            'data' => $notifications->getCollection()->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? null,
                'message' => $n->data['message'] ?? null,
                'url' => $n->data['url'] ?? null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => count($unreadIds),
            ],
        ]);

        if ($unreadIds !== []) {
            $user->unreadNotifications()->whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        return $response;
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => __('app.notifications.mark_all')]);
    }
}
