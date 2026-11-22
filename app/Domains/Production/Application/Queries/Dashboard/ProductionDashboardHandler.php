<?php

namespace Domains\Production\Application\Queries\Dashboard;

use Domains\Label\Application\LabelPlanService;
use Domains\Order\Application\Repositories\OrderRepositoryInterface;
use Domains\OrderReturn\Application\OrderReturnSummary;
use Domains\Production\Application\Data\ProductionDashboardData;
use Domains\Production\Application\ProductionCalculator;
use Domains\StoreGroup\Application\GroupInvoiceService;
use Shared\Bus\QueryHandlerInterface;

readonly class ProductionDashboardHandler implements QueryHandlerInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private LabelPlanService $labelPlans,
        private ProductionCalculator $production,
        private OrderReturnSummary $returnSummary,
        private GroupInvoiceService $groupInvoices,
    ) {}

    public function handle(object $message): ProductionDashboardData
    {
        assert($message instanceof ProductionDashboardQuery);
        $date = $message->date;
        $tab = $message->tab;
        $input = ['page' => $message->page];

        $orders = $this->orders->orders($date);
        $stores = $this->orders->stores();
        $products = $this->orders->products();
        $quantities = [];
        $totals = [];
        $productsById = $products->keyBy('id');
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $quantities[$item->product_id][$order->bakery_id] = ($quantities[$item->product_id][$order->bakery_id] ?? 0) + $item->quantity;
                $totals[$item->product_id] = ($totals[$item->product_id] ?? 0) + $item->quantity;
            }
        }
        $plan = $this->labelPlans->forQuantities($quantities, $products, $stores);
        $packaging = $plan['packaging'];
        $labelPackaging = $plan['labelPackaging'];
        $labels = $plan['labels'];
        $labelTotal = $plan['labelTotal'];
        $packageTotal = $plan['packageTotal'];
        $excludedStores = $plan['excludedStores'];
        $productionQuantities = [];
        $productionProductIds = [];
        foreach ($totals as $productId => $quantity) {
            $product = $productsById->get($productId);
            if ($product === null) {
                continue;
            }
            $name = $product->name;
            $productionQuantities[$name] = ($productionQuantities[$name] ?? 0) + $quantity;
            $productionProductIds[$name] = (int) $productId;
        }
        $production = $this->production->calculate($productionQuantities, $productionProductIds);
        $productionTotal = array_sum($totals);
        $orderedStoreIds = $orders->pluck('bakery_id')->all();
        $activeStores = $stores->where('is_active', true);
        $matrixStores = $stores->filter(fn ($store): bool => $store->is_active || in_array($store->id, $orderedStoreIds, true));
        $pageCount = max(1, (int) ceil($orders->count() / 20));
        $page = min((int) $input['page'], $pageCount);
        $visibleOrders = $orders->forPage($page, 20);
        $orderReturns = $this->returnSummary->forOrders($visibleOrders);
        $groups = $tab === 'orders' ? $this->groupInvoices->forOrders($orders) : [];

        return new ProductionDashboardData(compact('date', 'tab', 'orders', 'visibleOrders', 'orderReturns', 'groups', 'page', 'pageCount', 'activeStores', 'matrixStores', 'products', 'quantities', 'totals', 'packaging', 'labelPackaging', 'labels', 'labelTotal', 'packageTotal', 'excludedStores', 'productionTotal', 'production', 'orderedStoreIds'));
    }
}
