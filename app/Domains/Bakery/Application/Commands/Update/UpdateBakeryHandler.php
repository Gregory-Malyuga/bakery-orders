<?php

namespace Domains\Bakery\Application\Commands\Update;

use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Domain\Events\BakeryUpdated;
use Domains\Bakery\Domain\Models\Bakery;
use Shared\Bus\CommandHandlerInterface;

readonly class UpdateBakeryHandler implements CommandHandlerInterface
{
    public function __construct(private BakeryRepositoryInterface $repository) {}

    public function handle(object $message): null
    {
        assert($message instanceof UpdateBakeryCommand);

        /** @var Bakery $record */
        $record = $this->repository->findOrFail($message->id);
        $data = ['name' => $message->name];
        $updated = $this->repository->update($record, $data);

        BakeryUpdated::dispatch($updated);

        return null;
    }
}
