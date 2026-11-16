<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\GroupInvoiceService;
use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Order;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\Bakery\Infrastructure\Exports\MatrixInvoiceWorkbook;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PharData;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
class GroupInvoiceRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0:Order,1:int,2:string} */
    private function orderWithGroup(string $groupName): array
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create([
            'name' => 'Точка '.$groupName, 'address' => 'Тестовый адрес', 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Регрессионная булочка', 'sort_order' => 1, 'minimum_quantity' => 1,
            'packaging_mode' => 'single', 'is_active' => true,
        ]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 1000]);
        $groupId = (int) DB::table('bakery_store_groups')->insertGetId([
            'name' => $groupName, 'show_in_invoice' => true, 'exclude_from_labels' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bakery_store_group_members')->insert(['group_id' => $groupId, 'bakery_id' => $store->id]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 3]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 3], $quote['hash'])['order'];
        $this->assertInstanceOf(Order::class, $order);

        return [$order, $product->id, $quote['date']];
    }

    /** @return GroupInvoice */
    private function group(Order $order): array
    {
        $groups = app(GroupInvoiceService::class)->forOrders([$order]);
        $activeGroups = array_values(array_filter($groups, static fn (array $group): bool => $group['has_orders']));
        $this->assertCount(1, $activeGroups);

        return $activeGroups[0];
    }

    /** @param GroupInvoice $group */
    private function html(array $group, string $date): string
    {
        return view('bakery.group-invoice', [
            'group' => $group, 'date' => $date, 'generatedAt' => now('Europe/Moscow'),
        ])->render();
    }

    /** @param GroupInvoice $group */
    private function xlsx(array $group, string $date): string
    {
        $path = app(MatrixInvoiceWorkbook::class)->create([], [], [$group], $date, '02.11.2026 12:00');

        try {
            $archive = new PharData($path);
            $xml = $archive['xl/worksheets/sheet1.xml']->getContent();
            unset($archive);

            return $xml;
        } finally {
            @unlink($path);
        }
    }

    public function test_regular_group_without_returns_has_no_return_columns_in_html_or_xlsx(): void
    {
        [$order, , $date] = $this->orderWithGroup('Регрессия обычная без возвратов');
        $group = $this->group($order);
        $this->assertSame(0, $group['returned_quantity']);
        $this->assertSame(3000, $group['net']);

        $html = $this->html($group, $date);
        $xml = $this->xlsx($group, $date);
        $this->assertStringNotContainsString('Возврат, шт.', $html);
        $this->assertStringNotContainsString('После возврата, ₽', $html);
        $this->assertStringNotContainsString('Возврат, ₽', $html);
        $this->assertStringNotContainsString('Возврат</t>', $xml);
        $this->assertStringNotContainsString('Возврат, ₽', $xml);
        $this->assertStringContainsString('К оплате, ₽', $xml);
    }

    public function test_regular_group_with_returns_exposes_return_columns_and_net_amount(): void
    {
        [$order, $productId, $date] = $this->orderWithGroup('Регрессия обычная с возвратами');
        $this->addReturn($order, $productId);
        $group = $this->group($order);
        $this->assertSame(1, $group['returned_quantity']);
        $this->assertSame(1000, $group['returns']);
        $this->assertSame(2000, $group['net']);

        $html = $this->html($group, $date);
        $xml = $this->xlsx($group, $date);
        $this->assertStringContainsString('Возврат, шт.', $html);
        $this->assertStringContainsString('Возврат, ₽', $html);
        $this->assertStringContainsString('Возврат</t>', $xml);
        $this->assertStringContainsString('Возврат, ₽', $xml);
        $this->assertStringContainsString('20.00', $xml);
    }

    public function test_baltic_and_kolpino_hide_returns_even_when_present_but_keep_net_amount(): void
    {
        foreach (['Балтийская общая', 'Колпино общая'] as $groupName) {
            [$order, $productId, $date] = $this->orderWithGroup($groupName.' регрессия');
            $this->addReturn($order, $productId);
            $group = $this->group($order);
            $this->assertFalse($group['show_return_columns']);
            $this->assertSame(2000, $group['net']);
            $html = $this->html($group, $date);
            $xml = $this->xlsx($group, $date);
            $this->assertStringNotContainsString('Возврат, шт.', $html);
            $this->assertStringNotContainsString('Возврат, ₽', $html);
            $this->assertStringNotContainsString('Возврат</t>', $xml);
            $this->assertStringNotContainsString('Возврат, ₽', $xml);
            $this->assertStringContainsString('20.00', $xml);
            $this->assertStringContainsString('Итого по точкам', $xml);
        }
    }

    public function test_cancelled_return_does_not_trigger_columns_or_reduce_total(): void
    {
        [$order, $productId, $date] = $this->orderWithGroup('Регрессия отменённого возврата');
        $this->addReturn($order, $productId);
        DB::table('bakery_returns')->where('order_id', $order->id)->update(['status' => 'cancelled']);
        $group = $this->group($order);
        $this->assertSame(0, $group['returned_quantity']);
        $this->assertSame(3000, $group['net']);
        $this->assertStringNotContainsString('Возврат, шт.', $this->html($group, $date));
        $this->assertStringNotContainsString('Возврат</t>', $this->xlsx($group, $date));
    }

    private function addReturn(Order $order, int $productId): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        DB::table('bakery_returns')->insert([
            'admin_user_id' => $admin->id,
            'order_id' => $order->id, 'product_id' => $productId, 'quantity' => 1,
            'unit_kopecks' => 1000, 'product_name' => 'Регрессионная булочка',
            'is_order_item' => true, 'status' => 'active',
            'created_at' => now(),
        ]);
    }
}
