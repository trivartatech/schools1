<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatParticipant;
use App\Models\Section;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    // ── Main chat page ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user     = $this->authUser();
        $schoolId = $this->resolveSchoolId($user);

        // Load all conversations this user participates in
        $conversations = $this->chatService->getConversationsForUser($user, $schoolId);

        // Users available to start a DM or add to group (same school).
        // Includes class_id / section_id (current academic year) for students
        // and parents — drives the Create Group filters in the Vue page.
        $availableUsers = User::where('school_id', $schoolId)
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->with([
                'student.currentAcademicHistory:id,student_id,class_id,section_id',
                'studentParent.students.currentAcademicHistory:id,student_id,class_id,section_id',
            ])
            ->select('id', 'name', 'user_type', 'avatar')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                $type = $u->user_type instanceof \BackedEnum
                    ? $u->user_type->value
                    : (string) $u->user_type;

                $classId   = null;
                $sectionId = null;

                if ($type === 'student' && $u->student?->currentAcademicHistory) {
                    $classId   = $u->student->currentAcademicHistory->class_id;
                    $sectionId = $u->student->currentAcademicHistory->section_id;
                } elseif ($type === 'parent' && $u->studentParent) {
                    // Use the first child's class/section as a representative
                    // tag — covers the common single-child case and gives the
                    // class filter something to match against for multi-child.
                    $firstChild = $u->studentParent->students->first();
                    if ($firstChild?->currentAcademicHistory) {
                        $classId   = $firstChild->currentAcademicHistory->class_id;
                        $sectionId = $firstChild->currentAcademicHistory->section_id;
                    }
                }

                return [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'user_type'  => $type,
                    'avatar'     => $u->avatar,
                    'class_id'   => $classId,
                    'section_id' => $sectionId,
                ];
            });

        // Classes + sections for the group filters (admin/teacher only).
        $classes  = [];
        $sections = [];
        if ($user->isSuperAdmin() || $user->isAdmin() || $user->isTeacher()) {
            $classes = \App\Models\CourseClass::where('school_id', $schoolId)
                ->orderBy('numeric_value')->orderBy('name')
                ->get(['id', 'name']);

            $sections = Section::where('school_id', $schoolId)
                ->forCurrentYear()
                ->with('courseClass:id,name')
                ->get()
                ->map(fn($s) => [
                    'id'         => $s->id,
                    'name'       => $s->name,
                    'class_id'   => $s->course_class_id,
                    'class_name' => $s->courseClass?->name,
                    // Pre-formatted label for legacy callers
                    'label'      => ($s->courseClass->name ?? '') . ' - ' . $s->name,
                ]);
        }

        return Inertia::render('School/Chat/Index', [
            'conversations'   => $conversations,
            'available_users' => $availableUsers,
            'classes'         => $classes,
            'sections'        => $sections,
            'active_id'       => $request->integer('conv'),
        ]);
    }

    // ── Fetch messages for a conversation (paginated, polling-friendly) ────
    public function messages(ChatConversation $conversation, Request $request)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);

        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar,user_type', 'replyTo.sender:id,name'])
            ->whereNull('deleted_at_for_all')
            ->when($request->filled('before_id'), fn($q) => $q->where('id', '<', $request->before_id))
            ->latest()
            ->take(40)
            ->get()
            ->reverse()
            ->values();

        // Mark messages as read
        $this->chatService->markAllRead($conversation, $user);

        // Fetch read receipts for last 20 messages
        $reads = ChatMessageRead::whereIn('message_id', $messages->pluck('id'))
            ->with('user:id,name')
            ->get()
            ->groupBy('message_id');

        $messages = $messages->map(function ($msg) use ($reads) {
            $msg->read_by = $reads->get($msg->id, collect())->map(fn($r) => [
                'user_id' => $r->user_id,
                'name'    => $r->user->name,
                'read_at' => $r->read_at,
            ])->values();
            return $msg;
        });

        return response()->json([
            'messages' => $messages,
            'has_more' => $messages->isNotEmpty() && $conversation->messages()->where('id', '<', $messages->first()->id)->exists(),
        ]);
    }

    // ── Poll for new messages (lightweight, used by frontend every 2s) ────
    public function poll(ChatConversation $conversation, Request $request)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);

        $afterId = $request->integer('after_id', 0);

        $messages = $conversation->messages()
            ->with(['sender:id,name,avatar,user_type', 'replyTo.sender:id,name'])
            ->whereNull('deleted_at_for_all')
            ->where('id', '>', $afterId)
            ->oldest()
            ->take(50)
            ->get();

        // Mark as read automatically
        if ($messages->isNotEmpty()) {
            $this->chatService->markAllRead($conversation, $user);
        }

        // Typing indicators (active in last 5s)
        $typers = DB::table('chat_typing_indicators')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->where('typed_at', '>=', now()->subSeconds(5))
            ->join('users', 'users.id', '=', 'chat_typing_indicators.user_id')
            ->select('users.id', 'users.name')
            ->get();

        return response()->json([
            'messages' => $messages,
            'typers'   => $typers,
        ]);
    }

    // ── Send a message ─────────────────────────────────────────────────────
    public function send(Request $request, ChatConversation $conversation)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);
        $this->authorizeCanSend($conversation, $user);

        $request->validate([
            'body'        => 'nullable|string|max:5000',
            'type'        => 'required|in:text,image,pdf,voice',
            'attachment'  => [
                'nullable', 'file', 'max:10240',
                Rule::when($request->type === 'image', ['mimes:jpg,jpeg,png,gif,webp']),
                Rule::when($request->type === 'pdf', ['mimes:pdf']),
                Rule::when($request->type === 'voice', ['mimes:mp3,wav,m4a,webm']),
            ],
            'reply_to_id' => 'nullable|exists:chat_messages,id',
        ]);

        $msg = new ChatMessage([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'type'            => $request->type,
            'body'            => $request->body,
            'reply_to_id'     => $request->reply_to_id,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat/attachments', 'public');
            $msg->attachment_path = $path;
            $msg->attachment_name = $file->getClientOriginalName();
            $msg->attachment_mime = $file->getMimeType();
            $msg->attachment_size = $file->getSize();
        }

        $msg->save();

        // Mark sender as read
        $this->chatService->markAllRead($conversation, $user);

        $msg->load('sender:id,name,avatar,user_type', 'replyTo.sender:id,name');
        $msg->read_by = [];

        return response()->json(['message' => $msg], 201);
    }

    // ── Edit a message ─────────────────────────────────────────────────────
    public function editMessage(Request $request, ChatMessage $message)
    {
        $user = $this->authUser();
        abort_if($message->sender_id !== $user->id, 403, 'Not your message.');

        $request->validate(['body' => 'required|string|max:5000']);

        $message->update([
            'body'      => $request->body,
            'edited_at' => now(),
        ]);

        return response()->json(['message' => $message->fresh()]);
    }

    // ── Delete a message ───────────────────────────────────────────────────
    public function deleteMessage(Request $request, ChatMessage $message)
    {
        $user = $this->authUser();
        $canDelete = $message->sender_id === $user->id || $user->isAdmin();
        abort_if(!$canDelete, 403, 'Not authorized.');

        $message->update(['deleted_at_for_all' => now()]);

        return response()->json(['ok' => true]);
    }

    // ── Pin / unpin a message ──────────────────────────────────────────────
    public function pinMessage(ChatMessage $message)
    {
        $user = $this->authUser();
        abort_if(!$user->isAdmin() && !$user->isTeacher(), 403);

        $message->update(['is_pinned' => !$message->is_pinned]);

        return response()->json(['is_pinned' => $message->is_pinned]);
    }

    // ── Pinned messages list ───────────────────────────────────────────────
    public function pinnedMessages(ChatConversation $conversation)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);

        $pinned = $conversation->messages()
            ->where('is_pinned', true)
            ->whereNull('deleted_at_for_all')
            ->with('sender:id,name,avatar')
            ->latest()
            ->get();

        return response()->json(['pinned' => $pinned]);
    }

    // ── Search messages ────────────────────────────────────────────────────
    public function searchMessages(ChatConversation $conversation, Request $request)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);

        $request->validate(['q' => 'required|string|min:2|max:100']);

        $results = $conversation->messages()
            ->where('type', 'text')
            ->whereNull('deleted_at_for_all')
            ->where('body', 'LIKE', '%' . $request->q . '%')
            ->with('sender:id,name')
            ->latest()
            ->take(20)
            ->get();

        return response()->json(['results' => $results]);
    }

    // ── Typing indicator ───────────────────────────────────────────────────
    public function typing(ChatConversation $conversation)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);

        DB::table('chat_typing_indicators')->upsert(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id, 'typed_at' => now()],
            ['conversation_id', 'user_id'],
            ['typed_at']
        );

        return response()->json(['ok' => true]);
    }

    // ── Start or get a direct (1:1) conversation ───────────────────────────
    public function startDirect(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user     = Auth::user();
        $schoolId = app('current_school')->id;
        $otherUser = User::where('school_id', $schoolId)->findOrFail($request->user_id);

        $conv = $this->chatService->findOrCreateDirect($user, $otherUser, $schoolId);

        return response()->json(['conversation_id' => $conv->id]);
    }

    // ── Create a custom group ──────────────────────────────────────────────
    public function createGroup(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $user     = Auth::user();
        abort_if(!$user->isAdmin() && !$user->isTeacher(), 403, 'Only admins/teachers can create groups.');

        $schoolId = app('current_school')->id;

        $conv = $this->chatService->createCustomGroup(
            $user,
            $schoolId,
            $request->name,
            $request->user_ids,
        );

        return response()->json(['conversation_id' => $conv->id]);
    }

    // ── Admin broadcast ────────────────────────────────────────────────────
    public function createBroadcast(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $user = $this->authUser();
        abort_if(!$user->isAdmin(), 403, 'Only admins can create broadcasts.');

        $schoolId = app('current_school')->id;

        $conv = $this->chatService->createBroadcast(
            $user,
            $schoolId,
            $request->name,
            $request->user_ids,
        );

        return response()->json(['conversation_id' => $conv->id]);
    }

    // ── Leave a group ──────────────────────────────────────────────────────
    public function leaveGroup(ChatConversation $conversation)
    {
        $user = $this->authUser();
        abort_if($conversation->is_system_managed, 403, 'Cannot leave a system-managed group.');

        ChatParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    // ── Add participants to group ──────────────────────────────────────────
    public function addParticipants(Request $request, ChatConversation $conversation)
    {
        $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $user = $this->authUser();
        abort_if(!$user->isAdmin(), 403, 'Only admins can add participants.');

        foreach ($request->user_ids as $uid) {
            ChatParticipant::firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_id'         => $uid,
            ], [
                'role'      => 'member',
                'joined_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Mark messages as read ──────────────────────────────────────────────
    public function markRead(ChatConversation $conversation)
    {
        $user = $this->authUser();
        $this->authorizeParticipant($conversation, $user);
        $this->chatService->markAllRead($conversation, $user);

        return response()->json(['ok' => true]);
    }

    // ── Poll conversation list (for badge updates) ─────────────────────────
    public function pollConversations()
    {
        $user     = $this->authUser();
        $schoolId = $this->resolveSchoolId($user);

        $conversations = $this->chatService->getConversationsForUser($user, $schoolId);

        return response()->json(['conversations' => $conversations]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Type-safe Auth user helper */
    private function authUser(): User
    {
        /** @var \App\Models\User $u */
        $u = Auth::user();
        return $u;
    }

    /**
     * Safely resolve the school ID — super_admin has no school_id, so
     * fall back to current_school (set by middleware) or the first school.
     */
    private function resolveSchoolId(User $user): int
    {
        if ($user->school_id) {
            return $user->school_id;
        }
        // Super admin: use the middleware-bound school or the first school in DB
        if (app()->bound('current_school')) {
            return app('current_school')->id;
        }
        return \App\Models\School::firstOrFail()->id;
    }

    private function authorizeParticipant(ChatConversation $conv, User $user): void
    {
        // Admins can access all conversations
        if ($user->isSuperAdmin() || $user->isAdmin()) return;

        $isMember = $conv->participants()->where('user_id', $user->id)->exists();
        abort_if(!$isMember, 403, 'You are not a participant in this conversation.');
    }

    private function authorizeCanSend(ChatConversation $conv, User $user): void
    {
        // Super admins, admins, principals can send anywhere
        if ($user->isSuperAdmin() || $user->isAdmin()) return;

        // Students can only message in their section groups
        if ($user->isStudent() && $conv->type === 'broadcast') {
            abort(403, 'Students cannot send messages in broadcast channels.');
        }
        // Parents cannot send in broadcasts
        if ($user->isParent() && $conv->type === 'broadcast') {
            abort(403, 'Parents cannot send messages in broadcast channels.');
        }
    }
}
