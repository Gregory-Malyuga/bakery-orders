<?php

use Elastic\Elasticsearch\Client;
use Laravel\Octane\Octane;
use Shared\Elasticsearch\EsTruncationBag;

return [

    'server' => env('OCTANE_SERVER', 'frankenphp'),

    'https' => env('OCTANE_HTTPS', false),

    /*
     * Warm at worker boot so the first request pays no cold-start cost.
     * Keep Octane's framework defaults (auth, cache, db, view, ...) and add ours.
     */
    'warm' => [
        ...Octane::defaultServicesToWarm(),
        Client::class,
    ],

    /*
     * Reset these singletons between requests to prevent state leaking across requests.
     * EsTruncationBag carries a boolean flag that must start clean on every request.
     */
    'flush' => [
        EsTruncationBag::class,
    ],

    'garbage' => 50,

    'max_execution_time' => 30,

];
