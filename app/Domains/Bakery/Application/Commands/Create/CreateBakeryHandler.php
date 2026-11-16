<?php

namespace Domains\Bakery\Application\Commands\Create;

use Domains\Bakery\Application\Repositories\BakeryRepositoryInterface;
use Domains\Bakery\Domain\Events\BakeryCreated;
use Domains\Bakery\Domain\Models\Bakery;
use Shared\Bus\CommandHandlerInterface;

readonly class CreateBakeryHandler implements CommandHandlerInterface
{
    public function __construct(private BakeryRepositoryInterface $repository) {}

    public function handle(object $message): int
    {
        assert($message instanceof CreateBakeryCommand);

        /** @var Bakery $record */
        $record = $this->repository->create([
            'name' => $message->name,
        ]);

        BakeryCreated::dispatch($record);

        return $record->id;
    }
}
