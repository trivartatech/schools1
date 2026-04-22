<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CourseClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app('current_school_id');
        
        $query = User::where('school_id', $schoolId)
            ->with(['staff', 'student', 'studentParent']);

        // Filters
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        } else {
            // Default: exclude super_admin and admin if not explicitly requested
            $query->whereIn('user_type', ['teacher', 'student', 'parent', 'principal', 'accountant', 'driver']);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by Class/Section for students
        if ($request->user_type === 'student' && ($request->filled('class_id') || $request->filled('section_id'))) {
            $query->whereHas('student.currentAcademicHistory', function($q) use ($request) {
                if ($request->filled('class_id')) $q->where('class_id', $request->class_id);
                if ($request->filled('section_id')) $q->where('section_id', $request->section_id);
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('School/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['user_type', 'search', 'status', 'class_id', 'section_id']),
            'classes' => CourseClass::where('school_id', $schoolId)->orderBy('numeric_value')->orderBy('name')->get(),
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $schoolId = app('current_school_id');
        $user = User::where('school_id', $schoolId)->findOrFail($id);

        // Prevent resetting your own password through this admin action
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Use the profile page to change your own password.'], 422);
        }

        $newPassword = Str::random(10); // 10 chars: stronger than 8

        $user->update(['password' => Hash::make($newPassword)]);

        // Log the action — who reset whose password and when
        \Illuminate\Support\Facades\Log::info('Password reset by admin', [
            'reset_by'    => auth()->id(),
            'reset_for'   => $user->id,
            'school_id'   => $schoolId,
            'timestamp'   => now()->toIso8601String(),
        ]);

        // The `password` field is intentionally returned here so the admin
        // UI can display it once and prompt the operator to communicate it
        // to the user via a secure channel (in-person / SMS).
        // It is NOT stored in logs — the Log::info above deliberately omits it.
        return response()->json([
            'success'  => true,
            'message'  => 'Password reset successfully. Show this to the user once, then close.',
            'password' => $newPassword,
        ]);
    }

    public function toggleStatus($id)
    {
        $user = User::where('school_id', app('current_school_id'))->findOrFail($id);
        
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('success', 'User status updated successfully.');
    }

    public function updateUsername(Request $request, $id)
    {
        $user = User::where('school_id', app('current_school_id'))->findOrFail($id);
        
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return back()->with('success', 'Credentials updated successfully.');
    }
}
