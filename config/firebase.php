<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials Path
    |--------------------------------------------------------------------------
    |
    | The path to your Firebase service account JSON file.
    | By default, it looks for firebase-auth.json in your storage/app directory.
    |
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-auth.json')),

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | Optional. If left empty, the package will automatically extract
    | the project_id from your service account JSON file.
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID', ''),
];
