<?php

namespace App\Observers;

use App\Models\ClassSubject;
use App\Models\Section;

/** ClassSubject: subject-teacher assignments → sync the affected section(s). */
class ClassSubjectObserver extends SectionGroupObserver
{
    public function created(ClassSubject $cs): void
    {
        $this->resync($cs, 'subject.created');
    }

    public function updated(ClassSubject $cs): void
    {
        if (! $cs->wasChanged(['incharge_staff_id', 'section_id', 'course_class_id'])) return;

        $this->resync($cs, 'subject.updated');

        // If the row jumped to a different section, also resync the previous one.
        if ($cs->wasChanged('section_id')) {
            $oldSectionId = $cs->getOriginal('section_id');
            if ($oldSectionId) {
                $this->safeSync(Section::find($oldSectionId), 'subject.updated.old_section');
            }
        }
    }

    public function deleted(ClassSubject $cs): void
    {
        $this->resync($cs, 'subject.deleted');
    }

    protected function resync(ClassSubject $cs, string $context): void
    {
        if ($cs->section_id) {
            $this->safeSync(Section::find($cs->section_id), $context);
            return;
        }
        // Class-level row (section_id NULL): the teacher applies to every section.
        $this->safeSyncMany(
            Section::where('course_class_id', $cs->course_class_id)->get(),
            $context
        );
    }
}
