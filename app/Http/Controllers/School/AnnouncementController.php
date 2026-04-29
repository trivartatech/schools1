<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Models\CommunicationTemplate;
use App\Models\CourseClass;
use App\Services\NotificationService;
use App\Services\BroadcastService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    protected $broadcastService;

    public function __construct(BroadcastService $broadcastService)
    {
        $this->broadcastService = $broadcastService;
    }

    public function index()
    {
        $announcements = Announcement::with(['sender', 'template'])
            ->where('school_id', app('current_school_id'))
            ->latest()
            ->paginate(10);

        $classes = CourseClass::where('school_id', app('current_school_id'))
            ->with(['sections' => fn($q) => $q->forCurrentYear()])
            ->orderBy('numeric_value')->orderBy('name')
            ->get();

        $templates = CommunicationTemplate::where('school_id', app('current_school_id'))
            ->whereIn('type', ['voice', 'sms', 'whatsapp'])
            ->where('is_active', true)
            ->get();

        return Inertia::render('School/Communication/Announcements/Index', [
            'announcements' => $announcements,
            'classes'       => $classes,
            'templates'     => $templates,
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Announcement Store Request:', $request->except(['audio']));

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title'                      => 'required|string|max:255',
            'delivery_method'            => 'required|in:voice,sms,whatsapp',
            'audience_type'              => 'required|in:school,class,section,employee,individual',
            'audience_ids'               => 'nullable|array',
            'communication_template_id'  => 'required_if:delivery_method,sms,whatsapp|nullable|exists:communication_templates,id',
            'scheduled_at'               => 'nullable|date|after:now',
            'audio'                      => [
                'nullable',
                'file',
                'mimes:mp3,wav,ogg,webm',
                'max:5120',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->delivery_method === 'voice' && !$value && !$request->communication_template_id) {
                        $fail('Please provide either an audio recording or a voice template.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            Log::warning('Announcement Validation Failed:', [
                'errors' => $validator->errors()->toArray(),
                'input'  => $request->except(['audio']),
            ]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $path    = null;
        $mp3Path = null;

        if ($request->hasFile('audio')) {
            // Store original file (webm/mp3/wav)
            $path = $request->file('audio')->store('announcements', 'public');

            // Convert webm/ogg → WAV for browser playback and Exotel compatibility
            $mimeType     = $request->file('audio')->getMimeType();
            $needsConvert = in_array($mimeType, ['audio/webm', 'audio/ogg', 'video/webm'])
                || str_ends_with(strtolower($path), '.webm')
                || str_ends_with(strtolower($path), '.ogg');

            if ($needsConvert) {
                $mp3Path = $this->convertToWav($path);
            }
        }

        $announcement = Announcement::create([
            'school_id'                 => app('current_school_id'),
            'sender_id'                 => auth()->id(),
            'title'                     => $request->title,
            'delivery_method'           => $request->delivery_method,
            'audience_type'             => $request->audience_type,
            'audience_ids'              => $request->audience_ids,
            'communication_template_id' => $request->communication_template_id,
            'audio_path'                => $path,
            'mp3_path'                  => $mp3Path,
            'scheduled_at'              => $request->scheduled_at,
            'is_broadcasted'            => false,
        ]);

        $message = $announcement->scheduled_at
            ? 'Announcement scheduled for ' . $announcement->scheduled_at->format('M d, Y H:i')
            : 'Announcement saved successfully.';

        return to_route('school.communication.announcements.index')->with('success', $message);
    }

    /**
     * Locate the ffmpeg binary on this host. Returns null if not found.
     */
    protected function locateFfmpeg(): ?string
    {
        $isWindows = stripos(PHP_OS, 'WIN') === 0;

        $candidates = $isWindows
            ? ['C:/ffmpeg/bin/ffmpeg.exe', 'C:/Program Files/ffmpeg/bin/ffmpeg.exe', 'C:/ProgramData/chocolatey/bin/ffmpeg.exe']
            : ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) return $candidate;
        }

        $cmd = $isWindows ? 'where ffmpeg 2>NUL' : 'which ffmpeg 2>/dev/null';
        @exec($cmd, $out);
        if (!empty($out[0]) && file_exists(trim($out[0]))) {
            return trim($out[0]);
        }

        return null;
    }

    /**
     * Convert a storage-relative audio path to WAV (8kHz, mono, PCM).
     * Exotel telephony requires 8kHz sample rate, mono, 16-bit PCM WAV.
     * Returns the new wav storage-relative path, or null on failure.
     */
    protected function convertToWav(string $storagePath): ?string
    {
        $absolutePath = storage_path('app/public/' . $storagePath);

        $ffmpeg = $this->locateFfmpeg();
        if (!$ffmpeg) {
            Log::warning('🔇 [FFmpeg] Not found — skipping WAV conversion. Install: apt-get install ffmpeg (Linux) or choco install ffmpeg (Windows)');
            return null;
        }

        $wavTmp = sys_get_temp_dir() . '/ann_' . uniqid() . '.wav';
        $cmd    = sprintf(
            '%s -y -i %s -ar 8000 -ac 1 -acodec pcm_s16le -f wav %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($absolutePath),
            escapeshellarg($wavTmp)
        );

        exec($cmd, $cmdOut, $exitCode);

        if ($exitCode !== 0 || !file_exists($wavTmp)) {
            Log::error('🔴 [FFmpeg] Conversion failed (exit ' . $exitCode . '): ' . implode(' | ', $cmdOut));
            return null;
        }

        $wavPath = 'announcements/' . pathinfo($storagePath, PATHINFO_FILENAME) . '.wav';
        Storage::disk('public')->put($wavPath, file_get_contents($wavTmp));
        @unlink($wavTmp);

        Log::info("🎵 [Store] WAV saved: {$wavPath}");
        return $wavPath;
    }

    public function broadcast(Announcement $announcement)
    {
        if ($announcement->school_id !== app('current_school_id')) {
            abort(403);
        }

        if ($announcement->is_broadcasted) {
            return to_route('school.communication.announcements.index')->with('error', 'Announcement already broadcasted.');
        }

        try {
            $this->broadcastService->broadcast($announcement);
            return to_route('school.communication.announcements.index')->with('success', 'Broadcast completed successfully.');
        } catch (\Throwable $e) {
            return to_route('school.communication.announcements.index')->with('error', 'Broadcast failed: ' . $e->getMessage());
        }
    }
}
