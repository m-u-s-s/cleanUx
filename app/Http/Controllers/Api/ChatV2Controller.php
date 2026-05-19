<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\ChatV2\AttachmentService;
use App\Services\ChatV2\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatV2Controller extends Controller
{
    public function __construct(
        protected ChatService $chat,
        protected AttachmentService $attachments,
    ) {}

    public function listMyThreads(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = ChatThread::query()
            ->forUser($user->id)
            ->when($request->boolean('archived'), fn ($q) => $q->where('is_archived', true), fn ($q) => $q->where('is_archived', false))
            ->orderByDesc('last_message_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function createThread(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context_type' => ['nullable', 'string', 'max:64'],
            'context_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:191'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'participants.*.role' => ['required', 'string', 'in:client,provider,admin,observer,system'],
        ]);

        // Force inclure le user actuel comme participant
        $currentUserId = $request->user()->id;
        $hasMe = collect($data['participants'])->contains(fn ($p) => (int) $p['user_id'] === $currentUserId);
        if (! $hasMe) {
            $data['participants'][] = ['user_id' => $currentUserId, 'role' => 'client'];
        }

        try {
            $thread = $this->chat->startThread(
                contextType: $data['context_type'] ?? null,
                contextId: $data['context_id'] ?? null,
                participants: $data['participants'],
                title: $data['title'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'thread' => $thread->fresh('participants')], 201);
    }

    public function showThread(Request $request, ChatThread $thread): JsonResponse
    {
        if (! $this->isParticipant($thread, $request->user()->id)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        return response()->json(['data' => $thread->load('participants')]);
    }

    public function listMessages(Request $request, ChatThread $thread): JsonResponse
    {
        if (! $this->isParticipant($thread, $request->user()->id)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $rows = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->notDeleted()
            ->orderByDesc('id')
            ->limit((int) $request->integer('limit', (int) config('chat_v2.messages_per_page', 50)))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function sendMessage(Request $request, ChatThread $thread): JsonResponse
    {
        $user = $request->user();
        if (! $this->isParticipant($thread, $user->id)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:' . (int) config('chat_v2.max_message_length', 4096)],
            'attachment' => ['nullable', 'file'],
        ]);

        if (empty($data['body']) && ! $request->hasFile('attachment')) {
            return response()->json(['ok' => false, 'errors' => ['body' => ['Body ou attachment requis.']]], 422);
        }

        $attachment = null;
        if ($request->hasFile('attachment')) {
            try {
                $attachment = $this->attachments->store($request->file('attachment'));
            } catch (ValidationException $e) {
                return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
            }
        }

        try {
            $msg = $this->chat->sendMessage($thread, $user, $data['body'] ?? '', $attachment);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $msg,
            'moderation_status' => $msg->moderation_status,
        ], 201);
    }

    public function markAsRead(Request $request, ChatThread $thread): JsonResponse
    {
        $user = $request->user();
        if (! $this->isParticipant($thread, $user->id)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $upTo = $request->integer('up_to_message_id') ?: null;
        $count = $this->chat->markAsRead($thread, $user, $upTo);
        return response()->json(['ok' => true, 'marked' => $count]);
    }

    public function archiveThread(Request $request, ChatThread $thread): JsonResponse
    {
        $user = $request->user();
        if (! $this->isParticipant($thread, $user->id)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $this->chat->archiveThread($thread);
        return response()->json(['ok' => true]);
    }

    public function downloadAttachment(Request $request, ChatMessage $message): Response
    {
        if (! $message->thread || ! $this->isParticipant($message->thread, $request->user()->id)) {
            abort(403);
        }
        if (! $message->attachment_path) {
            abort(404);
        }
        $disk = (string) config('chat_v2.attachments_disk', 'local');
        if (! Storage::disk($disk)->exists($message->attachment_path)) {
            abort(404);
        }
        return response(
            Storage::disk($disk)->get($message->attachment_path),
            200,
            [
                'Content-Type' => $message->attachment_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . basename($message->attachment_path) . '"',
            ],
        );
    }

    /* ----- ADMIN ----- */

    public function adminListThreads(Request $request): JsonResponse
    {
        $rows = ChatThread::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('flagged_only'), fn ($q) => $q->where('flagged_count', '>', 0))
            ->orderByDesc('last_message_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminListFlagged(Request $request): JsonResponse
    {
        $rows = ChatMessage::query()
            ->whereIn('moderation_status', [ChatMessage::MODERATION_FLAGGED, ChatMessage::MODERATION_BLOCKED])
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminModerate(Request $request, ChatMessage $message): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:delete,block,approve'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $row = $this->chat->moderateMessage($message, $data['action'], $request->user()->id, $data['reason'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'message' => $row]);
    }

    protected function isParticipant(ChatThread $thread, int $userId): bool
    {
        return ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }
}
