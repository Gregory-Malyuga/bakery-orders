<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Domain\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type GroupMember array{name:string,address:string,comment:string|null,ordered:bool,quantity:int,gross:int,returns:int,net:int}
 * @phpstan-type GroupProduct array{name:string,quantity:int,returned:int,unit_kopecks:int,gross:int,net:int}
 * @phpstan-type GroupInvoice array{id:int,name:string,members:list<GroupMember>,products:list<GroupProduct>,quantity:int,returned_quantity:int,gross:int,returns:int,net:int,has_orders:bool,show_return_columns:bool}
 */
class GroupInvoiceService
{
    public function __construct(private readonly OrderReturnSummary $returnSummary) {}

    /**
     * @param  iterable<Order>  $orders
     * @return list<GroupInvoice>
     */
    public function forOrders(iterable $orders): array
    {
        $orderList = iterator_to_array($orders, false);
        $ordersByStore = [];
        foreach ($orderList as $order) {
            $ordersByStore[(int) $order->bakery_id] = $order;
        }
        $returns = $this->returnSummary->forOrders($orderList);

        $membersByGroup = [];
        foreach (DB::table('bakery_store_group_members as members')
            ->join('bakeries as stores', 'stores.id', '=', 'members.bakery_id')
            ->select('members.group_id', 'members.bakery_id', 'stores.name', 'stores.address')
            ->orderBy('stores.name')->get() as $member) {
            /** @var object{group_id:int|string,bakery_id:int|string,name:string,address:string} $member */
            $membersByGroup[(int) $member->group_id][] = $member;
        }

        $result = [];
        foreach (DB::table('bakery_store_groups')->where('show_in_invoice', true)->orderBy('name')->get() as $group) {
            /** @var object{id:int|string,name:string} $group */
            $members = [];
            $products = [];
            $gross = 0;
            $returnAmount = 0;
            $quantity = 0;
            $returnedQuantity = 0;
            $hasOrders = false;
            foreach ($membersByGroup[(int) $group->id] ?? [] as $member) {
                $order = $ordersByStore[(int) $member->bakery_id] ?? null;
                $orderGross = $order === null ? 0 : $order->total_kopecks;
                $orderReturns = $order === null ? 0 : ($returns[(int) $order->id]['amount'] ?? 0);
                $orderQuantity = 0;
                if ($order !== null) {
                    $hasOrders = true;
                    $return = $returns[(int) $order->id] ?? ['quantities' => [], 'extras' => [], 'amount' => 0];
                    foreach ($order->items as $item) {
                        $orderQuantity += $item->quantity;
                        $productId = (int) $item->product_id;
                        $unit = (int) $item->unit_kopecks;
                        $returned = $return['quantities'][$productId] ?? 0;
                        $key = $productId.':'.$unit;
                        $products[$key] ??= ['name' => $item->product_name, 'quantity' => 0, 'returned' => 0, 'unit_kopecks' => $unit, 'gross' => 0, 'net' => 0];
                        $products[$key]['quantity'] += $item->quantity;
                        $products[$key]['returned'] += $returned;
                        $products[$key]['gross'] += $item->total_kopecks;
                        $products[$key]['net'] += ($item->quantity - $returned) * $unit;
                        $returnedQuantity += $returned;
                    }
                    foreach ($return['extras'] as $extra) {
                        $key = $extra['product_id'].':'.$extra['unit_kopecks'];
                        $products[$key] ??= ['name' => $extra['name'], 'quantity' => 0, 'returned' => 0, 'unit_kopecks' => $extra['unit_kopecks'], 'gross' => 0, 'net' => 0];
                        $products[$key]['returned'] += $extra['quantity'];
                        $products[$key]['net'] -= $extra['quantity'] * $extra['unit_kopecks'];
                        $returnedQuantity += $extra['quantity'];
                    }
                }
                $quantity += $orderQuantity;
                $gross += $orderGross;
                $returnAmount += $orderReturns;
                $members[] = [
                    'name' => $member->name,
                    'address' => $member->address,
                    'comment' => $order?->comment,
                    'ordered' => $order !== null,
                    'quantity' => $orderQuantity,
                    'gross' => $orderGross,
                    'returns' => $orderReturns,
                    'net' => $orderGross - $orderReturns,
                ];
            }
            uasort($products, static fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']) ?: ($first['unit_kopecks'] <=> $second['unit_kopecks']));
            $result[] = [
                'id' => (int) $group->id,
                'name' => $group->name,
                // These consolidated printouts use a compact layout without return columns.
                'show_return_columns' => mb_stripos((string) $group->name, 'Балтийск') === false && mb_stripos((string) $group->name, 'Колпино') === false,
                'members' => $members,
                'products' => array_values($products),
                'quantity' => $quantity,
                'returned_quantity' => $returnedQuantity,
                'gross' => $gross,
                'returns' => $returnAmount,
                'net' => $gross - $returnAmount,
                'has_orders' => $hasOrders,
            ];
        }

        return $result;
    }
}
