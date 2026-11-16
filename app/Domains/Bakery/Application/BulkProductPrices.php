<?php

namespace Domains\Bakery\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkProductPrices
{
    /** @param list<int> $storeIds */
    public function apply(int $productId, array $storeIds, int $delta, string $direction, string $key, int $userId): void
    {
        DB::transaction(function () use ($productId, $storeIds, $delta, $direction, $key, $userId): void {
            if (DB::table('bakery_price_operations')->where('request_key', $key)->exists()) {
                return;
            }
            $selected = array_values(array_unique($storeIds));
            sort($selected);
            $stores = DB::table('bakeries')->orderBy('id')->lockForUpdate()->get(['id', 'name', 'price_source_id']);
            $byId = $stores->keyBy('id');
            $sources = [];
            foreach ($selected as $id) {
                $store = $byId->get($id);
                /** @var object{id:int, name:string, price_source_id:int|null}|null $store */
                if ($store === null) {
                    throw ValidationException::withMessages(['stores' => "Точка #{$id} не найдена."]);
                }
                $sources[$id] = (int) ($store->price_source_id ?: $id);
            }
            foreach ($stores as $store) {
                /** @var object{id:int, name:string, price_source_id:int|null} $store */
                $source = (int) ($store->price_source_id ?: $store->id);
                if (in_array($source, $sources, true) && ! in_array((int) $store->id, $selected, true)) {
                    throw ValidationException::withMessages(['stores' => "У точки {$store->name} общий прайс с выбранными точками. Выберите её тоже."]);
                }
            }
            $before = [];
            $after = [];
            foreach (array_unique(array_values($sources)) as $source) {
                $price = DB::table('bakery_prices')->where('bakery_id', $source)->where('product_id', $productId)->lockForUpdate()->first(['kopecks']);
                /** @var object{kopecks:int}|null $price */
                $value = $price === null ? null : (int) $price->kopecks;
                /** @var object{name:string}|null $sourceStore */
                $sourceStore = $byId->get($source);
                if ($sourceStore === null) {
                    throw ValidationException::withMessages(['stores' => "Источник цены #{$source} не найден."]);
                }
                $name = $sourceStore->name;
                if ($value === null || $value <= 0) {
                    throw ValidationException::withMessages(['stores' => "Для точки {$name} цена не назначена или равна нулю."]);
                }
                $new = $direction === 'increase' ? $value + $delta : $value - $delta;
                if ($new <= 0) {
                    throw ValidationException::withMessages(['stores' => "Для точки {$name} новая цена должна быть больше нуля."]);
                }
                $before[$source] = $value;
                $after[$source] = $new;
            }
            $operationId = DB::table('bakery_price_operations')->insertGetId(['request_key' => $key, 'product_id' => $productId, 'user_id' => $userId, 'kind' => 'bulk', 'direction' => $direction, 'delta_kopecks' => $delta, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($sources as $storeId => $source) {
                DB::table('bakery_price_operation_items')->insert(['operation_id' => $operationId, 'bakery_id' => $storeId, 'price_source_id' => $source, 'before_kopecks' => $before[$source], 'after_kopecks' => $after[$source]]);
            }
            foreach ($after as $source => $value) {
                DB::table('bakery_prices')->where('bakery_id', $source)->where('product_id', $productId)->update(['kopecks' => $value, 'updated_at' => now()]);
            }
        }, 3);
    }

    public function single(int $productId, int $storeId, int $value, int $userId): void
    {
        DB::transaction(function () use ($productId, $storeId, $value, $userId): void {
            $store = DB::table('bakeries')->where('id', $storeId)->lockForUpdate()->firstOrFail();
            /** @var object{price_source_id:int|null} $store */
            $source = (int) ($store->price_source_id ?: $storeId);
            $old = DB::table('bakery_prices')->where('bakery_id', $source)->where('product_id', $productId)->lockForUpdate()->first(['kopecks']);
            /** @var object{kopecks:int}|null $old */
            $before = $old === null ? null : (int) $old->kopecks;
            DB::table('bakery_prices')->updateOrInsert(['bakery_id' => $source, 'product_id' => $productId], ['kopecks' => $value, 'updated_at' => now()]);
            $operationId = DB::table('bakery_price_operations')->insertGetId(['request_key' => (string) Str::uuid(), 'product_id' => $productId, 'user_id' => $userId, 'kind' => 'single', 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('bakeries')->where('id', $source)->orWhere('price_source_id', $source)->get(['id']) as $linked) {
                /** @var object{id:int} $linked */
                DB::table('bakery_price_operation_items')->insert(['operation_id' => $operationId, 'bakery_id' => $linked->id, 'price_source_id' => $source, 'before_kopecks' => $before, 'after_kopecks' => $value]);
            }
        }, 3);
    }

    public function reverse(int $operationId, string $reason, int $userId): void
    {
        DB::transaction(function () use ($operationId, $reason, $userId): void {
            $operation = DB::table('bakery_price_operations')->where('id', $operationId)->lockForUpdate()->first();
            abort_if($operation === null || $operation->kind !== 'bulk', 404);
            if (DB::table('bakery_price_operations')->where('reverses_id', $operationId)->exists()) {
                throw ValidationException::withMessages(['reason' => 'Операция уже отменена.']);
            }
            $items = DB::table('bakery_price_operation_items')->where('operation_id', $operationId)->orderBy('price_source_id')->get();
            $sources = $items->unique('price_source_id');
            foreach ($sources as $item) {
                /** @var object{price_source_id:int, after_kopecks:int} $item */
                $price = DB::table('bakery_prices')->where('bakery_id', $item->price_source_id)->where('product_id', $operation->product_id)->lockForUpdate()->first(['kopecks']);
                /** @var object{kopecks:int}|null $price */
                if ($price === null || (int) $price->kopecks !== (int) $item->after_kopecks) {
                    throw ValidationException::withMessages(['reason' => 'Цена одной из точек изменилась после операции. Отмена не выполнена.']);
                }
                if (DB::table('bakery_price_operation_items as i')->join('bakery_price_operations as o', 'o.id', '=', 'i.operation_id')->where('o.id', '>', $operationId)->where('o.product_id', $operation->product_id)->where('i.price_source_id', $item->price_source_id)->exists()) {
                    throw ValidationException::withMessages(['reason' => 'После операции цену одной из точек уже редактировали. Отмена не выполнена.']);
                }
            }
            $reversalId = DB::table('bakery_price_operations')->insertGetId(['request_key' => (string) Str::uuid(), 'product_id' => $operation->product_id, 'user_id' => $userId, 'kind' => 'reversal', 'reverses_id' => $operationId, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($items as $item) {
                DB::table('bakery_price_operation_items')->insert(['operation_id' => $reversalId, 'bakery_id' => $item->bakery_id, 'price_source_id' => $item->price_source_id, 'before_kopecks' => $item->after_kopecks, 'after_kopecks' => $item->before_kopecks]);
            }
            foreach ($sources as $item) {
                DB::table('bakery_prices')->where('bakery_id', $item->price_source_id)->where('product_id', $operation->product_id)->update(['kopecks' => $item->before_kopecks, 'updated_at' => now()]);
            }
        }, 3);
    }
}
