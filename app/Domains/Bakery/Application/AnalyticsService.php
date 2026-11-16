<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Domain\Models\Order;
use Illuminate\Support\Facades\DB;

/** @phpstan-type MetricRow array{label:string,orders:int,pieces:int,returned_pieces:int,gross:int,returns:int,net:int,return_share:float} */
class AnalyticsService
{
    public function __construct(private readonly OrderReturnSummary $returns) {}

    /** @return array{summary:MetricRow,days:list<MetricRow>,stores:list<MetricRow>,products:list<MetricRow>,missing_stores:array<int,string>,updated_at:string} */
    public function build(string $from, string $to, ?int $storeId = null, ?int $productId = null): array
    {
        $query = Order::query()->with('items')->whereBetween('delivery_date', [$from, $to])->where('status', 'accepted');
        if ($storeId !== null) {
            $query->where('bakery_id', $storeId);
        }
        $orders = $query->orderBy('delivery_date')->orderBy('bakery_id')->get();
        $returns = $this->returns->forOrders($orders);
        $summary = $this->bucket('Всего');
        $days = [];
        $stores = [];
        $products = [];
        foreach ($orders as $order) {
            $return = $returns[$order->id] ?? ['quantities' => [], 'extras' => [], 'amount' => 0];
            $date = $order->delivery_date->toDateString();
            foreach ($order->items as $item) {
                if ($productId !== null && (int) $item->product_id !== $productId) {
                    continue;
                }
                $returned = $return['quantities'][$item->product_id] ?? 0;
                $amount = $returned * (int) $item->unit_kopecks;
                $this->add($summary, (int) $order->id, (int) $item->quantity, $returned, (int) $item->total_kopecks, $amount);
                $this->addTo($days, $date, $date, (int) $order->id, (int) $item->quantity, $returned, (int) $item->total_kopecks, $amount);
                $this->addTo($stores, (string) $order->bakery_id, $order->store_name, (int) $order->id, (int) $item->quantity, $returned, (int) $item->total_kopecks, $amount);
                $this->addTo($products, (string) $item->product_id, $item->product_name, (int) $order->id, (int) $item->quantity, $returned, (int) $item->total_kopecks, $amount);
            }
            foreach ($return['extras'] as $extra) {
                if ($productId !== null && $extra['product_id'] !== $productId) {
                    continue;
                }
                $amount = $extra['quantity'] * $extra['unit_kopecks'];
                $this->add($summary, (int) $order->id, 0, $extra['quantity'], 0, $amount);
                $this->addTo($days, $date, $date, (int) $order->id, 0, $extra['quantity'], 0, $amount);
                $this->addTo($stores, (string) $order->bakery_id, $order->store_name, (int) $order->id, 0, $extra['quantity'], 0, $amount);
                $this->addTo($products, (string) $extra['product_id'], $extra['name'], (int) $order->id, 0, $extra['quantity'], 0, $amount);
            }
        }
        $orderedStoreIds = array_map('intval', array_keys($stores));
        $missing = DB::table('bakeries')->where('is_active', true);
        if ($storeId !== null) {
            $missing->where('id', $storeId);
        }
        if ($orderedStoreIds !== []) {
            $missing->whereNotIn('id', $orderedStoreIds);
        }

        $missingNames = [];
        foreach ($missing->orderBy('name')->pluck('name') as $name) {
            if (is_string($name)) {
                $missingNames[] = $name;
            }
        }

        return [
            'summary' => $this->finish($summary),
            'days' => $this->finishAll($days),
            'stores' => $this->finishAll($stores),
            'products' => $this->finishAll($products),
            'missing_stores' => $missingNames,
            'updated_at' => now('Europe/Moscow')->format('d.m.Y H:i'),
        ];
    }

    /** @return array{label:string,orders:array<int,bool>,pieces:int,returned_pieces:int,gross:int,returns:int} */
    private function bucket(string $label): array
    {
        return ['label' => $label, 'orders' => [], 'pieces' => 0, 'returned_pieces' => 0, 'gross' => 0, 'returns' => 0];
    }

    /** @param array{label:string,orders:array<int,bool>,pieces:int,returned_pieces:int,gross:int,returns:int} $bucket */
    private function add(array &$bucket, int $orderId, int $pieces, int $returned, int $gross, int $returnAmount): void
    {
        $bucket['orders'][$orderId] = true;
        $bucket['pieces'] += $pieces;
        $bucket['returned_pieces'] += $returned;
        $bucket['gross'] += $gross;
        $bucket['returns'] += $returnAmount;
    }

    /** @param array<string, array{label:string,orders:array<int,bool>,pieces:int,returned_pieces:int,gross:int,returns:int}> $buckets */
    private function addTo(array &$buckets, string $key, string $label, int $orderId, int $pieces, int $returned, int $gross, int $returnAmount): void
    {
        $buckets[$key] ??= $this->bucket($label);
        $this->add($buckets[$key], $orderId, $pieces, $returned, $gross, $returnAmount);
    }

    /**
     * @param  array{label:string,orders:array<int,bool>,pieces:int,returned_pieces:int,gross:int,returns:int}  $bucket
     * @return MetricRow
     */
    private function finish(array $bucket): array
    {
        return [
            'label' => $bucket['label'], 'orders' => count($bucket['orders']), 'pieces' => $bucket['pieces'],
            'returned_pieces' => $bucket['returned_pieces'], 'gross' => $bucket['gross'],
            'returns' => $bucket['returns'], 'net' => $bucket['gross'] - $bucket['returns'],
            'return_share' => $bucket['gross'] > 0 ? $bucket['returns'] / $bucket['gross'] * 100 : 0.0,
        ];
    }

    /**
     * @param  array<string, array{label:string,orders:array<int,bool>,pieces:int,returned_pieces:int,gross:int,returns:int}>  $buckets
     * @return list<MetricRow>
     */
    private function finishAll(array $buckets): array
    {
        $rows = [];
        foreach ($buckets as $bucket) {
            $rows[] = $this->finish($bucket);
        }

        return $rows;
    }
}
