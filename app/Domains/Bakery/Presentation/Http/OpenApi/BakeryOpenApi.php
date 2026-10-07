<?php

namespace Domains\Bakery\Presentation\Http\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Bakerys', description: 'Bakery management')]
#[OA\Schema(
    schema: 'Bakery',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Get(
    path: '/api/v1/bakeries',
    tags: ['Bakerys'],
    summary: 'List Bakerys',
    responses: [new OA\Response(response: 200, description: 'Paginated list')],
)]
#[OA\Post(
    path: '/api/v1/bakeries',
    tags: ['Bakerys'],
    summary: 'Create Bakery',
    responses: [new OA\Response(response: 201, description: 'Created')],
)]
#[OA\Get(
    path: '/api/v1/bakeries/{id}',
    tags: ['Bakerys'],
    summary: 'Get Bakery by ID',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Success'),
        new OA\Response(response: 404, description: 'Not found'),
    ],
)]
#[OA\Put(
    path: '/api/v1/bakeries/{id}',
    tags: ['Bakerys'],
    summary: 'Update Bakery',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Updated'),
        new OA\Response(response: 404, description: 'Not found'),
    ],
)]
#[OA\Delete(
    path: '/api/v1/bakeries/{id}',
    tags: ['Bakerys'],
    summary: 'Delete Bakery',
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [new OA\Response(response: 204, description: 'Deleted')],
)]
class BakeryOpenApi {}
