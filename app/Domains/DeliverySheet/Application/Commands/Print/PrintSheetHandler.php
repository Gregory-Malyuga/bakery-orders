<?php

namespace Domains\DeliverySheet\Application\Commands\Print;

use Domains\DeliverySheet\Application\DeliverySheetService;
use Domains\DeliverySheet\Application\Repositories\DeliverySheetRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class PrintSheetHandler implements CommandHandlerInterface
{
    public function __construct(
        private DeliverySheetService $service,
        private DeliverySheetRepositoryInterface $repository,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof PrintSheetCommand);

        $source = $this->repository->sheet($message->sheetId);
        abort_if($source === null, 404);

        /**
         * @var array{date:string,route:string,driver:string,salary_kopecks?:int|null,
         *     bonus_kopecks?:int|null,rows:list<array{store_id:int,due:int,cashless?:bool}>} $snapshot
         */
        $snapshot = json_decode((string) $source->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $sheet = $this->service->sheetForPrint($snapshot);

        return $this->repository->createPrint($sheet['date'], $message->sheetId, $sheet);
    }
}
