<?php

namespace App\Policies;

use App\Models\ExamType;
use App\Models\User;

class ExamTypePolicy
{
    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('manage_exam_types');
    }

    public function view(User $user, ExamType $examType)
    {
        return $user->hasPermissionTo('manage_exam_types') && $user->school_id === $examType->school_id;
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('manage_exam_types');
    }

    public function update(User $user, ExamType $examType)
    {
        return $user->hasPermissionTo('manage_exam_types') && $user->school_id === $examType->school_id;
    }

    public function delete(User $user, ExamType $examType)
    {
        return $user->hasPermissionTo('manage_exam_types') && $user->school_id === $examType->school_id;
    }
}
