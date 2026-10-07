<?php

use Domains\Bakery\Providers\BakeryServiceProvider;
use Domains\User\Providers\UserServiceProvider;
use Shared\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    UserServiceProvider::class,
    BakeryServiceProvider::class,
];
