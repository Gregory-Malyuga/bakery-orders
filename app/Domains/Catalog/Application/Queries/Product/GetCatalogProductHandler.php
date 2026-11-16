<?php

namespace Domains\Catalog\Application\Queries\Product;

use Domains\Catalog\Application\Data\CatalogViewData;
use Domains\Catalog\Application\Repositories\CatalogReadRepositoryInterface;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Shared\Bus\QueryHandlerInterface;

readonly class GetCatalogProductHandler implements QueryHandlerInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CatalogReadRepositoryInterface $repository,
    ) {}

    public function handle(object $message): CatalogViewData
    {
        assert($message instanceof GetCatalogProductQuery);
        $product = $message->productId;
        $item = $this->orders->products()->firstWhere('id', $product);
        abort_if($item === null, 404);
        $stores = $this->orders->stores();
        $prices = [];
        $unavailable = $this->repository->unavailableStores($product);
        foreach ($stores as $store) {
            $prices[$store->id] = $this->orders->prices((int) $store->id, true)[$product] ?? null;
        }
        $history = $this->repository->priceOperations($product);
        $historyItems = $this->repository->priceOperationItems($history);
        $reversed = $this->repository->reversedOperationIds($history);


        return new CatalogViewData(compact('item', 'stores', 'prices', 'unavailable', 'history', 'historyItems', 'reversed'));
    }
}
