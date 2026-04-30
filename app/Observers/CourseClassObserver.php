<?php

namespace App\Observers;

use App\Models\CourseClass;
use App\Models\Section;

/** CourseClass: class-incharge change → resync every section under this class. */
class CourseClassObserver extends SectionGroupObserver
{
    public function updated(CourseClass $class): void
    {
        if (! $class->wasChanged('incharge_staff_id')) return;

        $this->safeSyncMany(
            Section::where('course_class_id', $class->id)->get(),
            'class.incharge'
        );
    }
}
