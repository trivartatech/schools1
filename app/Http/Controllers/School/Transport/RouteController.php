<?php

namespace App\Http\Controllers\School\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RouteController extends Controller
{
    public function index()
    {
        $routes = TransportRoute::tenant()
            ->withCount('stops')
            ->with(['vehicles' => fn($q) => $q->select('id', 'route_id', 'vehicle_number', 'vehicle_name')])
            ->orderBy('route_name')
            ->get();

        return Inertia::render('School/Transport/Routes/Index', [
            'routes' => $routes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_name'     => 'required|string|max:255',
            'route_code'     => 'required|string|max:50|unique:transport_routes,route_code,NULL,id,school_id,' . app('current_school_id'),
            'start_location' => 'nullable|string|max:255',
            'end_location'   => 'nullable|string|max:255',
            'distance'       => 'nullable|numeric|min:0',
            'estimated_time' => 'nullable|string|max:50',
            'status'         => 'in:active,inactive',
        ]);

        $route = TransportRoute::create([
            ...$validated,
            'school_id' => app('current_school_id'),
        ]);

        return back()->with('success', "Route '{$route->route_name}' created.");
    }

    public function update(Request $request, TransportRoute $route)
    {
        $this->authorizeTenant($route);

        $validated = $request->validate([
            'route_name'     => 'required|string|max:255',
            'route_code'     => 'required|string|max:50|unique:transport_routes,route_code,' . $route->id . ',id,school_id,' . app('current_school_id'),
            'start_location' => 'nullable|string|max:255',
            'end_location'   => 'nullable|string|max:255',
            'distance'       => 'nullable|numeric|min:0',
            'estimated_time' => 'nullable|string|max:50',
            'status'         => 'in:active,inactive',
        ]);

        $route->update($validated);
        return back()->with('success', 'Route updated.');
    }

    public function destroy(TransportRoute $route)
    {
        $this->authorizeTenant($route);
        $route->delete();
        return back()->with('success', 'Route deleted.');
    }

    // ─── PDF Export ────────────────────────────────────────────────────

    /**
     * GET /school/transport/routes/export-pdf
     * Stream a PDF listing all routes with their stops and stop fees.
     */
    public function exportPdf()
    {
        $schoolId = app('current_school_id');
        $school   = \App\Models\School::find($schoolId);

        $routes = TransportRoute::where('school_id', $schoolId)
            ->with([
                'stops' => fn ($q) => $q->orderBy('stop_order'),
                'vehicles:id,route_id,vehicle_number,vehicle_name',
            ])
            ->orderBy('route_name')
            ->get();

        $pdf = Pdf::loadView('pdf.transport-routes', [
            'school' => $school,
            'routes' => $routes,
            'generatedAt' => now()->format('d M Y, h:i A'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Transport-Routes-Stops.pdf');
    }

    // ─── Stop sub-resource ─────────────────────────────────────────────

    public function stops(TransportRoute $route)
    {
        $this->authorizeTenant($route);
        return response()->json($route->stops);
    }

    public function storeStop(Request $request)
    {
        $schoolId = app('current_school_id');

        $validated = $request->validate([
            'route_id'             => ['required', Rule::exists('transport_routes', 'id')->where('school_id', $schoolId)],
            'stop_name'            => 'required|string|max:255',
            'stop_code'            => 'nullable|string|max:50',
            'pickup_time'          => 'nullable|date_format:H:i',
            'drop_time'            => 'nullable|date_format:H:i',
            'distance_from_school' => 'nullable|numeric|min:0',
            'fee'                  => 'nullable|numeric|min:0',
            'stop_order'           => 'nullable|integer|min:0',
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
        ]);

        // Ensure the route belongs to this school
        $route = TransportRoute::where('id', $validated['route_id'])
            ->where('school_id', app('current_school_id'))
            ->firstOrFail();

        $stop = TransportStop::create([
            ...$validated,
            'school_id' => app('current_school_id'),
            'fee'       => $validated['fee'] ?? 0,
        ]);

        return back()->with('success', "Stop '{$stop->stop_name}' added.");
    }

    public function updateStop(Request $request, TransportStop $stop)
    {
        abort_unless($stop->school_id === app('current_school_id'), 403);

        $validated = $request->validate([
            'stop_name'            => 'required|string|max:255',
            'stop_code'            => 'nullable|string|max:50',
            'pickup_time'          => 'nullable|date_format:H:i',
            'drop_time'            => 'nullable|date_format:H:i',
            'distance_from_school' => 'nullable|numeric|min:0',
            'fee'                  => 'nullable|numeric|min:0',
            'stop_order'           => 'nullable|integer|min:0',
            'latitude'             => 'nullable|numeric|between:-90,90',
            'longitude'            => 'nullable|numeric|between:-180,180',
        ]);

        $stop->update([
            ...$validated,
            'fee' => $validated['fee'] ?? $stop->fee ?? 0,
        ]);

        return back()->with('success', 'Stop updated.');
    }

    public function destroyStop(TransportStop $stop)
    {
        abort_unless($stop->school_id === app('current_school_id'), 403);
        $stop->delete();
        return back()->with('success', 'Stop deleted.');
    }

    public function reorderStops(Request $request)
    {
        $request->validate([
            'stops'          => 'required|array',
            'stops.*.id'     => ['required', Rule::exists('transport_stops', 'id')->where('school_id', app('current_school_id'))],
            'stops.*.order'  => 'required|integer|min:0',
        ]);

        foreach ($request->stops as $item) {
            TransportStop::where('id', $item['id'])
                ->where('school_id', app('current_school_id'))
                ->update(['stop_order' => $item['order']]);
        }

        return response()->json(['success' => true]);
    }

    private function authorizeTenant(TransportRoute $route)
    {
        abort_unless($route->school_id === app('current_school_id'), 403);
    }
}
