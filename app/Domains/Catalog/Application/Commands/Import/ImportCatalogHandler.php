<?php

namespace Domains\Catalog\Application\Commands\Import;

use Domains\Catalog\Application\Repositories\TransactionRunnerInterface;
use Domains\Price\Application\Repositories\PriceCatalogImportRepositoryInterface;
use Domains\Product\Application\Repositories\ProductCatalogImportRepositoryInterface;
use Domains\Store\Application\Repositories\StoreCatalogImportRepositoryInterface;
use Shared\Bus\CommandHandlerInterface;

readonly class ImportCatalogHandler implements CommandHandlerInterface
{
    public function __construct(
        private TransactionRunnerInterface $transactions,
        private StoreCatalogImportRepositoryInterface $stores,
        private ProductCatalogImportRepositoryInterface $products,
        private PriceCatalogImportRepositoryInterface $prices,
    ) {}

    public function handle(object $message): ?int
    {
        assert($message instanceof ImportCatalogCommand);

        $this->transactions->run(function () use ($message): void {
            $storeIds = [];
            $productIds = [];
            foreach ($message->stores as $key => [$name, $address]) {
                $storeIds[$key] = $this->stores->firstOrCreate($name, $address);
            }
            foreach ($message->products as $key => $name) {
                $productIds[$key] = $this->products->firstOrCreate($name);
            }
            foreach ($message->rows as [$storeKey, $productKey, $kopecks]) {
                $this->prices->put($storeIds[$storeKey], $productIds[$productKey], $kopecks);
            }
        });

        return null;
    }
}
