<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\ChatParticipant;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatService
{
    // ── Get all conversations for a user (with metadata) ──────────────────
    public function getConversationsForUser(User $user, int $schoolId): Collection
    {
        $isAdmin = $user->isSuperAdmin() || $user->isAdmin();

        if ($isAdmin) {
            // Admins see ALL conversations in the school
            $conversations = ChatConversation::where('school_id', $schoolId)
                ->with([
                    'participants.user:id,name,avatar,user_type',
                    'latestMessage.sender:id,name',
                ])
                ->latest('updated_at')
                ->get();

            // Auto-add admin as participant if not already in (so mark-read, typing work)
            $myConvIds = ChatParticipant::where('user_id', $user->id)->pluck('conversation_id')->flip();
            foreach ($conversations as $conv) {
                if (!isset($myConvIds[$conv->id])) {
                    ChatParticipant::firstOrCreate(
                        ['conversation_id' => $conv->id, 'user_id' => $user->id],
                        ['role' => 'admin', 'joined_at' => now()]
                    );
                    // Reload participants so unread count works below
                    $conv->load('participants.user:id,name,avatar,user_type');
                }
            }
        } else {
            $convIds = ChatParticipant::where('user_id', $user->id)
                ->pluck('conversation_id');

            $conversations = ChatConversation::whereIn('id', $convIds)
                ->where('school_id', $schoolId)
                ->with([
                    'participants.user:id,name,avatar,user_type',
                    'latestMessage.sender:id,name',
                ])
                ->latest('updated_at')
                ->get();
        }

        return $conversations->map(function ($conv) use ($user) {
            $participant = $conv->participants->firstWhere('user_id', $user->id);

            // For DMs, use the other person's name as the conversation name
            if ($conv->type === 'direct') {
                $other = $conv->participants
                    ->firstWhere('user_id', '!=', $user->id);
                $conv->display_name   = $other?->user?->name ?? 'Unknown';
                $conv->display_avatar = $other?->user?->avatar;
            } else {
                $conv->display_name   = $conv->name;
                $conv->display_avatar = $conv->avatar;
            }

            // Unread count
            $lastRead = $participant?->last_read_at;
            $conv->unread_count = $conv->messages()
                ->whereNull('deleted_at_for_all')
                ->where('sender_id', '!=', $user->id)
                ->when($lastRead, fn($q) => $q->where('created_at', '>', $lastRead))
                ->count();

            $conv->my_role  = $participant?->role ?? 'member';
            $conv->is_muted = $participant?->is_muted ?? false;

            // Participants list (for group header)
            $conv->members = $conv->participants->map(fn($p) => [
                'id'        => $p->user->id ?? null,
                'name'      => $p->user->name ?? 'Unknown',
                'avatar'    => $p->user->avatar ?? null,
                'user_type' => $p->user->user_type ?? null,
                'role'      => $p->role,
            ])->values();

            return $conv;
        });
    }


    // ── Find existing DM or create one ────────────────────────────────────
    public function findOrCreateDirect(User $userA, User $userB, int $schoolId): ChatConversation
    {
        // Find existing direct conversation between these two users
        $existing = ChatConversation::where('type', 'direct')
            ->where('school_id', $schoolId)
            ->whereHas('participants', fn($q) => $q->where('user_id', $userA->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $userB->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($userA, $userB, $schoolId) {
            $conv = ChatConversation::create([
                'school_id'  => $schoolId,
                'type'       => 'direct',
                'created_by' => $userA->id,
            ]);

            foreach ([$userA->id, $userB->id] as $uid) {
                ChatParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id'         => $uid,
                    'role'            => 'admin',
                    'joined_at'       => now(),
                ]);
            }

            return $conv;
        });
    }

    // ── Create a custom group conversation ────────────────────────────────
    public function createCustomGroup(User $creator, int $schoolId, string $name, array $userIds): ChatConversation
    {
        return DB::transaction(function () use ($creator, $schoolId, $name, $userIds) {
            $conv = ChatConversation::create([
                'school_id'  => $schoolId,
                'type'       => 'group',
                'group_type' => 'custom_group',
                'name'       => $name,
                'created_by' => $creator->id,
            ]);

            // Add creator as admin
            ChatParticipant::create([
                'conversation_id' => $conv->id,
                'user_id'         => $creator->id,
                'role'            => 'admin',
                'joined_at'       => now(),
            ]);

            // Add members
            foreach (array_unique($userIds) as $uid) {
                if ($uid == $creator->id) continue;
                ChatParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id'         => $uid,
                    'role'            => 'member',
                    'joined_at'       => now(),
                ]);
            }

            return $conv;
        });
    }

    // ── Create or update the section group for a Section ──────────────────
    public function ensureSectionGroup(Section $section, int $schoolId): ChatConversation
    {
        $existing = ChatConversation::where('section_id', $section->id)
            ->where('type', 'group')
            ->where('group_type', 'section_group')
            ->first();

        if ($existing) return $existing;

        return DB::transaction(function () use ($section, $schoolId) {
            $className = $section->courseClass->name ?? 'Class';
            $conv = ChatConversation::create([
                'school_id'         => $schoolId,
                'type'              => 'group',
                'group_type'        => 'section_group',
                'name'              => $className . ' - ' . $section->name,
                'section_id'        => $section->id,
                'is_system_managed' => true,
                'created_by'        => 1, // System user ID – admin
            ]);

            // Add incharge teacher if present
            if ($section->incharge_staff_id) {
                $teacherStaff = $section->inchargeStaff;
                if ($teacherStaff?->user_id) {
                    ChatParticipant::firstOrCreate([
                        'conversation_id' => $conv->id,
                        'user_id'         => $teacherStaff->user_id,
                    ], ['role' => 'admin', 'joined_at' => now()]);
                }
            }

            return $conv;
        });
    }

    // ── Sync section group members when section assignments change ─────────
    public function syncSectionGroupMembers(Section $section, array $userIds): void
    {
        $conv = ChatConversation::where('section_id', $section->id)
            ->where('group_type', 'section_group')
            ->first();

        if (!$conv) return;

        // Add new members
        foreach ($userIds as $uid) {
            ChatParticipant::firstOrCreate(
                ['conversation_id' => $conv->id, 'user_id' => $uid],
                ['role' => 'member', 'joined_at' => now()]
            );
        }

        // Remove members not in the list (except admins)
        ChatParticipant::where('conversation_id', $conv->id)
            ->where('role', 'member')
            ->whereNotIn('user_id', $userIds)
            ->delete();
    }

    // ── Create a broadcast channel ────────────────────────────────────────
    public function createBroadcast(User $creator, int $schoolId, string $name, array $userIds): ChatConversation
    {
        return DB::transaction(function () use ($creator, $schoolId, $name, $userIds) {
            $conv = ChatConversation::create([
                'school_id'  => $schoolId,
                'type'       => 'broadcast',
                'name'       => $name,
                'created_by' => $creator->id,
            ]);

            ChatParticipant::create([
                'conversation_id' => $conv->id,
                'user_id'         => $creator->id,
                'role'            => 'admin',
                'joined_at'       => now(),
            ]);

            foreach (array_unique($userIds) as $uid) {
                if ($uid == $creator->id) continue;
                ChatParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id'         => $uid,
                    'role'            => 'member',
                    'joined_at'       => now(),
                ]);
            }

            return $conv;
        });
    }

    // ── Mark all messages in a conversation as read by user ───────────────
    public function markAllRead(ChatConversation $conv, User $user): void
    {
        // Get unread message IDs for this user
        $unread = $conv->messages()
            ->whereNull('deleted_at_for_all')
            ->where('sender_id', '!=', $user->id)
            ->whereNotIn('id', function ($q) use ($user) {
                $q->select('message_id')
                    ->from('chat_message_reads')
                    ->where('user_id', $user->id);
            })
            ->pluck('id');

        if ($unread->isNotEmpty()) {
            $rows = $unread->map(fn($id) => [
                'message_id' => $id,
                'user_id'    => $user->id,
                'read_at'    => now(),
            ])->toArray();

            ChatMessageRead::insertOrIgnore($rows);
        }

        // Update last_read_at on participant
        ChatParticipant::where('conversation_id', $conv->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }
}
