<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Support\Facades\DB;

class PeriodReportService
{
    public function __construct(private readonly ProductionCalculator $production) {}

    /** @return array{orders:list<array{date:string,store:string,product:string,quantity:int,returned:int,unit_kopecks:int,total_kopecks:int,return_kopecks:int,net_kopecks:int}>,products:array<string,int>,production:array{dough_grams:int|float,main_dough:array<string,float>,samsa_count:int,samsa_dough:array<string,float>,ingredients:array<string,float>,other_ingredients:array<string,array<string,float>>,without_recipe:list<string>},total_kopecks:int,return_kopecks:int,net_kopecks:int} */
    public function build(string $from, string $to): array
    {
        $orders = Order::query()->with('items')->whereBetween('delivery_date', [$from, $to])->where('status', 'accepted')->orderBy('delivery_date')->orderBy('bakery_id')->get();
        $orderIds = $orders->pluck('id')->all();
        $returned = [];
        if ($orderIds !== []) {
            foreach (DB::table('bakery_returns')->whereIn('order_id', $orderIds)->selectRaw('order_id, product_id, SUM(quantity) AS quantity')->groupBy('order_id', 'product_id')->get() as $row) {
                /** @var object{order_id:int|string,product_id:int|string,quantity:int|string} $row */
                $returned[$row->order_id.':'.$row->product_id] = (int) $row->quantity;
            }
        }
        $lines = [];
        $products = [];
        $total = 0;
        $returnTotal = 0;
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $returnedQuantity = $returned[$order->id.':'.$item->product_id] ?? 0;
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
                $total += $item->total_kopecks;
                $returnTotal += $returnKopecks;
            }
        }
        ksort($products);

        $productIds = Product::query()->whereIn('name', array_keys($products))->pluck('id', 'name')->all();

        return ['orders' => $lines, 'products' => $products, 'production' => $this->production->calculate($products, $productIds), 'total_kopecks' => $total, 'return_kopecks' => $returnTotal, 'net_kopecks' => $total - $returnTotal];
    }
}
