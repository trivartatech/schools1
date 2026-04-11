<?php

namespace App\Utils;

use Spatie\Activitylog\Facades\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityLog
{
    /**
     * Log a generic action.
     */
    public static function log(string $description, ?Model $subject = null, array $properties = [], string $logName = 'default')
    {
        $activity = activity($logName)
            ->performedOn($subject)
            ->withProperties($properties)
            ->by(auth()->user());

        // Add multi-tenant context (school, organization, trust)
        if (app()->bound('current_school_id')) {
            $schoolId = app('current_school_id');
            $activity->tap(function ($activity) use ($schoolId) {
                $activity->school_id = $schoolId;

                // Optionally fetch organization and trust from school if not cached/shared
                $school = \App\Models\School::find($schoolId);
                if ($school) {
                    $activity->organization_id = $school->organization_id;
                    $activity->trust_id = $school->trust_id;
                }
            });
        }

        return $activity->log($description);
    }

    /**
     * Log student-related actions.
     */
    public static function student(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'student');
    }

    /**
     * Log staff-related actions.
     */
    public static function staff(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'staff');
    }

    /**
     * Log academic-related actions (exams, classes, etc.).
     */
    public static function academic(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'academic');
    }

    /**
     * Log financial-related actions (fees, expenses).
     */
    public static function finance(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'finance');
    }

    /**
     * Log system/configuration-related actions.
     */
    public static function system(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'system');
    }

    /**
     * Log security-related actions (login, role changed).
     */
    public static function security(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'security');
    }

    /**
     * Log communication-related actions (announcements, messages).
     */
    public static function communication(string $description, ?Model $subject = null, array $properties = [])
    {
        return self::log($description, $subject, $properties, 'communication');
    }
}
