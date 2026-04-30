<?php

namespace App\Observers;

use App\Models\Section;

/** Section: created → make group; incharge / name / class change → resync. */
class SectionObserver extends SectionGroupObserver
{
    public function created(Section $section): void
    {
        $this->safeSync($section, 'section.created');
    }

    public function updated(Section $section): void
    {
        if ($section->wasChanged(['incharge_staff_id', 'name', 'course_class_id'])) {
            $this->safeSync($section, 'section.updated');
        }
    }
}
