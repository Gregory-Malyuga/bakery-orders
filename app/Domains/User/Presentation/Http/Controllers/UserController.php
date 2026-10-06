<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Commands\Create\CreateUserCommand;
use Domains\User\Application\Commands\Delete\DeleteUserCommand;
use Domains\User\Application\Commands\Update\UpdateUserCommand;
use Domains\User\Application\Data\CreateUserData;
use Domains\User\Application\Data\UpdateUserData;
use Domains\User\Application\Data\UserResource;
use Domains\User\Application\Queries\FindById\FindUserByIdQuery;
use Domains\User\Application\Queries\ListAll\ListUsersQuery;
use Domains\User\Domain\Models\User;
use Domains\User\Presentation\Http\Requests\ListUsersRequest;
use Domains\User\Presentation\Http\Requests\StoreUserRequest;
use Domains\User\Presentation\Http\Requests\UpdateUserRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;
use Shared\Bus\QueryBusInterface;
use Spatie\LaravelData\PaginatedDataCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $commands,
        private readonly QueryBusInterface $queries,
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        /** @var LengthAwarePaginator<int, User> $paginator */
        $paginator = $this->queries->ask(new ListUsersQuery(
            filters: $request->toFilters(),
            sort: $request->toSort(),
            pagination: $request->toPagination(),
        ));

        return response()->json(UserResource::collect($paginator, PaginatedDataCollection::class));
    }

    public function show(int $id): JsonResponse
    {
        /** @var User $record */
        $record = $this->queries->ask(new FindUserByIdQuery($id));

        return response()->json(UserResource::from($record));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $dto = CreateUserData::from($request);

        /** @var User|null $actor */
        $actor = $request->user();

        $id = $this->commands->dispatch(new CreateUserCommand(
            name: $dto->name,
            email: $dto->email,
            password: $dto->password,
            role: $dto->role,
            actor: $actor,
        ));
        assert($id !== null);

        /** @var User $record */
        $record = $this->queries->ask(new FindUserByIdQuery($id));

        return response()->json(UserResource::from($record), 201);
    }

    public function update(int $id, UpdateUserRequest $request): JsonResponse
    {
        $dto = UpdateUserData::from($request);

        /** @var User|null $actor */
        $actor = $request->user();

        $this->commands->dispatch(new UpdateUserCommand(
            id: $id,
            name: $dto->name,
            email: $dto->email,
            password: $dto->password,
            role: $dto->role,
            actor: $actor,
        ));

        /** @var User $record */
        $record = $this->queries->ask(new FindUserByIdQuery($id));

        return response()->json(UserResource::from($record));
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();

        $this->commands->dispatch(new DeleteUserCommand($id, $actor));

        return response()->json(null, 204);
    }
}
