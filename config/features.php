<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Import Challenge JSON
    |--------------------------------------------------------------------------
    |
    | Toggles the "Import from JSON" workflow on the challenge creation form.
    | Useful for piping output from a local LLM into the form. Disable in
    | production to remove the surface area for arbitrary JSON injection.
    |
    */
    'import_challenge_json' => env('FEATURE_IMPORT_CHALLENGE_JSON', true),
];
