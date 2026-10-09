<?php

return [
    // A relative path is taken from the app root: the queue worker doesn't run
    // from there, so a relative path only worked in artisan/tinker.
    'credentials' => (fn (?string $p) => $p && ! str_starts_with($p, '/') ? base_path($p) : $p)(
        env('FIREBASE_CREDENTIALS')
    ),
];
