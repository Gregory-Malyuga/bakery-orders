<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Domain\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderReturnSummary
{
    /**
     * @param  iterable<Order>  $orders
     * @return array<int, array{quantities:array<int,int>,extras:list<array{product_id:int,name:string,quantity:int,unit_kopecks:int}>,amount:int}>
     */
    public function forOrders(iterable $orders): array
    {
        $byId = [];
        foreach ($orders as $order) {
            $byId[(int) $order->id] = $order;
        }
        if ($byId === []) {
            return [];
        }

        $result = [];
        foreach (DB::table('bakery_returns')->whereIn('order_id', array_keys($byId))->orderBy('id')->get() as $row) {
            /** @var object{order_id:int|string,product_id:int|string,quantity:int|string,unit_kopecks:int|string|null,product_name:string|null,is_order_item:bool|int|string} $row */
            $orderId = (int) $row->order_id;
            $productId = (int) $row->product_id;
            $item = $byId[$orderId]->items->firstWhere('product_id', $productId);
            $quantity = (int) $row->quantity;
            $unit = $row->unit_kopecks !== null ? (int) $row->unit_kopecks : (int) ($item === null ? 0 : $item->unit_kopecks);
            $result[$orderId] ??= ['quantities' => [], 'extras' => [], 'amount' => 0];
            $result[$orderId]['amount'] += $quantity * $unit;
            if ($item !== null && in_array($row->is_order_item, [true, 1, '1', 't'], true)) {
                $result[$orderId]['quantities'][$productId] = ($result[$orderId]['quantities'][$productId] ?? 0) + $quantity;
            } else {
                $result[$orderId]['extras'][] = [
                    'product_id' => $productId,
                    'name' => (string) ($row->product_name ?? 'Товар #'.$productId),
                    'quantity' => $quantity,
                    'unit_kopecks' => $unit,
                ];
            }
        }

        return $result;
    }
}
