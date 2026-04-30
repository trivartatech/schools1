<?php

namespace App\Observers;

use App\Models\Section;
use App\Models\StudentAcademicHistory;

/** Student admit / transfer / withdraw — sync source + target sections. */
class StudentAcademicHistoryObserver extends SectionGroupObserver
{
    public function created(StudentAcademicHistory $h): void
    {
        $this->syncId($h->section_id, 'history.created');
    }

    public function updated(StudentAcademicHistory $h): void
    {
        if (! $h->wasChanged('section_id')) return;

        // Source section loses the student; target section gains them.
        $this->syncId($h->getOriginal('section_id'), 'history.updated.from');
        $this->syncId($h->section_id, 'history.updated.to');
    }

    public function deleted(StudentAcademicHistory $h): void
    {
        $this->syncId($h->section_id, 'history.deleted');
    }

    protected function syncId(?int $sectionId, string $context): void
    {
        if (! $sectionId) return;
        $this->safeSync(Section::find($sectionId), $context);
    }
}
