<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffPunchController extends Controller
{
    /**
     * Show the punch attendance page for the logged-in staff member.
     */
    public function index()
    {
        $school = School::findOrFail(app('current_school_id'));
        $staff  = Staff::where('user_id', auth()->id())
                       ->where('school_id', $school->id)
                       ->first();

        // If the logged-in user has no staff record, show page with a message
        if (! $staff) {
            return Inertia::render('School/Staff/Punch', [
                'staff'      => [
                    'id'          => null,
                    'name'        => auth()->user()->name,
                    'employee_id' => null,
                    'photo'       => null,
                ],
                'today'      => now()->toDateString(),
                'attendance' => null,
                'history'    => [],
                'geoFence'   => [
                    'lat'     => (float) $school->geo_fence_lat,
                    'lng'     => (float) $school->geo_fence_lng,
                    'radius'  => (int)   $school->geo_fence_radius,
                    'enabled' => $school->geo_fence_lat && $school->geo_fence_lng,
                ],
                'noStaffRecord' => true,
            ]);
        }

        $today = now()->toDateString();

        $attendance = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', $today)
            ->first();

        // Last 7 days history
        $history = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->orderByDesc('date')
            ->limit(7)
            ->get()
            ->map(fn ($r) => [
                'date'      => $r->date->format('Y-m-d'),
                'day'       => $r->date->format('D'),
                'status'    => $r->status,
                'check_in'  => $r->check_in,
                'check_out' => $r->check_out,
            ]);

        return Inertia::render('School/Staff/Punch', [
            'staff' => [
                'id'          => $staff->id,
                'name'        => auth()->user()->name,
                'employee_id' => $staff->employee_id,
                'photo'       => $staff->photo_url,
            ],
            'today'      => $today,
            'attendance' => $attendance ? [
                'status'    => $attendance->status,
                'check_in'  => $attendance->check_in,
                'check_out' => $attendance->check_out,
            ] : null,
            'history'       => $history,
            'noStaffRecord' => false,
            'geoFence' => [
                'lat'    => (float) $school->geo_fence_lat,
                'lng'    => (float) $school->geo_fence_lng,
                'radius' => (int)   $school->geo_fence_radius,
                'enabled' => $school->geo_fence_lat && $school->geo_fence_lng,
            ],
        ]);
    }

    /**
     * Clock-in: create attendance record with check_in time.
     */
    public function clockIn(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $school = School::findOrFail(app('current_school_id'));
        $staff  = Staff::where('user_id', auth()->id())
                       ->where('school_id', $school->id)
                       ->firstOrFail();

        // Geofence check
        if ($school->geo_fence_lat && $school->geo_fence_lng) {
            $distance = $this->haversine(
                $request->latitude, $request->longitude,
                $school->geo_fence_lat, $school->geo_fence_lng
            );

            if ($distance > $school->geo_fence_radius) {
                return back()->withErrors([
                    'geofence' => "You are {$this->formatDistance($distance)} away from school. Please punch within {$school->geo_fence_radius}m of the campus.",
                ]);
            }
        }

        $today = now()->toDateString();
        $now   = now()->format('H:i:s');

        // Honour the per-role / per-day-bucket "is working day?" toggle from
        // /school/settings/attendance-timings. Falls back to "weekday is
        // working, weekend is off" when the new structure is absent.
        if (! $school->isWorkingDay('staff')) {
            return back()->withErrors(['punch' => 'Today is not a working day for staff.']);
        }

        $existing = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return back()->withErrors(['punch' => 'You have already clocked in today.']);
        }

        // Late threshold honours the new attendance_timings settings shape and
        // falls back to legacy `settings.late_threshold` (default 09:30).
        $lateThreshold = $school->lateThresholdFor('staff');
        $status = Carbon::parse($now)->gt(Carbon::parse($lateThreshold)) ? 'late' : 'present';

        StaffAttendance::updateOrCreate(
            [
                'school_id' => $school->id,
                'staff_id'  => $staff->id,
                'date'      => $today,
            ],
            [
                'status'       => $status,
                'check_in'     => $now,
                'punch_in_lat' => $request->latitude,
                'punch_in_lng' => $request->longitude,
                'marked_by'    => auth()->id(),
            ]
        );

        $label = $status === 'late' ? 'Clocked in (marked late).' : 'Clocked in successfully!';

        return back()->with('success', $label);
    }

    /**
     * Clock-out: update check_out time on existing record.
     */
    public function clockOut(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $school = School::findOrFail(app('current_school_id'));
        $staff  = Staff::where('user_id', auth()->id())
                       ->where('school_id', $school->id)
                       ->firstOrFail();

        // Geofence check
        if ($school->geo_fence_lat && $school->geo_fence_lng) {
            $distance = $this->haversine(
                $request->latitude, $request->longitude,
                $school->geo_fence_lat, $school->geo_fence_lng
            );

            if ($distance > $school->geo_fence_radius) {
                return back()->withErrors([
                    'geofence' => "You are {$this->formatDistance($distance)} away from school. Please punch within {$school->geo_fence_radius}m of the campus.",
                ]);
            }
        }

        $today = now()->toDateString();

        $attendance = StaffAttendance::where('school_id', $school->id)
            ->where('staff_id', $staff->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendance || ! $attendance->check_in) {
            return back()->withErrors(['punch' => 'You must clock in before clocking out.']);
        }

        if ($attendance->check_out) {
            return back()->withErrors(['punch' => 'You have already clocked out today.']);
        }

        $attendance->update([
            'check_out'     => now()->format('H:i:s'),
            'punch_out_lat' => $request->latitude,
            'punch_out_lng' => $request->longitude,
        ]);

        return back()->with('success', 'Clocked out successfully!');
    }

    /**
     * Save geofence configuration for the school.
     */
    public function saveGeoFence(Request $request)
    {
        $request->validate([
            'geo_fence_lat'    => 'required|numeric|between:-90,90',
            'geo_fence_lng'    => 'required|numeric|between:-180,180',
            'geo_fence_radius' => 'required|integer|min:50|max:5000',
        ]);

        $school = School::findOrFail(app('current_school_id'));
        $school->update($request->only('geo_fence_lat', 'geo_fence_lng', 'geo_fence_radius'));

        return back()->with('success', 'Geofence updated successfully.');
    }

    /**
     * Haversine distance in metres between two lat/lng points.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // Earth radius in metres

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function formatDistance(float $metres): string
    {
        return $metres >= 1000
            ? round($metres / 1000, 1) . ' km'
            : round($metres) . 'm';
    }
}
