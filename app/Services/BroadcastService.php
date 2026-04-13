<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Models\StudentAcademicHistory;
use Illuminate\Support\Facades\Log;

class BroadcastService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Entry point for broadcasting an announcement.
     * Uses dispatchSync to run immediately (works without queue worker).
     */
    public function broadcast(Announcement $announcement)
    {
        Log::info("Dispatching ProcessBroadcast job (sync) for Announcement ID {$announcement->id}");
        \App\Jobs\ProcessBroadcast::dispatchSync($announcement);
        return true;
    }

    /**
     * Resolve list of recipients: [ ['phone'=>'...', 'name'=>'...', 'user_id'=>null] ]
     *
     * For student/parent audiences (school, class, section) we go directly through
     * StudentAcademicHistory → Student → StudentParent to get phone numbers.
     * This works even when students don't have User accounts.
     *
     * For employee/individual audiences we still use the User model.
     */
    protected function resolveRecipients(Announcement $announcement): array
    {
        $schoolId = $announcement->school_id;
        $type     = $announcement->audience_type;
        $ids      = $announcement->audience_ids ?? [];

        // ── Employee or Individual → User model ──────────────────────────────
        if ($type === 'employee' || $type === 'individual') {
            $query = User::where('school_id', $schoolId)->where('is_active', true);

            if ($type === 'employee') {
                $query->whereNotIn('user_type', ['student', 'parent']);
            } else {
                $query->whereIn('id', $ids);
            }

            return $query->get()->map(function ($user) {
                return ['phone' => $user->phone, 'name' => $user->name, 'user_id' => $user->id];
            })->filter(fn($r) => !empty($r['phone']))->values()->all();
        }

        // ── School / Class / Section → StudentAcademicHistory ────────────────
        // Students may not have User accounts; resolve phones via parent records.
        $query = StudentAcademicHistory::with(['student.studentParent'])
            ->where('status', 'current')
            ->whereHas('student', fn($q) => $q->where('school_id', $schoolId));

        if ($type === 'class') {
            $query->whereIn('class_id', $ids);
        } elseif ($type === 'section') {
            $query->whereIn('section_id', $ids);
        }
        // 'school' = all current students in the school (no extra filter)

        $recipients = [];
        $seen       = [];

        foreach ($query->get() as $history) {
            $student = $history->student;
            if (!$student) continue;

            $parent = $student->studentParent;
            $phone  = $parent?->primary_phone
                   ?? $parent?->father_phone
                   ?? $parent?->mother_phone
                   ?? null;

            if (!$phone || isset($seen[$phone])) continue;
            $seen[$phone] = true;

            $recipients[] = [
                'phone'   => $phone,
                'name'    => trim($student->first_name . ' ' . $student->last_name),
                'user_id' => $student->user_id,
            ];
        }

        return $recipients;
    }

    /**
     * Actual broadcast processing logic, called from the background job.
     */
    public function processIndividualMessages(Announcement $announcement)
    {
        $announcement->load('school', 'template');

        try {
            $recipients = $this->resolveRecipients($announcement);
            Log::info("Broadcast starting for Announcement ID {$announcement->id}. Recipients found: " . count($recipients));
        } catch (\Throwable $e) {
            Log::error("Recipient resolution failed for Announcement ID {$announcement->id}: " . $e->getMessage());
            throw $e;
        }

        $audioPath = $announcement->mp3_path ?: $announcement->audio_path;
        $audioUrl  = $audioPath ? asset('storage/' . $audioPath) : null;

        foreach ($recipients as $recipient) {
            $phone  = $recipient['phone'];
            $name   = $recipient['name'];
            $userId = $recipient['user_id'];

            try {
                if ($announcement->delivery_method === 'voice') {
                    $content = $announcement->template ? $announcement->template->content : null;

                    if ($content) {
                        $content = str_replace(
                            ['{name}', '{title}', '{app_name}'],
                            [$name, $announcement->title, $announcement->school->name],
                            $content
                        );
                    }

                    if (!$content && !$audioUrl) {
                        $content = "This is an important announcement from {$announcement->school->name}. {$announcement->title}.";
                    }

                    Log::info("Sending voice to [{$phone}] ({$name})");
                    $this->notificationService->sendVoiceCall($phone, $audioUrl, $content, $userId);

                } elseif ($announcement->delivery_method === 'sms' && $announcement->template) {
                    $this->notificationService->sendSms($phone, $announcement->template->content, $announcement->template->template_id, $userId, [
                        'name'     => $name,
                        'title'    => $announcement->title,
                        'app_name' => $announcement->school->name,
                    ]);

                } elseif ($announcement->delivery_method === 'whatsapp' && $announcement->template) {
                    $this->notificationService->sendWhatsApp($phone, $announcement->template->template_id, [
                        'name'     => $name,
                        'title'    => $announcement->title,
                        'app_name' => $announcement->school->name,
                    ], $userId, $announcement->template->language_code ?? 'en');
                }
            } catch (\Exception $e) {
                Log::error("Broadcast failed for phone [{$phone}]: " . $e->getMessage());
            }
        }

        return count($recipients);
    }
}
