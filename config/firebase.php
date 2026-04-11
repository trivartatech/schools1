<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Service Account Credentials
    |--------------------------------------------------------------------------
    |
    | Path to the Firebase service account JSON file.
    | Download from: Firebase Console → Project Settings → Service Accounts
    |                → Generate new private key
    |
    | Set FIREBASE_CREDENTIALS in .env to the absolute path of the JSON file,
    | or place it at storage/app/firebase-credentials.json
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
];
