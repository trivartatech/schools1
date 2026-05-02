<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentPhotoController extends Controller
{
    private const ALLOWED_ROLES = ['admin', 'super_admin', 'principal', 'teacher'];

    public function upload(Request $request, Student $student)
    {
        $user = $request->user();

        abort_unless(in_array($user->user_type, self::ALLOWED_ROLES), 403, 'Not authorised to update student photos.');

        // Tenant scope: student must belong to the same school as the logged-in user
        abort_unless((int) $student->school_id === (int) $user->school_id, 403, 'Student not found.');

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $path = $request->file('photo')->store('students/photos', 'public');
        $student->update(['photo' => $path]);

        return response()->json([
            'message'   => 'Photo updated successfully.',
            'photo_url' => $student->photo_url,
        ]);
    }
}
