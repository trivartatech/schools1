<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ERP Edition
    |--------------------------------------------------------------------------
    |
    | Selects the product packaging tier at install time. Drives both the
    | seeder (which seeders to run, what to write into schools.features) and
    | the runtime gates (route 404s, sidebar/menu hiding) for the install.
    |
    | Valid values: full | standard | lite
    |
    */

    'edition' => env('ERP_EDITION', 'full'),

    /*
    |--------------------------------------------------------------------------
    | Edition → feature map
    |--------------------------------------------------------------------------
    |
    | The features dictionary written to schools.features for each edition
    | when a school row is first created. Add a new edition by adding a new
    | key here (and updating ERP_EDITION accordingly).
    |
    */

    'editions' => [
        'full'     => ['hostel' => true,  'transport' => true],
        'standard' => ['hostel' => false, 'transport' => true],
        'lite'     => ['hostel' => false, 'transport' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module list
    |--------------------------------------------------------------------------
    |
    | The set of modules whose access is gated by the edition / features flag.
    | Must stay in sync with the `module:<name>` middleware used on routes —
    | CheckModuleAccess only short-circuits with 404 for modules listed here.
    |
    */

    'modules' => ['hostel', 'transport'],

];
