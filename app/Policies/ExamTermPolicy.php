<?php

namespace App\Policies;

use App\Models\ExamTerm;
use App\Models\User;

class ExamTermPolicy
{
    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('manage_exam_terms');
    }

    public function view(User $user, ExamTerm $examTerm)
    {
        return $user->hasPermissionTo('manage_exam_terms') && $user->school_id === $examTerm->school_id;
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('manage_exam_terms');
    }

    public function update(User $user, ExamTerm $examTerm)
    {
        return $user->hasPermissionTo('manage_exam_terms') && $user->school_id === $examTerm->school_id;
    }

    public function delete(User $user, ExamTerm $examTerm)
    {
        return $user->hasPermissionTo('manage_exam_terms') && $user->school_id === $examTerm->school_id;
    }
}
