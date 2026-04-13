<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
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
     * For scheduled broadcasts, the scheduler dispatches async via dispatch().
     */
    public function broadcast(Announcement $announcement)
    {
        Log::info("Dispatching ProcessBroadcast job (sync) for Announcement ID {$announcement->id}");

        \App\Jobs\ProcessBroadcast::dispatchSync($announcement);

        return true;
    }

    /**
     * Actual broadcast processing logic, called from the background job.
     */
    public function processIndividualMessages(Announcement $announcement)
    {
        $announcement->load('school', 'template'); // Eager load school and template to avoid N+1 queries
        
        $query = User::where('school_id', $announcement->school_id)->where('is_active', true);

        try {
            // Filter by Audience
            if ($announcement->audience_type === 'school') {
                $query->whereIn('user_type', ['student', 'parent']);
            } elseif ($announcement->audience_type === 'employee') {
                $query->whereNotIn('user_type', ['student', 'parent']);
            } elseif ($announcement->audience_type === 'class') {
                // Use academicHistories (all years, status=current) so broadcast works
                // even when students haven't been re-enrolled for the new academic year.
                $query->whereIn('user_type', ['student', 'parent'])
                    ->where(function($q) use ($announcement) {
                        $q->whereHas('student.academicHistories', function($sq) use ($announcement) {
                            $sq->whereIn('class_id', $announcement->audience_ids)
                               ->where('status', 'current');
                        })->orWhereHas('studentParent.students.academicHistories', function($sq) use ($announcement) {
                            $sq->whereIn('class_id', $announcement->audience_ids)
                               ->where('status', 'current');
                        });
                    });
            } elseif ($announcement->audience_type === 'section') {
                $query->whereIn('user_type', ['student', 'parent'])
                    ->where(function($q) use ($announcement) {
                        $q->whereHas('student.academicHistories', function($sq) use ($announcement) {
                            $sq->whereIn('section_id', $announcement->audience_ids)
                               ->where('status', 'current');
                        })->orWhereHas('studentParent.students.academicHistories', function($sq) use ($announcement) {
                            $sq->whereIn('section_id', $announcement->audience_ids)
                               ->where('status', 'current');
                        });
                    });
            } elseif ($announcement->audience_type === 'individual') {
                $query->whereIn('id', $announcement->audience_ids);
            }

            $users = $query->get();
            Log::info("Broadcast starting for Announcement ID {$announcement->id}. Users found: " . $users->count());
        } catch (\Throwable $e) {
            Log::error("Critical fail in audience resolution for broadcast [ID: {$announcement->id}]: " . $e->getMessage());
            throw $e; // Rethrow to fail the job gracefully
        }
        $processedPhones = [];

        /** @var User $user */
        foreach ($users as $user) {
            $phone = $user->phone;
            
            if ($user->isParent() && $user->studentParent) {
                $phone = $user->studentParent->primary_phone 
                    ?? $user->studentParent->father_phone 
                    ?? $user->studentParent->mother_phone;
            } elseif ($user->isStudent() && $user->student) {
                $phone = $user->student->phone
                    ?? $user->student->studentParent?->primary_phone
                    ?? $user->student->studentParent?->father_phone
                    ?? $user->student->studentParent?->mother_phone
                    ?? null;
            }
            
            if (!$phone) {
                Log::warning("No phone found for user ID {$user->id} ({$user->user_type})");
                continue;
            }

            if (in_array($phone, $processedPhones)) {
                continue;
            }
            $processedPhones[] = $phone;

            Log::info("Processing broadcast to phone: {$phone} for user ID {$user->id}");

            try {
                if ($announcement->delivery_method === 'voice') {
                    // Bug fix: Priority to pre-converted MP3/WAV path to avoid repeat conversions in Exotel webhook
                    $audioPath = $announcement->mp3_path ?: $announcement->audio_path;
                    $audioUrl = $audioPath ? asset('storage/' . $audioPath) : null;
                    $content = $announcement->template ? $announcement->template->content : null;
                    
                    if ($content) {
                        $content = str_replace(
                            ['{name}', '{title}', '{app_name}'],
                            [$user->name, $announcement->title, $announcement->school->name],
                            $content
                        );
                    }

                    // Fallback: if no template and no audio, use announcement title as TTS
                    // to ensure Exotel never receives an empty ExoML response.
                    if (!$content && !$audioUrl) {
                        $content = "This is an important announcement from {$announcement->school->name}. {$announcement->title}.";
                    }

                    $this->notificationService->sendVoiceCall($phone, $audioUrl, $content, $user->id);
                } elseif ($announcement->delivery_method === 'sms' && $announcement->template) {
                    $this->notificationService->sendSms($phone, $announcement->template->content, $announcement->template->template_id, $user->id, [
                        'app_name' => $announcement->school->name,
                        'title' => $announcement->title,
                        'name' => $user->name,
                    ]);
                } elseif ($announcement->delivery_method === 'whatsapp' && $announcement->template) {
                    $params = [
                        'name' => $user->name,
                        'title' => $announcement->title,
                        'app_name' => $announcement->school->name
                    ];
                    $this->notificationService->sendWhatsApp($phone, $announcement->template->template_id, $params, $user->id, $announcement->template->language_code ?? 'en');
                }
            } catch (\Exception $e) {
                Log::error("Failed to send broadcast to user ID {$user->id} / Phone {$phone}: " . $e->getMessage());
            }
        }

        return $users->count();
    }
}
