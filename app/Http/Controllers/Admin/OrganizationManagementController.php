<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Organization;
use App\Models\Trust;
use Illuminate\Support\Str;

class OrganizationManagementController extends Controller
{
    /**
     * List all organizations.
     */
    public function index()
    {
        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => Organization::withCount('schools')->get(),
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return Inertia::render('Admin/Organizations/Create');
    }

    /**
     * Store new organization.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organizations,name',
            'email' => 'required|email|max:255|unique:organizations,email',
        ]);

        Organization::create(array_merge($validated, [
            'slug' => Str::slug($validated['name']),
        ]));

        return redirect()->route('admin.organizations.index')->with('success', 'Organization registered successfully.');
    }
}
