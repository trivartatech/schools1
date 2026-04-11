<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommunicationConfigController extends Controller
{
    public function smsConfig()
    {
        return Inertia::render('School/Communication/Config/Sms', [
            'config' => app('current_school')->settings['sms'] ?? [
                'gateway' => 'msg91',
                'sender_id' => '',
                'api_key' => '',
                'test_number' => '',
                'test_template_id' => '',
                'number_prefix' => '91'
            ]
        ]);
    }

    public function updateSmsConfig(Request $request)
    {
        $validated = $request->validate([
            'gateway'          => 'required|string',
            'sender_id'        => 'nullable|string',
            'api_key'          => 'required|string',
            'test_number'      => 'nullable|string|min:10',
            'test_template_id' => 'nullable|string',
            'number_prefix'    => 'nullable|string|max:5',
        ]);

        $school = app('current_school');
        $settings = $school->settings ?? [];
        $settings['sms'] = $validated;
        $school->update(['settings' => $settings]);
        return back()->with('success', 'SMS Configuration updated.');
    }

    public function sendTestSms(Request $request)
    {
        $school = app('current_school');
        $config = $school->settings['sms'] ?? [];
        
        if (empty($config['api_key'])) {
            return back()->with('error', 'API Key not configured.');
        }

        if (empty($config['test_number'])) {
            return back()->with('error', 'Test number not configured.');
        }

        $service = new \App\Services\NotificationService($school);
        $service->notifyTestSms($config['test_number'], auth()->id());

        return back()->with('success', 'Test SMS triggered. Check communication logs.');
    }

    public function whatsappConfig()
    {
        return Inertia::render('School/Communication/Config/WhatsApp', [
            'config' => app('current_school')->settings['whatsapp'] ?? [
                'provider' => 'msg91',
                'sender_id' => '',
                'api_key' => '',
                'identifier' => '',
                'test_number' => '',
                'test_template_id' => '',
                'number_prefix' => '91'
            ]
        ]);
    }

    public function updateWhatsappConfig(Request $request)
    {
        $validated = $request->validate([
            'provider'         => 'required|string',
            'sender_id'        => 'nullable|string',
            'api_key'          => 'required|string',
            'identifier'       => 'nullable|string',
            'test_number'      => 'nullable|string|min:10',
            'test_template_id' => 'nullable|string',
            'number_prefix'    => 'nullable|string|max:5',
        ]);

        $school = app('current_school');
        $settings = $school->settings ?? [];
        $settings['whatsapp'] = $validated;
        $school->update(['settings' => $settings]);
        return back()->with('success', 'WhatsApp Configuration updated.');
    }

    public function sendTestWhatsapp(Request $request)
    {
        $school = app('current_school');
        $config = $school->settings['whatsapp'] ?? [];
        
        if (empty($config['api_key'])) {
            return back()->with('error', 'API Key not configured.');
        }

        if (empty($config['test_number'])) {
            return back()->with('error', 'Test number not configured.');
        }

        if (empty($config['test_template_id'])) {
            return back()->with('error', 'Test template ID not configured.');
        }

        $service = new \App\Services\NotificationService($school);
        $service->notifyTestWhatsapp($config['test_number'], $config['test_template_id'], auth()->id());

        return back()->with('success', 'Test WhatsApp triggered. Check communication logs.');
    }

    public function notificationConfig()
    {
        return Inertia::render('School/Communication/Config/Notification', [
            'config' => app('current_school')->settings['notifications_v2'] ?? [
                'in_portal' => true,
                'push' => false,
                'email' => false,
                'attendance_notify_all' => false,
            ]
        ]);
    }

    public function updateNotificationConfig(Request $request)
    {
        $validated = $request->validate([
            'in_portal'             => 'required|boolean',
            'push'                  => 'required|boolean',
            'email'                 => 'required|boolean',
            'attendance_notify_all' => 'required|boolean',
        ]);

        $school = app('current_school');
        $settings = $school->settings ?? [];
        $settings['notifications_v2'] = $validated;
        $school->update(['settings' => $settings]);
        return back()->with('success', 'Notification Configuration updated.');
    }

    public function voiceConfig()
    {
        return Inertia::render('School/Communication/Config/Voice', [
            'config' => app('current_school')->settings['voice'] ?? [
                'provider' => 'exotel',
                'api_sid' => '',
                'api_key' => '',
                'api_token' => '',
                'subdomain' => 'api.exotel.com',
                'caller_id' => '',
                'test_number' => '',
                'intro_audio_path' => null
            ]
        ]);
    }

    public function updateVoiceConfig(Request $request)
    {
        $validated = $request->validate([
            'provider'    => 'required|string',
            'api_sid'     => 'required|string',
            'api_key'     => 'required|string',
            'api_token'   => 'required|string',
            'subdomain'   => 'nullable|string',
            'caller_id'   => 'required|string',
            'test_number' => 'nullable|string|min:10',
            'app_id'      => 'required|string',
        ]);

        $school   = app('current_school');
        $settings = $school->settings ?? [];

        $voiceSettings = $validated;

        if ($request->boolean('delete_intro_audio')) {
            // Delete the existing file from storage
            $existing = $settings['voice']['intro_audio_path'] ?? null;
            if ($existing) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($existing);
            }
            $voiceSettings['intro_audio_path'] = null;
        } elseif ($request->hasFile('intro_audio')) {
            // Delete old file before saving new one
            $existing = $settings['voice']['intro_audio_path'] ?? null;
            if ($existing) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($existing);
            }
            $path = $request->file('intro_audio')->store('voice_intros', 'public');
            $voiceSettings['intro_audio_path'] = $path;
        } else {
            // No change — keep existing path
            $voiceSettings['intro_audio_path'] = $settings['voice']['intro_audio_path'] ?? null;
        }

        $settings['voice'] = $voiceSettings;
        $school->update(['settings' => $settings]);
        return back()->with('success', 'Voice Configuration updated.');
    }

    public function sendTestVoice(Request $request)
    {
        $school = app('current_school');
        $config = $school->settings['voice'] ?? [];
        
        $testNumber = $request->test_number ?? $config['test_number'] ?? null;

        if (empty($config['api_sid'])) {
            return back()->with('error', 'Account SID not configured.');
        }

        if (empty($config['api_key'])) {
            return back()->with('error', 'API Key not configured.');
        }

        if (empty($testNumber)) {
            return back()->with('error', 'Test number not configured. Please enter a number in the test field.');
        }

        $service = new \App\Services\NotificationService($school);
        $service->notifyTestVoice($testNumber, auth()->id());

        return back()->with('success', 'Test Voice Call triggered. Check communication logs.');
    }
}
