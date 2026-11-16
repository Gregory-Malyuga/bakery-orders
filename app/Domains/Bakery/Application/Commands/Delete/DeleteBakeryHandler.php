<?php

namespace Domains\Bakery\Application\Commands\Delete;

use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Domain\Events\BakeryDeleted;
use Shared\Bus\CommandHandlerInterface;

readonly class DeleteBakeryHandler implements CommandHandlerInterface
{
    public function __construct(private BakeryRepositoryInterface $repository) {}

    public function handle(object $message): null
    {
        assert($message instanceof DeleteBakeryCommand);

        $record = $this->repository->findOrFail($message->id);
        $this->repository->delete($record);
        BakeryDeleted::dispatch($record);

        return null;
    }
}
