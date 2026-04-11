<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class MobileQrController extends Controller
{
    public function index()
    {
        $school  = app('current_school');
        $request = request();

        // Build the canonical school URL — used inside the QR code payload.
        // Priority: 1) custom domain  2) slug subdomain (production only)  3) request host
        $appUrl    = rtrim(config('app.url'), '/');
        $isLocal   = app()->environment('local');

        // If school has a custom domain stored in settings, use that
        $settings      = $school->settings ?? [];
        $customDomain  = $settings['custom_domain'] ?? null;

        if ($customDomain) {
            $schoolUrl = 'https://' . preg_replace('#^https?://#', '', $customDomain);
        } elseif (!$isLocal && $school->slug) {
            // In production, derive subdomain URL: slug.host
            $parsed    = parse_url($appUrl);
            $host      = $parsed['host'] ?? 'yourerp.in';
            $schoolUrl = 'https://' . $school->slug . '.' . $host;
        } else {
            // Local / tunnel environments — use the actual request URL
            $schoolUrl = $request->getSchemeAndHttpHost();
        }

        // Deep-link payload formats
        $baseUrl   = $isLocal ? $request->getSchemeAndHttpHost() : $appUrl;
        $deepLink  = "educonnect://school?url=" . rawurlencode($schoolUrl);
        $httpsLink = $baseUrl . '/school/join?url=' . rawurlencode($schoolUrl);

        return Inertia::render('School/Settings/MobileQR', [
            'school'     => $school,
            'schoolUrl'  => $schoolUrl,
            'deepLink'   => $deepLink,
            'httpsLink'  => $httpsLink,
            'appStoreUrl'    => config('app.mobile_app_store_url',   'https://apps.apple.com/app/educonnect/id0000000000'),
            'playStoreUrl'   => config('app.mobile_play_store_url',  'https://play.google.com/store/apps/details?id=in.trivarta.educonnect'),
        ]);
    }
}
