<?php

namespace Domains\Bakery\Application;

use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LabelPlanService
{
    public function __construct(private readonly PackagingPlanner $packaging) {}

    /**
     * @param  array<int, array<int, int>>  $quantities  Product ID => store ID => quantity.
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Bakery>  $stores
     * @return array{packaging:array<int,array{one:int,two:int,three:int,five:int,loose:int}>,labelPackaging:array<int,array{one:int,two:int,three:int,five:int,loose:int}>,labels:array<int,int>,labelTotal:int,packageTotal:int,excludedStores:list<array{name:string,groups:list<string>,packages:int}>}
     */
    public function forQuantities(array $quantities, Collection $products, Collection $stores): array
    {
        $exclusions = [];
        foreach (DB::table('bakery_store_group_members as members')
            ->join('bakery_store_groups as groups', 'groups.id', '=', 'members.group_id')
            ->where('groups.exclude_from_labels', true)
            ->select('members.bakery_id', 'groups.name')->orderBy('groups.name')->get() as $row) {
            /** @var object{bakery_id:int|string,name:string} $row */
            $exclusions[(int) $row->bakery_id][] = $row->name;
        }

        $productsById = $products->keyBy('id');
        $storesById = $stores->keyBy('id');
        $packaging = [];
        $labelPackaging = [];
        $excludedPackages = [];
        foreach ($quantities as $productId => $byStore) {
            $product = $productsById->get($productId);
            if ($product === null) {
                continue;
            }
            $packaging[$productId] = $this->emptyPlan();
            $labelPackaging[$productId] = $this->emptyPlan();
            foreach ($byStore as $storeId => $quantity) {
                if ($storesById->get($storeId) === null) {
                    continue;
                }
                $plan = $this->packaging->plan($quantity, $product->packaging_mode);
                foreach ($plan as $size => $count) {
                    $packaging[$productId][$size] += $count;
                    if (! isset($exclusions[$storeId])) {
                        $labelPackaging[$productId][$size] += $count;
                    }
                }
                if (isset($exclusions[$storeId])) {
                    $excludedPackages[$storeId] = ($excludedPackages[$storeId] ?? 0)
                        + $plan['one'] + $plan['two'] + $plan['three'] + $plan['five'];
                }
            }
        }

        $labels = [];
        $packageTotal = 0;
        foreach ($packaging as $productId => $plan) {
            $packageTotal += $plan['one'] + $plan['two'] + $plan['three'] + $plan['five'];
            $labelPlan = $labelPackaging[$productId];
            $labels[$productId] = $labelPlan['one'] + $labelPlan['two'] + $labelPlan['three'] + $labelPlan['five'];
        }
        $excludedStores = [];
        foreach ($excludedPackages as $storeId => $count) {
            $store = $storesById->get($storeId);
            if ($store !== null) {
                $excludedStores[] = ['name' => $store->name, 'groups' => $exclusions[$storeId], 'packages' => $count];
            }
        }

        return [
            'packaging' => $packaging,
            'labelPackaging' => $labelPackaging,
            'labels' => $labels,
            'labelTotal' => array_sum($labels),
            'packageTotal' => $packageTotal,
            'excludedStores' => $excludedStores,
        ];
    }

    /** @return array{one:int,two:int,three:int,five:int,loose:int} */
    private function emptyPlan(): array
    {
        return ['one' => 0, 'two' => 0, 'three' => 0, 'five' => 0, 'loose' => 0];
    }
}
