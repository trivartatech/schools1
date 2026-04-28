<?php

namespace App\Http\Controllers\School\Reports;

use App\Http\Controllers\Controller;
use App\Models\AdminContact;
use App\Models\DailyReportDelivery;
use App\Models\DailyReportSetting;
use App\Services\DailyReportBroadcaster;
use App\Services\DailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class DailyMasterReportController extends Controller
{
    public function __construct(
        private DailyReportService $service,
        private DailyReportBroadcaster $broadcaster,
    ) {}

    /**
     * GET /school/reports/daily-master
     * Renders the master report page for the chosen date (default: today).
     */
    public function index(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $date = $this->resolveDate($request->query('date'));
        $mode = $request->query('mode') === 'weekly' ? 'weekly' : 'daily';

        if ($mode === 'weekly') {
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd   = $date->copy()->endOfWeek(Carbon::SUNDAY);
            $report = $this->service->forWeek($schoolId, $weekStart, $weekEnd, $academicYearId);
        } else {
            $report = $this->service->forDate($schoolId, $date, $academicYearId);
        }

        $settings = DailyReportSetting::forSchool($schoolId);
        $adminContacts = AdminContact::where('school_id', $schoolId)
            ->orderBy('id')->get(['id', 'name', 'phone', 'whatsapp_number']);

        $lastDelivery = DailyReportDelivery::where('school_id', $schoolId)
            ->where('report_date', $date->toDateString())
            ->latest('sent_at')
            ->first();

        $deliveries = DailyReportDelivery::where('school_id', $schoolId)
            ->where('report_date', $date->toDateString())
            ->with('adminContact:id,name')
            ->orderBy('id')
            ->get(['id', 'admin_contact_id', 'channel_used', 'to_number', 'sent_at', 'error']);

        return Inertia::render('School/Reports/DailyMasterReport', [
            'report'         => $report,
            'settings'       => $settings,
            'admin_contacts' => $adminContacts,
            'last_sent_at'   => $lastDelivery?->sent_at?->toIso8601String(),
            'deliveries'     => $deliveries,
        ]);
    }

    /**
     * POST /school/reports/daily-master/send
     * Manual trigger — runs the same broadcast logic the scheduled command uses.
     */
    public function send(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $date = $this->resolveDate($request->input('date'));
        $mode = $request->input('mode') === 'weekly' ? 'weekly' : 'daily';

        $contacts = AdminContact::where('school_id', $schoolId)->get();
        if ($contacts->isEmpty()) {
            return back()->with('warning', 'No admin numbers configured. Add contacts at /school/settings/admin-contacts first.');
        }

        $result = $this->broadcaster->broadcast($schoolId, $date, $mode, $academicYearId);

        $msg = "Report sent — WhatsApp: {$result['whatsapp']}, SMS: {$result['sms']}, failed: {$result['failed']}";
        return back()->with('success', $msg);
    }

    /**
     * GET /school/reports/daily-master/pdf
     * Generates and streams a PDF version of the report.
     */
    public function pdf(Request $request)
    {
        $schoolId       = app('current_school_id');
        $academicYearId = app()->bound('current_academic_year_id') ? app('current_academic_year_id') : null;

        $date = $this->resolveDate($request->query('date'));
        $mode = $request->query('mode') === 'weekly' ? 'weekly' : 'daily';

        $report = $mode === 'weekly'
            ? $this->service->forWeek(
                $schoolId,
                $date->copy()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfWeek(Carbon::SUNDAY),
                $academicYearId
            )
            : $this->service->forDate($schoolId, $date, $academicYearId);

        $school = \App\Models\School::find($schoolId);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.daily-master-pdf', [
            'report' => $report,
            'school' => $school,
        ])->setPaper('a4');

        $filename = "daily-report-{$date->format('Y-m-d')}.pdf";
        return $pdf->stream($filename);
    }

    /**
     * GET /school/reports/daily-master/pdf/{token}  (signed)
     * Public signed download for SMS/WhatsApp recipients — same content as pdf().
     */
    public function pdfSigned(Request $request, int $schoolId, string $date)
    {
        if (!URL::hasValidSignature($request)) abort(403);

        $school = \App\Models\School::findOrFail($schoolId);

        // Pull AY from school's currently-active year if available
        $academicYear = \App\Models\AcademicYear::where('school_id', $schoolId)
            ->current()->first();
        $academicYearId = $academicYear?->id;

        $dateCarbon = Carbon::parse($date);

        $report = $this->service->forDate($schoolId, $dateCarbon, $academicYearId);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.daily-master-pdf', [
            'report' => $report,
            'school' => $school,
        ])->setPaper('a4');

        return $pdf->stream("daily-report-{$date}.pdf");
    }

    private function resolveDate(?string $input): Carbon
    {
        if (!$input) return Carbon::today();
        try {
            return Carbon::parse($input)->startOfDay();
        } catch (\Throwable $e) {
            return Carbon::today();
        }
    }
}
