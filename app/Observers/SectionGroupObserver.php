<?php

namespace App\Observers;

use App\Models\Section;
use App\Services\ChatService;
use Illuminate\Support\Facades\Log;

/**
 * Base class for the four observers that keep section_group chat conversations
 * in sync (Section, CourseClass, ClassSubject, StudentAcademicHistory). Each
 * concrete observer extends this and delegates the actual roster update to
 * ChatService::syncSection() so the membership rules live in one place.
 *
 * Safety: every callback wraps the sync in try/catch so a chat-sync failure
 * cannot roll back the originating model save (mirrors the GL observer pattern).
 */
abstract class SectionGroupObserver
{
    public function __construct(protected ChatService $chatService) {}

    protected function safeSync(?Section $section, string $context): void
    {
        if (! $section) return;
        try {
            $this->chatService->syncSection($section);
        } catch (\Throwable $e) {
            Log::warning("Section group sync failed [{$context}]", [
                'section_id' => $section->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function safeSyncMany(iterable $sections, string $context): void
    {
        foreach ($sections as $section) {
            $this->safeSync($section, $context);
        }
    }
}
