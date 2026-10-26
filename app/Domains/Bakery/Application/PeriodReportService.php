<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Domain\Models\Order;

class PeriodReportService
{
    public function __construct(private readonly ProductionCalculator $production, private readonly OrderReturnSummary $returnSummary) {}

    /** @return array{orders:list<array{date:string,store:string,product:string,quantity:int,returned:int,unit_kopecks:int,total_kopecks:int,return_kopecks:int,net_kopecks:int}>,products:array<string,int>,production:array{dough_grams:int|float,main_dough:array<string,float>,samsa_count:int,samsa_batch_size:int|float,samsa_dough:array<string,float>,ingredients:array<string,int|float>,other_ingredients:array<string,array<string,int|float>>,without_recipe:list<string>},total_kopecks:int,return_kopecks:int,net_kopecks:int} */
    public function build(string $from, string $to): array
    {
        $orders = Order::query()->with('items')->whereBetween('delivery_date', [$from, $to])->where('status', 'accepted')->orderBy('delivery_date')->orderBy('bakery_id')->get();
        $returns = $this->returnSummary->forOrders($orders);
        $lines = [];
        $products = [];
        $productIds = [];
        $total = 0;
        $returnTotal = 0;
        foreach ($orders as $order) {
            $return = $returns[$order->id] ?? ['quantities' => [], 'extras' => [], 'amount' => 0];
            foreach ($order->items as $item) {
                $returnedQuantity = $return['quantities'][$item->product_id] ?? 0;
                $returnKopecks = $returnedQuantity * $item->unit_kopecks;
                $lines[] = [
                    'date' => $order->delivery_date->toDateString(),
                    'store' => $order->store_name,
                    'product' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'returned' => $returnedQuantity,
                    'unit_kopecks' => (int) $item->unit_kopecks,
                    'total_kopecks' => (int) $item->total_kopecks,
                    'return_kopecks' => $returnKopecks,
                    'net_kopecks' => $item->total_kopecks - $returnKopecks,
                ];
                $products[$item->product_name] = ($products[$item->product_name] ?? 0) + $item->quantity;
                $productIds[$item->product_name] = (int) $item->product_id;
                $total += $item->total_kopecks;
            }
            foreach ($return['extras'] as $extra) {
                $amount = $extra['quantity'] * $extra['unit_kopecks'];
                $lines[] = [
                    'date' => $order->delivery_date->toDateString(),
                    'store' => $order->store_name,
                    'product' => $extra['name'],
                    'quantity' => 0,
                    'returned' => $extra['quantity'],
                    'unit_kopecks' => $extra['unit_kopecks'],
                    'total_kopecks' => 0,
                    'return_kopecks' => $amount,
                    'net_kopecks' => -$amount,
                ];
            }
            $returnTotal += $return['amount'];
        }
        ksort($products);

        return ['orders' => $lines, 'products' => $products, 'production' => $this->production->calculate($products, $productIds), 'total_kopecks' => $total, 'return_kopecks' => $returnTotal, 'net_kopecks' => $total - $returnTotal];
    }
}
