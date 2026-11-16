<?php

return [
    'elasticsearch' => env('HEALTH_CHECK_ELASTICSEARCH', true),
    'redis' => env('HEALTH_CHECK_REDIS', true),
];
