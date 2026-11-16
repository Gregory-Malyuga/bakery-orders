<?php

use Laravel\Octane\Octane;

return [

    'server' => env('OCTANE_SERVER', 'frankenphp'),

    'https' => env('OCTANE_HTTPS', false),

    'warm' => Octane::defaultServicesToWarm(),

    'flush' => [],

    'garbage' => 50,

    'max_execution_time' => 30,

];
