<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Data\AuthTokenData;
use Domains\User\Application\Data\AuthUserData;
use Domains\User\Application\Data\LoginData;
use Domains\User\Application\Queries\FindByCredentials\FindUserByCredentialsQuery;
use Domains\User\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Shared\Bus\QueryBusInterface;

class LoginController extends Controller
{
    public function __construct(private readonly QueryBusInterface $queries) {}

    public function __invoke(LoginData $data): JsonResponse
    {
        /** @var User $user */
        $user = $this->queries->ask(new FindUserByCredentialsQuery($data->email, $data->password));

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(new AuthTokenData($token, AuthUserData::from($user)));
    }
}
