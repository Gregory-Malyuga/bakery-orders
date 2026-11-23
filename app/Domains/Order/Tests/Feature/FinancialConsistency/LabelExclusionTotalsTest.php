<?php

namespace Domains\Order\Tests\Feature\FinancialConsistency;

use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\FinancialConsistencyCase;
use Domains\Price\Domain\Models\Price;
use Domains\Product\Domain\Models\Product;
use Domains\Store\Domain\Models\Store;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LabelExclusionTotalsTest extends FinancialConsistencyCase
{
    public function test_label_exclusions_apply_once_per_store_without_changing_packaging_or_production(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $first = Store::query()->create([
            'name' => 'Без этикеток',
            'address' => 'Первый адрес',
            'is_active' => true,
        ]);
        $second = Store::query()->create([
            'name' => 'С этикетками',
            'address' => 'Второй адрес',
            'is_active' => true,
        ]);
        $products = [];
        foreach (
            [
                ['По одной', 'single', 1],
                ['По две', 'pair', 2],
                ['По три', 'mixed', 3],
                ['По пять', 'mixed', 5],
            ] as [$name, $mode, $quantity]
        ) {
            $product = Product::query()->create([
                'name' => $name,
                'sort_order' => count($products) + 1,
                'minimum_quantity' => 1,
                'packaging_mode' => $mode,
                'is_active' => true,
            ]);
            $products[$product->id] = ['product' => $product, 'quantity' => $quantity];
            foreach ([$first, $second] as $store) {
                Price::query()->create([
                    'bakery_id' => $store->id,
                    'product_id' => $product->id,
                    'kopecks' => 100,
                ]);
            }
        }
        $quantities = array_map(static fn (array $entry): int => $entry['quantity'], $products);
        $productIds = array_keys($products);
        $service = app(OrderingService::class);
        foreach ([$first, $second] as $store) {
            $quote = $service->quote($store->id, $quantities);
            $this->assertNotNull(
                $service->place($store->id, (string) Str::uuid(), $quantities, $quote['hash'])[
                    'order'
                ],
            );
        }
        $date = $quote['date'];
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Verify,
        ]);
        $url = '/admin?date='.$date;

        $this->actingAs($admin)
            ->get($url.'&tab=labels')
            ->assertOk()
            ->assertViewHas('labelTotal', 8);
        $this->actingAs($admin)
            ->post('/admin/groups', [
                'name' => 'Исключить первую',
                'exclude_from_labels' => 1,
                'stores' => [$first->id],
            ])
            ->assertRedirect();
        $firstGroupId = DB::table('bakery_store_groups')
            ->where('name', 'Исключить первую')
            ->value('id');
        $this->assertIsInt($firstGroupId);
        $hasOneOfEachSize = static function (array $plans) use ($productIds): bool {
            /** @var array<int, array{one:int,two:int,three:int,five:int,loose:int}> $plans */
            return $plans[$productIds[0]]['one'] === 1 &&
                $plans[$productIds[1]]['two'] === 1 &&
                $plans[$productIds[2]]['three'] === 1 &&
                $plans[$productIds[3]]['five'] === 1;
        };
        $hasOneExcludedStore = static function (array $stores): bool {
            /** @var list<array{name:string,groups:list<string>,packages:int}> $stores */
            return count($stores) === 1 &&
                $stores[0]['name'] === 'Без этикеток' &&
                $stores[0]['packages'] === 4;
        };
        $this->actingAs($admin)
            ->get($url.'&tab=labels')
            ->assertOk()
            ->assertViewHas('labelTotal', 4)
            ->assertViewHas('packageTotal', 8)
            ->assertViewHas('labelPackaging', $hasOneOfEachSize)
            ->assertViewHas('excludedStores', $hasOneExcludedStore)
            ->assertSee('Исключить первую');
        $this->actingAs($admin)
            ->get($url.'&tab=packaging')
            ->assertOk()
            ->assertViewHas('packaging', static function (array $plans) use ($productIds): bool {
                /** @var array<int, array{one:int,two:int,three:int,five:int,loose:int}> $plans */
                return $plans[$productIds[0]]['one'] === 2;
            });
        $this->actingAs($admin)
            ->get($url.'&tab=production')
            ->assertOk()
            ->assertViewHas('productionTotal', 22);

        $this->actingAs($admin)
            ->post('/admin/groups', [
                'name' => 'Перекрытие',
                'show_in_invoice' => 1,
                'exclude_from_labels' => 1,
                'stores' => [$first->id],
            ])
            ->assertRedirect();
        $secondGroupId = DB::table('bakery_store_groups')->where('name', 'Перекрытие')->value('id');
        $this->assertIsInt($secondGroupId);
        $this->actingAs($admin)
            ->get($url.'&tab=labels')
            ->assertOk()
            ->assertViewHas('labelTotal', 4);
        $this->actingAs($admin)
            ->post('/admin/groups', [
                'id' => $firstGroupId,
                'name' => 'Исключить первую',
                'exclude_from_labels' => 1,
                'stores' => [$first->id, $second->id],
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->get($url.'&tab=labels')
            ->assertOk()
            ->assertViewHas('labelTotal', 0)
            ->assertViewHas('packageTotal', 8);
        $this->actingAs($admin)
            ->post('/admin/groups', [
                'id' => $firstGroupId,
                'name' => 'Исключить первую',
                'stores' => [$first->id, $second->id],
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->get($url.'&tab=labels')
            ->assertOk()
            ->assertViewHas('labelTotal', 4);
        $this->actingAs($admin)
            ->post('/admin/groups', [
                'id' => $secondGroupId,
                'name' => 'Перекрытие',
                'show_in_invoice' => 1,
                'exclude_from_labels' => 1,
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->get($url.'&tab=labels')
            ->assertOk()
            ->assertViewHas('labelTotal', 8);
    }
}
