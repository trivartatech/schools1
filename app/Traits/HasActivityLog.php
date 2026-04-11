<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasActivityLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(strtolower(class_basename(get_class($this))));
    }

    /**
     * Tap to add school_id/org_id/trust_id automatically if available.
     */
    public function tapActivity($activity, string $eventName)
    {
        if (app()->bound('current_school_id')) {
            $schoolId = app('current_school_id');
            $activity->school_id = $schoolId;

            $school = \Illuminate\Support\Facades\Cache::remember(
                "school_meta_{$schoolId}",
                300,
                fn () => \App\Models\School::find($schoolId)
            );
            if ($school) {
                $activity->organization_id = $school->organization_id;
                $activity->trust_id = $school->trust_id;
            }
        }
    }
}
