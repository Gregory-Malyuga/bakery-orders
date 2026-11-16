<?php

namespace Domains\Bakery\Presentation\Http\Controllers;

use Domains\Bakery\Application\Commands\Create\CreateBakeryCommand;
use Domains\Bakery\Application\Commands\Delete\DeleteBakeryCommand;
use Domains\Bakery\Application\Commands\Update\UpdateBakeryCommand;
use Domains\Bakery\Application\Data\BakeryResource;
use Domains\Bakery\Application\Data\CreateBakeryData;
use Domains\Bakery\Application\Data\UpdateBakeryData;
use Domains\Bakery\Application\Queries\FindById\FindBakeryByIdQuery;
use Domains\Bakery\Application\Queries\ListAll\ListBakerysQuery;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Presentation\Http\Requests\ListBakerysRequest;
use Domains\Bakery\Presentation\Http\Requests\StoreBakeryRequest;
use Domains\Bakery\Presentation\Http\Requests\UpdateBakeryRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;
use Shared\Bus\QueryBusInterface;
use Spatie\LaravelData\PaginatedDataCollection;

class BakeryController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $commands,
        private readonly QueryBusInterface $queries,
    ) {}

    public function index(ListBakerysRequest $request): JsonResponse
    {
        /** @var LengthAwarePaginator<int, Bakery> $paginator */
        $paginator = $this->queries->ask(new ListBakerysQuery(
            filters: $request->toFilters(),
            sort: $request->toSort(),
            pagination: $request->toPagination(),
        ));

        return response()->json(BakeryResource::collect($paginator, PaginatedDataCollection::class));
    }

    public function show(int $id): JsonResponse
    {
        /** @var Bakery $record */
        $record = $this->queries->ask(new FindBakeryByIdQuery($id));

        return response()->json(BakeryResource::from($record));
    }

    public function store(StoreBakeryRequest $request): JsonResponse
    {
        $dto = CreateBakeryData::from($request);

        /** @var int $id */
        $id = $this->commands->dispatch(new CreateBakeryCommand(
            name: $dto->name,
        ));

        /** @var Bakery $record */
        $record = $this->queries->ask(new FindBakeryByIdQuery($id));

        return response()->json(BakeryResource::from($record), 201);
    }

    public function update(int $id, UpdateBakeryRequest $request): JsonResponse
    {
        $dto = UpdateBakeryData::from($request);

        $this->commands->dispatch(new UpdateBakeryCommand(
            id: $id,
            name: $dto->name,
        ));

        /** @var Bakery $record */
        $record = $this->queries->ask(new FindBakeryByIdQuery($id));

        return response()->json(BakeryResource::from($record));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->commands->dispatch(new DeleteBakeryCommand($id));

        return response()->json(null, 204);
    }
}
