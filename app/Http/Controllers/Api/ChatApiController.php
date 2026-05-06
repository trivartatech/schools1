<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatApiController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    // GET /api/v1/chats — list conversations for authenticated user
    public function conversations(Request $request)
    {
        $user     = $this->authUser();
        $schoolId = $user->school_id;

        $conversations = $this->chatService->getConversationsForUser($user, $schoolId);

        return response()->json([
            'data'    => $conversations,
            'unread'  => $conversations->sum('unread_count'),
        ]);
    }

    // GET /api/v1/messages/{chat_id} — paginated messages
    public function messages(ChatConversation $conversation, Request $request)
    {
        $user = $this->authUser();
        $this->requireParticipant($conversation, $user->id);

        $perPage  = min($request->integer('per_page', 30), 100);
        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar,user_type', 'replyTo.sender:id,name'])
            ->whereNull('deleted_at_for_all')
            ->when($request->filled('before_id'), fn($q) => $q->where('id', '<', $request->before_id))
            ->latest()
            ->take($perPage)
            ->get()
            ->reverse()
            ->values();

        $this->chatService->markAllRead($conversation, $user);

        return response()->json([
            'data'     => $messages,
            'has_more' => $messages->isNotEmpty()
                && $conversation->messages()->where('id', '<', $messages->first()->id)->exists(),
        ]);
    }

    // POST /api/v1/send-message — send to a conversation
    public function sendMessage(Request $request)
    {
        $user = $this->authUser();

        $request->validate([
            'conversation_id' => 'required|exists:chat_conversations,id',
            'type'            => 'required|in:text,image,pdf,voice',
            'body'            => 'nullable|string|max:5000',
            'attachment'      => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,mp3,wav,ogg,m4a,aac,webm,mp4',
            'reply_to_id'     => 'nullable|exists:chat_messages,id',
        ]);

        $conv = ChatConversation::findOrFail($request->conversation_id);
        $this->requireParticipant($conv, $user->id);

        $msg = new ChatMessage([
            'conversation_id' => $conv->id,
            'sender_id'       => $user->id,
            'type'            => $request->type,
            'body'            => $request->body,
            'reply_to_id'     => $request->reply_to_id,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $msg->attachment_path = $file->store('chat/attachments', 'public');
            $msg->attachment_name = $file->getClientOriginalName();
            $msg->attachment_mime = $file->getMimeType();
            $msg->attachment_size = $file->getSize();
        }

        $msg->save();
        $this->chatService->markAllRead($conv, $user);
        $msg->load('sender:id,name,avatar,user_type', 'replyTo.sender:id,name');

        return response()->json(['data' => $msg], 201);
    }

    // POST /api/v1/create-group — admin/teacher only
    public function createGroup(Request $request)
    {
        $user = $this->authUser();

        if (!($user->isAdmin() || $user->isTeacher())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'name'       => 'required|string|max:100',
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $conv = $this->chatService->createCustomGroup(
            $user,
            $user->school_id,
            $request->name,
            $request->user_ids,
        );

        return response()->json(['data' => ['conversation_id' => $conv->id]], 201);
    }

    // POST /api/v1/sync-section — trigger section member sync (internal/auto)
    public function syncSection(Request $request)
    {
        $request->validate(['section_id' => 'required|exists:sections,id']);

        $user    = $this->authUser();
        $section = \App\Models\Section::findOrFail($request->section_id);

        // Build list of user IDs in this section (students + teachers)
        $studentUserIds = \App\Models\StudentAcademicHistory::where('section_id', $section->id)
            ->join('students', 'students.id', '=', 'student_academic_histories.student_id')
            ->pluck('students.user_id')
            ->filter()
            ->toArray();

        $this->chatService->syncSectionGroupMembers($section, $studentUserIds);

        return response()->json(['message' => 'Section group synced.', 'member_count' => count($studentUserIds)]);
    }

    // GET /api/v1/users/search — search users in the school for a new chat
    public function searchUsers(Request $request)
    {
        $request->validate([
            'q'         => 'nullable|string|max:100',
            'user_type' => 'nullable|string|max:30',
        ]);

        $user     = $this->authUser();
        $schoolId = $user->school_id;
        $q        = trim((string) $request->input('q', ''));
        $type     = $request->input('user_type');

        $query = \App\Models\User::where('school_id', $schoolId)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->select(['id', 'name', 'phone', 'avatar', 'user_type']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }
        if ($type) {
            $query->where('user_type', $type);
        }

        $users = $query->orderBy('name')->limit(50)->get()->map(function ($u) {
            $type = $u->user_type instanceof \BackedEnum ? $u->user_type->value : (string) ($u->user_type ?? '');
            return [
                'id'        => $u->id,
                'name'      => $u->name,
                'phone'     => $u->phone,
                'avatar'    => $u->avatar,
                'user_type' => $type,
                'role'      => ucfirst(str_replace('_', ' ', $type)),
            ];
        });

        return response()->json(['data' => $users]);
    }

    // POST /api/v1/start-direct-chat — find-or-create a 1-to-1 conversation
    public function startDirect(Request $request)
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $user  = $this->authUser();
        $other = \App\Models\User::where('school_id', $user->school_id)
            ->where('is_active', true)
            ->findOrFail($request->user_id);

        if ($other->id === $user->id) {
            return response()->json(['message' => "You can't chat with yourself."], 422);
        }

        $conv = $this->chatService->findOrCreateDirect($user, $other, $user->school_id);

        return response()->json([
            'data' => [
                'conversation_id' => $conv->id,
                'display_name'    => $other->name,
            ],
        ], 201);
    }

    // GET /api/v1/chats/{conversation}/poll — for mobile long-polling
    public function poll(ChatConversation $conversation, Request $request)
    {
        $user = $this->authUser();
        $this->requireParticipant($conversation, $user->id);

        $afterId  = $request->integer('after_id', 0);
        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar,user_type', 'replyTo.sender:id,name'])
            ->whereNull('deleted_at_for_all')
            ->where('id', '>', $afterId)
            ->oldest()
            ->take(50)
            ->get();

        if ($messages->isNotEmpty()) {
            $this->chatService->markAllRead($conversation, $user);
        }

        return response()->json(['data' => $messages]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return \App\Models\User */
    private function authUser()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user;
    }

    private function requireParticipant(ChatConversation $conv, int $userId): void
    {
        if (!$conv->participants()->where('user_id', $userId)->exists()) {
            abort(403, 'Not a participant.');
        }
    }
}
