<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_students');
    }

    public function view(User $user, Student $student): bool
    {
        if (!$user->hasPermissionTo('view_students')) {
            return false;
        }

        // Tenant isolation
        if ($user->school_id && $user->school_id !== $student->school_id) {
            return false;
        }

        // Parents can only view their own children
        if ($user->isParent()) {
            return $student->parent_id === $user->studentParent?->id;
        }

        // Students can only view themselves
        if ($user->isStudent()) {
            return $student->id === $user->student?->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_students');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('edit_students')
            && ($user->school_id === null || $user->school_id === $student->school_id);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('delete_students')
            && ($user->school_id === null || $user->school_id === $student->school_id);
    }

    public function requestEdit(User $user, Student $student): bool
    {
        return $user->hasPermissionTo('request_edit_students')
            || $user->hasPermissionTo('edit_students');
    }

    /**
     * Bulk import students via CSV/Excel upload.
     * Requires explicit 'bulk_import_students' permission.
     */
    public function bulkImport(User $user): bool
    {
        return $user->hasPermissionTo('bulk_import_students');
    }

    /**
     * Promote students to the next class/year in bulk.
     * Requires 'promote_students' — a destructive action limited to admin/principal.
     */
    public function promote(User $user): bool
    {
        return $user->hasPermissionTo('promote_students');
    }

    /**
     * View student documents (certificates, transfer certificates, birth certificates).
     * Students can view their own; parents their child's; staff need the permission.
     */
    public function viewDocuments(User $user, Student $student): bool
    {
        if ($user->school_id && $user->school_id !== $student->school_id) {
            return false;
        }

        // Student views own documents
        if ($user->isStudent()) {
            return $student->id === $user->student?->id
                && $user->hasPermissionTo('view_student_documents');
        }

        // Parent views their child's documents
        if ($user->isParent()) {
            $parentStudentIds = $user->studentParent?->students?->pluck('id') ?? collect();
            return $parentStudentIds->contains($student->id)
                && $user->hasPermissionTo('view_student_documents');
        }

        return $user->hasPermissionTo('view_student_documents');
    }

    /**
     * Analyse a student's performance data (reports, analytics).
     * Falls back to 'view_reports' for staff who have broad reporting access.
     */
    public function analyse(User $user, Student $student): bool
    {
        if ($user->school_id && $user->school_id !== $student->school_id) {
            return false;
        }

        return $user->hasPermissionTo('analyse_students')
            || $user->hasPermissionTo('view_reports');
    }
}
