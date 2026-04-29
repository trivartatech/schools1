<?php

/*
|--------------------------------------------------------------------------
| School Identity — sourced from .env, exposed via config()
|--------------------------------------------------------------------------
|
| GenericSchoolSeeder reads these values via config('school.*') so that
| seeding works correctly even after `php artisan config:cache` has been
| run. (env() returns null inside cached configs in non-config contexts —
| this file bridges the .env values into the runtime config tree.)
|
| Operators fill these via school-setup.xlsx (which the bootstrap.sh
| converts to .env) or by editing .env directly.
|
*/

return [

    // ── Core identity ──────────────────────────────────────────────────
    'name'           => env('SCHOOL_NAME', 'School'),
    'slug'           => env('SCHOOL_SLUG', 'school'),
    'code'           => env('SCHOOL_CODE', 'SCH001'),
    'board'          => env('SCHOOL_BOARD', 'CBSE'),
    'email'          => env('SCHOOL_EMAIL', ''),
    'phone'          => env('SCHOOL_PHONE', ''),
    'address'        => env('SCHOOL_ADDRESS', ''),
    'city'           => env('SCHOOL_CITY', ''),
    'state'          => env('SCHOOL_STATE', ''),
    'pincode'        => env('SCHOOL_PINCODE', ''),
    'website'        => env('SCHOOL_WEBSITE', ''),
    'principal_name' => env('SCHOOL_PRINCIPAL_NAME', 'Principal'),
    'timezone'       => env('SCHOOL_TIMEZONE', 'Asia/Kolkata'),
    'currency'       => env('SCHOOL_CURRENCY', 'INR'),
    'language'       => env('SCHOOL_LANGUAGE', 'en'),

    // ── Parent organization (trust) ────────────────────────────────────
    'organization' => [
        'name'    => env('ORG_NAME', 'Trust'),
        'slug'    => env('ORG_SLUG', 'trust'),
        'email'   => env('ORG_EMAIL', ''),
        'website' => env('ORG_WEBSITE', ''),
    ],

    // ── Initial admin users (created by GenericSchoolSeeder) ───────────
    'admins' => [
        'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
        'admin_email'       => env('ADMIN_EMAIL', 'admin@example.com'),
        'principal_email'   => env('PRINCIPAL_EMAIL', 'principal@example.com'),
        'default_password'  => env('DEFAULT_PASSWORD', 'ChangeMe@2026'),
    ],

];
