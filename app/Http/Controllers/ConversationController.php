<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendMessage;
use App\Actions\StartConversation;
use App\Exceptions\MessagingException;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-app messaging between a buyer and a listing's seller ("راسل المعلن"). One thread per
 * (listing, buyer) pair; see App\Models\Conversation.
 */
class ConversationController extends Controller
{
    /**
     * /messages: every conversation the user is a part of, newest activity first.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['listing', 'buyer', 'seller'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->unreadFor($user)])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('messages.index', ['conversations' => $conversations]);
    }

    /**
     * POST /ad/{listing}/message: finds or opens the buyer's conversation about this listing and
     * sends them straight to it (an optional first message can be posted in the same request).
     */
    public function start(Request $request, Listing $listing, StartConversation $start, SendMessage $send): RedirectResponse
    {
        try {
            $conversation = $start($request->user(), $listing);
        } catch (MessagingException $e) {
            return back()->with('error', $e->getMessage());
        }

        $body = trim((string) $request->input('body', ''));

        if ($body !== '') {
            try {
                $send($request->user(), $conversation, $body);
            } catch (MessagingException $e) {
                return redirect()->route('messages.show', $conversation)->with('error', $e->getMessage());
            }
        }

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * Opening the thread marks every message the other participant sent as read.
     */
    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $conversation->load(['listing', 'buyer', 'seller']);
        $messages = $conversation->messages()->with('sender')->orderBy('id')->get();

        $conversation->messages()->unreadFor($user)->update(['read_at' => now()]);

        return view('messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'other' => $conversation->otherParticipant($user),
        ]);
    }

    public function store(SendMessageRequest $request, Conversation $conversation, SendMessage $send): RedirectResponse
    {
        $this->authorize('reply', $conversation);

        try {
            $send($request->user(), $conversation, $request->validated('body'));
        } catch (MessagingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * Lightweight polling used by the thread view: messages with an id greater than "after",
     * marked read immediately since the viewer is on the page.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $after = (int) $request->query('after', 0);

        $messages = $conversation->messages()->with('sender')->where('id', '>', $after)->orderBy('id')->get();

        $conversation->messages()->unreadFor($user)->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'mine' => $message->sender_id === $user->id,
                'sender' => $message->sender->name,
                'time' => $message->created_at->translatedFormat('H:i'),
            ]),
        ]);
    }
}
