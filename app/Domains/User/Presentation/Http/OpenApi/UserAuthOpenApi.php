<?php

namespace Domains\User\Presentation\Http\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth', description: 'Аутентификация и регистрация')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
)]
#[OA\Schema(
    schema: 'AuthUser',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Иван Иванов'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ivan@example.com'),
        new OA\Property(property: 'role', type: 'string', example: 'user'),
    ],
)]
#[OA\Schema(
    schema: 'AuthTokenResponse',
    properties: [
        new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
        new OA\Property(property: 'user', ref: '#/components/schemas/AuthUser'),
    ],
)]
#[OA\Post(
    path: '/api/auth/register',
    tags: ['Auth'],
    summary: 'Регистрация нового пользователя',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Иван Иванов'),
                new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'ivan@example.com'),
                new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'secret123'),
                new OA\Property(property: 'password_confirmation', type: 'string', example: 'secret123'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Пользователь создан, токен выдан',
            content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse'),
        ),
        new OA\Response(response: 422, description: 'Ошибка валидации (email занят, пароли не совпадают и т.д.)'),
    ],
)]
#[OA\Post(
    path: '/api/auth/login',
    tags: ['Auth'],
    summary: 'Вход',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ivan@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'secret123'),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Успешная аутентификация',
            content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse'),
        ),
        new OA\Response(response: 401, description: 'Неверный email или пароль'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ],
)]
#[OA\Post(
    path: '/api/auth/logout',
    tags: ['Auth'],
    summary: 'Выход (удаление текущего токена)',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Токен удалён',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Выход выполнен.'),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Не аутентифицирован'),
    ],
)]
class UserAuthOpenApi {}
