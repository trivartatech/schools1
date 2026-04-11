<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ParentPortalController extends Controller
{
    public function studentProfile(Request $request)
    {
        return Inertia::render('Portal/StudentProfile');
    }
}
