<?php

namespace Shared\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Laravel App API',
    description: 'API',
    contact: new OA\Contact(email: 'api@example.com'),
)]
#[OA\Server(
    url: '/',
    description: 'API сервер',
)]
class ApiInfo {}
