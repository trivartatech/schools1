<?php

namespace App\Services\School;

use App\Models\School;
use App\Models\School\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function create(Request $request, School $school): Role
    {
        \DB::beginTransaction();

        $role = Role::create($this->formatParams($request, $school));

        \DB::commit();

        return $role;
    }

    private function formatParams(Request $request, School $school): array
    {
        return [
            'name' => Str::of($request->name)->slug('_')->value,
            'guard_name' => 'web',
            'school_id' => $school->id,
        ];
    }

    public function deletable(School $school, Role $role): void
    {
        if ($role->school_id !== $school->id) {
            throw ValidationException::withMessages(['message' => trans('general.errors.invalid_action')]);
        }

        if ($role->is_default) {
            throw ValidationException::withMessages(['message' => "Could not delete default system role."]);
        }
    }
}
