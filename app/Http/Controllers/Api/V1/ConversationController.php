<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\SendMessage;
use App\Actions\StartConversation;
use App\Exceptions\MessagingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\Api\ConversationResource;
use App\Http\Resources\Api\MessageResource;
use App\Models\Conversation;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['listing', 'buyer', 'seller'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->unreadFor($user)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => ConversationResource::collection($conversations)]);
    }

    public function start(Request $request, Listing $listing, StartConversation $start, SendMessage $send): JsonResponse
    {
        try {
            $conversation = $start($request->user(), $listing);
        } catch (MessagingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $body = trim((string) $request->input('body', ''));

        if ($body !== '') {
            try {
                $send($request->user(), $conversation, $body);
            } catch (MessagingException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json(['data' => new ConversationResource($conversation)], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $conversation->load(['listing', 'buyer', 'seller']);
        $messages = $conversation->messages()->with('sender')->orderBy('id')->get();

        $conversation->messages()->unreadFor($user)->update(['read_at' => now()]);

        return response()->json([
            'data' => new ConversationResource($conversation),
            'messages' => MessageResource::collection($messages),
        ]);
    }

    public function store(SendMessageRequest $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $this->authorize('reply', $conversation);

        try {
            $message = $send($request->user(), $conversation, $request->validated('body'));
        } catch (MessagingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new MessageResource($message->load('sender'))], 201);
    }
}
