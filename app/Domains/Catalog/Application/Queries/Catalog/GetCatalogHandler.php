<?php

namespace Domains\Catalog\Application\Queries\Catalog;

use Domains\Catalog\Application\Data\CatalogPageData;
use Domains\Catalog\Application\Repositories\CatalogReadRepositoryInterface;
use Domains\Order\Application\OrderCutoff;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\StoreAccount\Application\Queries\ListAll\ListStoreAccountsHandler;
use Domains\StoreAccount\Application\Queries\ListAll\ListStoreAccountsQuery;
use Shared\Bus\QueryHandlerInterface;

readonly class GetCatalogHandler implements QueryHandlerInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ListStoreAccountsHandler $storeAccounts,
        private OrderCutoff $cutoff,
        private CatalogReadRepositoryInterface $repository,
    ) {}

    public function handle(object $message): CatalogPageData
    {
        assert($message instanceof GetCatalogQuery);

        $input = [
            'store' => $message->storeId,
            'tab' => $message->tab,
            'page' => $message->page,
            'search' => $message->search,
        ];
        $stores = $this->orders->stores();
        $products = $this->orders->products();
        $tab = $input['tab'];
        $selectedStore = (int) ($input['store'] ?? $stores->first()?->id);
        abort_if($selectedStore && $this->orders->store($selectedStore) === null, 404);
        $accounts = $this->storeAccounts->handle(new ListStoreAccountsQuery)->accounts;
        $search = trim($input['search']);
        if ($tab === 'accounts' && $search !== '') {
            /** @var list<int> $matchingStoreIds */
            $matchingStoreIds = $stores
                ->filter(fn ($store): bool => mb_stripos($store->name, $search) !== false)
                ->pluck('id')
                ->all();
            $accounts = array_values(
                array_filter(
                    $accounts,
                    fn (array $account): bool => count(
                        array_intersect(
                            array_map('intval', $account['store_ids']),
                            $matchingStoreIds,
                        ),
                    ) > 0,
                ),
            );
        }
        $storeOwners = $tab === 'accounts' ? $this->repository->storeOwners() : [];
        $perPage = $tab === 'accounts' ? 8 : 20;
        $count = $tab === 'accounts' ? count($accounts) : $stores->count();
        $pageCount = max(1, (int) ceil($count / $perPage));
        $page = min((int) $input['page'], $pageCount);
        $visibleStores = $stores->forPage($page, $perPage);
        $visibleAccounts = array_slice($accounts, ($page - 1) * $perPage, $perPage);
        $unavailableProducts =
            $selectedStore && $tab === 'prices'
                ? $this->repository->unavailableProducts($selectedStore)
                : [];

        return new CatalogPageData(
            $stores,
            $visibleStores,
            $products,
            $selectedStore,
            $accounts,
            $visibleAccounts,
            $storeOwners,
            $unavailableProducts,
            $tab,
            $page,
            $pageCount,
            $search,
            $selectedStore ? $this->orders->prices($selectedStore, true, false) : [],
            $selectedStore ? $this->orders->minimums($selectedStore) : [],
            $this->cutoff->time(),
        );
    }
}
