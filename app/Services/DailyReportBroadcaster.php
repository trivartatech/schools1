<?php

namespace App\Services;

use App\Models\AdminContact;
use App\Models\DailyReportDelivery;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Builds the daily report PDF + WhatsApp/SMS messages and dispatches to every
 * AdminContact for the school. WhatsApp is tried first; on failure (no
 * whatsapp_number, provider error) the recipient falls back to SMS.
 *
 * Each attempt is logged in daily_report_deliveries for the page to display.
 */
class DailyReportBroadcaster
{
    public function __construct(private DailyReportService $service) {}

    /**
     * Run the broadcast for one school+date.
     *
     * @return array{whatsapp:int, sms:int, failed:int, contacts:int, pdf_path:string|null}
     */
    public function broadcast(int $schoolId, Carbon $date, string $mode = 'daily', ?int $academicYearId = null): array
    {
        $school = School::find($schoolId);
        if (!$school) return ['whatsapp' => 0, 'sms' => 0, 'failed' => 0, 'contacts' => 0, 'pdf_path' => null];

        $contacts = AdminContact::where('school_id', $schoolId)->get();
        if ($contacts->isEmpty()) {
            Log::info("[DailyReport] School {$schoolId} has no admin_contacts — skipped.");
            return ['whatsapp' => 0, 'sms' => 0, 'failed' => 0, 'contacts' => 0, 'pdf_path' => null];
        }

        // Build report payload once
        if ($mode === 'weekly') {
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd   = $date->copy()->endOfWeek(Carbon::SUNDAY);
            $report = $this->service->forWeek($schoolId, $weekStart, $weekEnd, $academicYearId);
        } else {
            $report = $this->service->forDate($schoolId, $date, $academicYearId);
        }

        // Generate PDF once and save to disk
        $pdfPath = $this->generatePdf($schoolId, $date, $mode, $report, $school);

        // Build a long WhatsApp text and a short SMS text from the same report
        $whatsAppText = $this->buildWhatsAppMessage($report, $school);
        $signedPdfUrl = $this->buildSignedPdfUrl($schoolId, $date);
        $smsText      = $this->buildSmsMessage($report, $school, $signedPdfUrl);

        // Pre-fetch the SMS template (system seed) once
        $smsTemplate = \App\Models\CommunicationTemplate::where('school_id', $school->id)
            ->where('type', 'sms')
            ->where('slug', 'daily_report')
            ->where('is_active', true)
            ->first();

        $notifier = new NotificationService($school);

        $stats = ['whatsapp' => 0, 'sms' => 0, 'failed' => 0, 'contacts' => $contacts->count(), 'pdf_path' => $pdfPath];

        foreach ($contacts as $contact) {
            // No WhatsApp and no phone — skip with a logged warning
            if (empty($contact->whatsapp_number) && empty($contact->phone)) {
                $this->logDelivery($schoolId, $contact->id, $date, $mode, 'failed', null, $pdfPath, 'No phone or WhatsApp number on contact');
                $stats['failed']++;
                continue;
            }

            $sent = false;

            // 1. Try WhatsApp first if a whatsapp_number exists
            if (!empty($contact->whatsapp_number)) {
                try {
                    $waSuccess = $this->sendWhatsApp($notifier, $contact->whatsapp_number, $whatsAppText, $signedPdfUrl, $school);
                    if ($waSuccess) {
                        $this->logDelivery($schoolId, $contact->id, $date, $mode, 'whatsapp', $contact->whatsapp_number, $pdfPath);
                        $stats['whatsapp']++;
                        $sent = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning("[DailyReport] WhatsApp send failed for {$contact->whatsapp_number}: " . $e->getMessage());
                }
            }

            // 2. Fallback to SMS if WhatsApp didn't go OR contact only has phone.
            //    DLT-approved template + template_id are required by MSG91 v5/flow.
            if (!$sent && !empty($contact->phone)) {
                if (!$smsTemplate || empty($smsTemplate->template_id)) {
                    Log::warning("[DailyReport] No DLT-approved SMS template for school {$school->id} — cannot send to {$contact->phone}");
                } else {
                    try {
                        $data = [
                            'date'    => $date->format('d-M-Y'),
                            'caption' => mb_substr($smsText, 0, 200),
                            'link'    => $signedPdfUrl,
                            'app_name'=> $school->name,
                        ];
                        $smsOk = $notifier->sendSms(
                            $contact->phone,
                            $smsTemplate->content,
                            $smsTemplate->template_id,
                            null,
                            $data
                        );
                        if ($smsOk) {
                            $this->logDelivery($schoolId, $contact->id, $date, $mode, 'sms', $contact->phone, $pdfPath);
                            $stats['sms']++;
                            $sent = true;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("[DailyReport] SMS send failed for {$contact->phone}: " . $e->getMessage());
                    }
                }
            }

            if (!$sent) {
                $this->logDelivery($schoolId, $contact->id, $date, $mode, 'failed', $contact->phone ?? $contact->whatsapp_number, $pdfPath, 'Both channels failed');
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Render the report Blade to PDF and persist on disk.
     * Returns the relative path within storage/app.
     */
    private function generatePdf(int $schoolId, Carbon $date, string $mode, array $report, School $school): string
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.daily-master-pdf', [
            'report' => $report,
            'school' => $school,
        ])->setPaper('a4');

        $folder = "reports/daily-master/{$schoolId}";
        $filename = $mode === 'weekly'
            ? 'weekly-' . $date->format('o-\WW') . '.pdf'
            : $date->format('Y-m-d') . '.pdf';
        $relativePath = $folder . '/' . $filename;

        Storage::disk('local')->put($relativePath, $pdf->output());
        return $relativePath;
    }

    /**
     * Try to send the report PDF via WhatsApp. Two paths supported:
     *   A) Direct document message (preferred) — uses MSG91's WhatsApp media API
     *   B) Template-based message with the link as a parameter (fallback)
     *
     * Returns true on success, false on a soft failure that should fall back to SMS.
     */
    private function sendWhatsApp(NotificationService $notifier, string $recipient, string $caption, string $pdfLink, School $school): bool
    {
        $config = $school->settings['whatsapp'] ?? [];

        // No WhatsApp configured — bail and let SMS take over
        if (empty($config['api_key'])) return false;

        // Pragmatic approach: leverage existing template plumbing if a "daily_report"
        // template is set up; otherwise log a notice and let SMS fallback handle it.
        // (Free-form WhatsApp text is not allowed by Meta policy outside templates,
        // so attempting a non-template send would fail at the provider level.)
        $template = \App\Models\CommunicationTemplate::where('school_id', $school->id)
            ->where('type', 'whatsapp')
            ->where('slug', 'daily_report')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            // No template — cannot send WhatsApp. Caller will fall back to SMS.
            Log::info("[DailyReport] No 'daily_report' WhatsApp template — falling back to SMS for {$recipient}.");
            return false;
        }

        // Pass the PDF link + caption summary as template parameters. The school
        // admin controls the exact placement of these in the template body.
        $params = [
            'caption' => mb_substr($caption, 0, 1000),
            'link'    => $pdfLink,
            'date'    => now()->format('d-M-Y'),
        ];

        $orderedParams = $this->extractOrderedParams($template->content, $params);

        try {
            return $notifier->sendWhatsApp(
                $recipient,
                $template->template_id,
                $orderedParams,
                null,
                $template->language_code ?? 'en'
            );
        } catch (\Throwable $e) {
            Log::warning("[DailyReport] WhatsApp template send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mirror NotificationService::extractOrderedWhatsAppParams without making
     * the original method public — small helper kept private here.
     */
    private function extractOrderedParams(?string $templateContent, array $data): array
    {
        if (empty($templateContent)) return array_values($data);

        preg_match_all('/##([A-Za-z0-9_]+)##/', $templateContent, $matches);
        if (empty($matches[1])) return array_values($data);

        $out = [];
        foreach ($matches[1] as $key) {
            $lowerKey = strtolower($key);
            $out[] = (string) ($data[$lowerKey] ?? ($data[$key] ?? ''));
        }
        return $out;
    }

    private function buildSignedPdfUrl(int $schoolId, Carbon $date): string
    {
        return URL::temporarySignedRoute(
            'school.reports.daily-master.pdf-signed',
            now()->addDays(7),
            ['schoolId' => $schoolId, 'date' => $date->toDateString()]
        );
    }

    /**
     * Build a long WhatsApp-friendly text (< 4096 chars). Includes alerts,
     * attendance %, fees, expenses, net cash, admissions, dues, outlook.
     */
    private function buildWhatsAppMessage(array $report, School $school): string
    {
        $meta = $report['meta'] ?? [];
        $kpi  = $report['kpi'] ?? [];
        $isWeekly = ($meta['mode'] ?? 'daily') === 'weekly';

        $rupees = fn($n) => '₹' . number_format((float) $n, 0, '.', ',');
        $pct = fn($n) => round((float) $n, 1) . '%';

        $lines = [];
        $lines[] = ($isWeekly ? 'WEEKLY DIGEST' : 'DAILY REPORT') . ' — ' . $school->name;
        $lines[] = $meta['date_label'] ?? '';
        $lines[] = '';

        if (isset($kpi['attendance_pct'])) {
            $lines[] = 'Attendance: ' . $pct($kpi['attendance_pct']['value'] ?? 0);
        }
        if (isset($kpi['fee_total'])) {
            $lines[] = 'Fees collected: ' . $rupees($kpi['fee_total']['value'] ?? 0);
        }
        if (isset($kpi['expense_total'])) {
            $lines[] = 'Expenses: ' . $rupees($kpi['expense_total']['value'] ?? 0);
        }
        if (isset($kpi['net_position'])) {
            $sign = ($kpi['net_position']['value'] ?? 0) >= 0 ? '+' : '−';
            $lines[] = 'Net: ' . $sign . $rupees(abs($kpi['net_position']['value'] ?? 0));
        }
        if (isset($kpi['new_admissions'])) {
            $lines[] = 'New admissions: ' . (int) ($kpi['new_admissions']['value'] ?? 0);
        }

        // Alerts
        $alerts = $report['alerts'] ?? [];
        if (!empty($alerts)) {
            $lines[] = '';
            $lines[] = '⚠ Alerts:';
            foreach ($alerts as $alert) {
                $lines[] = '• ' . ($alert['label'] ?? '') . ': ' . ($alert['count'] ?? 0);
            }
        }

        // Highlights
        $hl = $report['highlights'] ?? [];
        if (!empty($hl['student_of_the_day'])) {
            $lines[] = '';
            $sod = $hl['student_of_the_day'];
            $lines[] = "★ Student of the day: {$sod['name']} ({$sod['streak']}d streak)";
        }
        if (!empty($hl['top_class'])) {
            $tc = $hl['top_class'];
            $cs = $tc['class'] . ($tc['section'] ? " - {$tc['section']}" : '');
            $lines[] = "★ Top class: {$cs} (" . $pct($tc['pct']) . ")";
        }

        // Pending dues
        if (isset($report['fees']['pending_dues'])) {
            $pd = $report['fees']['pending_dues'];
            $lines[] = '';
            $lines[] = 'Pending dues: ' . $rupees($pd['amount']) . ' across ' . (int) $pd['students'] . ' students';
        }

        // Outlook
        $out = $report['outlook'] ?? null;
        if ($out && (!empty($out['holidays']) || ($out['birthdays'] ?? 0) > 0)) {
            $lines[] = '';
            $lines[] = 'Tomorrow (' . ($out['date_label'] ?? '') . '):';
            if (!empty($out['holidays'])) {
                foreach ((array) $out['holidays'] as $h) {
                    $lines[] = '• ' . $h;
                }
            }
            if (!empty($out['birthdays'])) {
                $lines[] = '• Birthdays: ' . (int) $out['birthdays'];
            }
        }

        return mb_substr(implode("\n", $lines), 0, 4000);
    }

    /**
     * Short SMS message — must fit ~300 chars including the signed PDF link.
     */
    private function buildSmsMessage(array $report, School $school, string $pdfLink): string
    {
        $meta = $report['meta'] ?? [];
        $kpi  = $report['kpi'] ?? [];

        $r = fn($n) => round((float) $n / 1000, 1) . 'k';
        $pct = fn($n) => round((float) $n, 1);

        $att = $pct($kpi['attendance_pct']['value'] ?? 0);
        $fee = $r($kpi['fee_total']['value'] ?? 0);
        $exp = $r($kpi['expense_total']['value'] ?? 0);
        $net = $r($kpi['net_position']['value'] ?? 0);
        $adm = (int) ($kpi['new_admissions']['value'] ?? 0);
        $alertCount = count($report['alerts'] ?? []);

        $body = "{$school->name} — {$meta['date_label']}: Att {$att}%, Fee ₹{$fee}, Exp ₹{$exp}, Net ₹{$net}, Adm {$adm}";
        if ($alertCount > 0) $body .= ", {$alertCount} alerts";
        $body .= ". Report: {$pdfLink}";

        return mb_substr($body, 0, 480);
    }

    private function logDelivery(int $schoolId, int $contactId, Carbon $date, string $mode, string $channel, ?string $toNumber, ?string $pdfPath, ?string $error = null): void
    {
        DailyReportDelivery::create([
            'school_id'        => $schoolId,
            'admin_contact_id' => $contactId,
            'report_date'      => $date->toDateString(),
            'mode'             => $mode,
            'channel_used'     => $channel,
            'to_number'        => $toNumber,
            'sent_at'          => now(),
            'pdf_path'         => $pdfPath,
            'error'            => $error,
        ]);
    }
}
