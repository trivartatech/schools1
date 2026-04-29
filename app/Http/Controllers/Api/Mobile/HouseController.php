<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\HousePoint;
use App\Models\HouseStudent;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mobile House + Leaderboard endpoints.
 *
 * Read-only on mobile for v1. Admin still awards/deducts points from web
 * (HousePointController@store) — the mobile surface is just the rallying
 * leaderboard and the active student's "my house" view, which is the
 * engagement piece students and parents actually open the app to see.
 */
class HouseController extends Controller
{
    private const CATEGORIES = ['sports', 'academic', 'cultural', 'discipline', 'general'];

    /**
     * Resolve which student's data to serve. Mirrors the helper used by other
     * Mobile/* controllers — students see themselves, parents see the active
     * child (X-Active-Student-Id header or ?student_id=).
     */
    private function assertAdmin(Request $request): void
    {
        $user = $request->user();
        $type = $user->user_type instanceof \BackedEnum ? $user->user_type->value : (string) $user->user_type;
        if (!in_array($type, ['admin', 'school_admin', 'principal', 'super_admin'], true)) {
            abort(response()->json(['error' => 'Unauthorized.'], 403));
        }
    }

    private function resolveStudentId($user, ?Request $request = null): ?int
    {
        if ($user->isStudent()) return $user->student?->id;
        if ($user->isParent()) {
            $parent = $user->studentParent;
            if (!$parent) return null;
            $children = $parent->students()->pluck('id');
            if ($children->isEmpty()) return null;
            $requested = $request?->header('X-Active-Student-Id') ?? $request?->input('student_id');
            if ($requested && $children->contains((int) $requested)) return (int) $requested;
            return (int) $children->first();
        }
        // Admin/teacher: optional ?student_id= override.
        if ($request?->filled('student_id')) {
            return (int) $request->input('student_id');
        }
        return null;
    }

    /**
     * POST /mobile/houses/{houseId}/points
     *
     * Award or deduct points to a house. Mirrors web HousePointController::store().
     * Admin-only. Points must be non-zero; negative values deduct.
     */
    public function awardPoints(Request $request, int $houseId): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');
        $yearId   = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;
        if (!$yearId) {
            return response()->json(['error' => 'No active academic year.'], 422);
        }

        $house = House::where('school_id', $schoolId)->find($houseId);
        if (!$house) {
            return response()->json(['error' => 'House not found.'], 404);
        }

        $validated = $request->validate([
            'category'    => 'required|in:sports,academic,cultural,discipline,general',
            'points'      => 'required|integer|not_in:0|between:-999,999',
            'description' => 'required|string|max:255',
        ]);

        $entry = HousePoint::create([
            'school_id'        => $schoolId,
            'house_id'         => $house->id,
            'academic_year_id' => $yearId,
            'category'         => $validated['category'],
            'points'           => $validated['points'],
            'description'      => $validated['description'],
            'awarded_by'       => $request->user()->id,
        ]);

        $verb  = $validated['points'] > 0 ? 'awarded' : 'deducted';
        $abs   = abs($validated['points']);
        $newTotal = (int) HousePoint::where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->where('house_id', $house->id)
            ->sum('points');

        return response()->json([
            'message' => "{$abs} point(s) {$verb}.",
            'data'    => [
                'id'             => $entry->id,
                'house_id'       => $house->id,
                'house_name'     => $house->name,
                'category'       => $entry->category,
                'points'         => (int) $entry->points,
                'description'    => $entry->description,
                'new_total'      => $newTotal,
                'created_at'     => $entry->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /mobile/houses/{houseId}/points/{pointId}
     *
     * Remove a single points entry. Admin-only. Mirrors web HousePointController::destroy().
     */
    public function deletePoint(Request $request, int $houseId, int $pointId): JsonResponse
    {
        $this->assertAdmin($request);
        $schoolId = app('current_school_id');
        $house = House::where('school_id', $schoolId)->find($houseId);
        if (!$house) return response()->json(['error' => 'House not found.'], 404);

        $point = HousePoint::where('id', $pointId)
            ->where('school_id', $schoolId)
            ->where('house_id', $house->id)
            ->first();
        if (!$point) return response()->json(['error' => 'Point entry not found.'], 404);

        $point->delete();
        return response()->json(['message' => 'Removed.', 'id' => $pointId]);
    }

    /**
     * GET /mobile/houses/leaderboard
     *
     * Current-year standings with category breakdown per house. Sorted by
     * total points desc. Mirrors the web HouseLeaderboardController@index
     * response shape so admins on mobile see the same numbers as on the
     * Inertia page.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $schoolId = app('current_school_id');
        $yearId   = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $houses = House::where('school_id', $schoolId)
            ->with(['captain:id,first_name,last_name,admission_no', 'incharge:id,name'])
            ->withCount(['houseStudents as student_count' => function ($q) use ($yearId) {
                if ($yearId) $q->where('academic_year_id', $yearId);
            }])
            ->orderBy('name')
            ->get();

        // Aggregate points + per-category breakdown in one query
        $pointsAgg = $yearId
            ? HousePoint::where('school_id', $schoolId)
                ->where('academic_year_id', $yearId)
                ->selectRaw('house_id, category, SUM(points) as total')
                ->groupBy('house_id', 'category')
                ->get()
                ->groupBy('house_id')
            : collect();

        $rows = $houses->map(function (House $house) use ($pointsAgg) {
            $rows = $pointsAgg->get($house->id, collect());
            $byCategory = [];
            foreach (self::CATEGORIES as $cat) {
                $byCategory[$cat] = (int) ($rows->where('category', $cat)->first()?->total ?? 0);
            }
            return [
                'id'             => $house->id,
                'name'           => $house->name,
                'color'          => $house->color,
                'emblem'         => $house->emblem,
                'student_count'  => (int) ($house->student_count ?? 0),
                'total_points'   => array_sum($byCategory),
                'category_points'=> $byCategory,
                'captain' => $house->captain ? [
                    'id'           => $house->captain->id,
                    'name'         => trim($house->captain->first_name . ' ' . $house->captain->last_name),
                    'admission_no' => $house->captain->admission_no,
                ] : null,
                'incharge' => $house->incharge ? [
                    'id'   => $house->incharge->id,
                    'name' => $house->incharge->name,
                ] : null,
            ];
        })
        ->sortByDesc('total_points')
        ->values()
        ->map(function ($row, $idx) {
            $row['rank'] = $idx + 1;
            return $row;
        })
        ->values();

        return response()->json([
            'data' => [
                'houses' => $rows,
                'top_total' => $rows->max('total_points') ?? 0,
            ],
        ]);
    }

    /**
     * GET /mobile/houses/my-house
     *
     * Active student's house with their rank in the leaderboard, the
     * leaderboard's top score (so the UI can render a relative-progress
     * bar), the latest 20 point events for that house, and per-category
     * totals. Returns has_house=false if the student isn't assigned.
     */
    public function myHouse(Request $request): JsonResponse
    {
        $user      = $request->user();
        $studentId = $this->resolveStudentId($user, $request);

        $schoolId = app('current_school_id');
        $yearId   = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $student = $studentId
            ? Student::where('school_id', $schoolId)->find($studentId)
            : null;

        $assignment = ($student && $yearId)
            ? HouseStudent::with([
                  'house:id,name,color,emblem,incharge_staff_id,captain_student_id',
                  'house.captain:id,first_name,last_name,admission_no',
                  'house.incharge:id,name',
              ])
              ->where('school_id', $schoolId)
              ->where('academic_year_id', $yearId)
              ->where('student_id', $student->id)
              ->first()
            : null;

        if (!$assignment || !$assignment->house) {
            return response()->json([
                'data' => [
                    'has_house' => false,
                    'student' => $student ? [
                        'id'   => $student->id,
                        'name' => trim($student->first_name . ' ' . $student->last_name),
                    ] : null,
                ],
            ]);
        }

        $house = $assignment->house;

        // Per-category totals + grand total for this house
        $categoryRows = HousePoint::where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->where('house_id', $house->id)
            ->selectRaw('category, SUM(points) as total')
            ->groupBy('category')
            ->get();
        $byCategory = [];
        foreach (self::CATEGORIES as $cat) {
            $byCategory[$cat] = (int) ($categoryRows->where('category', $cat)->first()?->total ?? 0);
        }
        $myTotal = array_sum($byCategory);

        // Compute rank: count how many houses have strictly more points
        $allTotals = HousePoint::where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->selectRaw('house_id, SUM(points) as total')
            ->groupBy('house_id')
            ->pluck('total', 'house_id')
            ->toArray();
        $totalHouses = House::where('school_id', $schoolId)->count();
        $higher = collect($allTotals)->filter(fn($v) => (int) $v > $myTotal)->count();
        $rank   = $higher + 1;
        $topTotal = (int) (collect($allTotals)->max() ?? 0);

        // Latest 20 points events
        $recent = HousePoint::with('awardedBy:id,name')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->where('house_id', $house->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $studentCount = HouseStudent::where('school_id', $schoolId)
            ->where('academic_year_id', $yearId)
            ->where('house_id', $house->id)
            ->count();

        return response()->json([
            'data' => [
                'has_house' => true,
                'student' => [
                    'id'   => $student->id,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                ],
                'house' => [
                    'id'             => $house->id,
                    'name'           => $house->name,
                    'color'          => $house->color,
                    'emblem'         => $house->emblem,
                    'student_count'  => $studentCount,
                    'total_points'   => $myTotal,
                    'category_points'=> $byCategory,
                    'captain' => $house->captain ? [
                        'id'           => $house->captain->id,
                        'name'         => trim($house->captain->first_name . ' ' . $house->captain->last_name),
                        'admission_no' => $house->captain->admission_no,
                        'is_me'        => (int) $house->captain_student_id === (int) $student->id,
                    ] : null,
                    'incharge' => $house->incharge ? [
                        'id'   => $house->incharge->id,
                        'name' => $house->incharge->name,
                    ] : null,
                ],
                'rank' => [
                    'position'    => $rank,
                    'total_houses'=> $totalHouses,
                    'top_total'   => $topTotal,
                    'gap_to_top'  => max(0, $topTotal - $myTotal),
                ],
                'recent_events' => $recent->map(fn($r) => [
                    'id'          => $r->id,
                    'category'    => $r->category,
                    'points'      => (int) $r->points,
                    'description' => $r->description,
                    'awarded_by'  => $r->awardedBy?->name,
                    'created_at'  => $r->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
