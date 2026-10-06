<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Commands\Register\RegisterCommand;
use Domains\User\Application\Data\AuthTokenData;
use Domains\User\Application\Data\AuthUserData;
use Domains\User\Application\Data\RegisterData;
use Domains\User\Application\Queries\FindById\FindUserByIdQuery;
use Domains\User\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;
use Shared\Bus\QueryBusInterface;

class RegisterController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $commands,
        private readonly QueryBusInterface $queries,
    ) {}

    public function __invoke(RegisterData $data): JsonResponse
    {
        $id = $this->commands->dispatch(new RegisterCommand($data->name, $data->email, $data->password));
        assert($id !== null);

        /** @var User $user */
        $user = $this->queries->ask(new FindUserByIdQuery($id));

        $token = $user->createToken('cp')->plainTextToken;

        return response()->json(new AuthTokenData($token, AuthUserData::from($user)), 201);
    }
}
