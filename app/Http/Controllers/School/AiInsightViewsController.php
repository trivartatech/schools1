<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AiInsightView;
use Illuminate\Http\Request;

class AiInsightViewsController extends Controller
{
    public function index()
    {
        $school = app('current_school');
        $user   = auth()->user();

        $views = AiInsightView::where('school_id', $school->id)
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'filters_json', 'created_at']);

        return response()->json(['views' => $views]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:80',
            'filters' => 'required|array',
            'filters.from'    => 'nullable|date',
            'filters.to'      => 'nullable|date|after_or_equal:filters.from',
            'filters.compare' => 'nullable|boolean',
        ]);

        $school = app('current_school');
        $user   = auth()->user();

        $view = AiInsightView::create([
            'school_id'    => $school->id,
            'user_id'      => $user->id,
            'name'         => $request->input('name'),
            'filters_json' => $request->input('filters'),
        ]);

        return response()->json(['view' => $view], 201);
    }

    public function destroy(AiInsightView $view)
    {
        $school = app('current_school');
        $user   = auth()->user();

        if ($view->school_id !== $school->id || $view->user_id !== $user->id) {
            abort(403);
        }

        $view->delete();
        return response()->json(['ok' => true]);
    }
}
