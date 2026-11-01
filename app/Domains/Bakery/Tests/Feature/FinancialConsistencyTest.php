<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Domain\Models\Bakery;
use Domains\Bakery\Domain\Models\Price;
use Domains\Bakery\Domain\Models\Product;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_order_pages_printouts_and_both_workbooks_agree_after_adjustment_returns_and_price_change(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $first = Bakery::query()->create(['name' => 'Проверка А', 'address' => 'Адрес А', 'is_active' => true]);
        $second = Bakery::query()->create(['name' => 'Проверка Б', 'address' => 'Адрес Б', 'is_active' => true]);
        $pizza = Product::query()->create(['name' => 'Пицца', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        $bun = Product::query()->create(['name' => 'Булочка', 'sort_order' => 2, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        foreach ([[$first, $pizza, 1234], [$first, $bun, 205], [$second, $pizza, 1530]] as [$store, $product, $kopecks]) {
            Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => $kopecks]);
        }
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $first->id]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $service = app(OrderingService::class);
        $firstQuote = $service->quote($first->id, [$pizza->id => 3, $bun->id => 2]);
        $this->assertSame(4112, $firstQuote['total']);
        $this->actingAs($customer)->post('/orders/preview', ['request_key' => (string) Str::uuid(), 'quantities' => [$pizza->id => 3, $bun->id => 2]])
            ->assertOk()->assertSee('41,12 ₽');
        $firstOrder = $service->place($first->id, (string) Str::uuid(), [$pizza->id => 3, $bun->id => 2], $firstQuote['hash'])['order'];
        $this->assertNotNull($firstOrder);
        $secondQuote = $service->quote($second->id, [$pizza->id => 2]);
        $secondOrder = $service->place($second->id, (string) Str::uuid(), [$pizza->id => 2], $secondQuote['hash'])['order'];
        $this->assertNotNull($secondOrder);
        $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/edit', [
            'quantities' => [$pizza->id => 4, $bun->id => 2], 'reason' => 'Уточнение',
        ])->assertRedirect();
        $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/returns', ['product' => $pizza->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post('/admin/orders/'.$firstOrder->id.'/returns', ['product' => $bun->id, 'quantity' => 1])->assertRedirect();
        Price::query()->where('bakery_id', $first->id)->where('product_id', $pizza->id)->update(['kopecks' => 9999]);

        $date = $firstQuote['date'];
        $this->assertSame(5346, $firstOrder->fresh()?->total_kopecks);
        $this->actingAs($customer)->get('/orders/'.$firstOrder->id)->assertOk()->assertSee('53,46 ₽')->assertSee('12,34 ₽');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()->assertSee('39,07 ₽')->assertSee('30,60 ₽');
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=production')->assertOk()
            ->assertViewHas('totals', fn (array $totals): bool => $totals[$pizza->id] === 6 && $totals[$bun->id] === 2);
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=labels')->assertOk()->assertViewHas('labelTotal', 8);
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=prep')->assertOk()
            ->assertViewHas('production', fn (array $production): bool => $production['dough_grams'] === 816
                && is_array($production['ingredients']) && ($production['ingredients']['Помидоры'] ?? null) === 240);
        $this->actingAs($admin)->get('/admin/orders/'.$firstOrder->id)->assertOk()->assertSee('53,46 ₽')->assertSee('41,12 ₽');
        $this->actingAs($admin)->get('/admin/orders/'.$firstOrder->id.'/print')->assertOk()
            ->assertSee('53,46 ₽')->assertSee('14,39 ₽')->assertSee('39,07 ₽');
        $this->actingAs($admin)->get('/admin/print?date='.$date)->assertOk()
            ->assertSee('39,07 ₽')->assertSee('30,60 ₽');

        $single = $this->downloadCells('/admin/orders/'.$firstOrder->id.'/xlsx', 2);
        $this->assertSame('4', $this->cellForProduct($single[1], 'Пицца', 'C'));
        $this->assertSame('1', $this->cellForProduct($single[1], 'Пицца', 'D'));
        $this->assertSame('12.34', $this->cellForProduct($single[1], 'Пицца', 'E'));
        $this->assertSame('49.36', $this->cellForProduct($single[1], 'Пицца', 'F'));
        $this->assertSame('37.02', $this->cellForProduct($single[1], 'Пицца', 'G'));
        $this->assertSame('C7*E7', $this->formulaForProduct($single[1], 'Пицца', 'F'));
        $this->assertSame('(C7-D7)*E7', $this->formulaForProduct($single[1], 'Пицца', 'G'));
        $this->assertSame('53.46', $this->cellForProduct($single[1], 'Итого', 'F'));
        $this->assertSame('39.07', $this->cellForProduct($single[1], 'Итого', 'G'));

        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 3);
        $this->assertSame('53.46', $this->cellForProduct($batch[1], 'Итого', 'F'));
        $this->assertSame('39.07', $this->cellForProduct($batch[1], 'Итого', 'G'));
        $this->assertSame('30.60', $this->cellForProduct($batch[2], 'Итого', 'E'));
        $this->assertNotContains('Возврат', $batch[2]);
        $this->assertSame('6', $this->cellForProduct($batch[3], 'Пицца', 'D', 'A'));
        $this->assertSame('2', $this->cellForProduct($batch[3], 'Булочка', 'D', 'A'));
        $this->assertSame('84.06', $this->cellForProduct($batch[3], 'Итого, ₽', 'D', 'A'));

        $period = '?from='.$date.'&to='.$date;
        $this->actingAs($admin)->get('/admin/report'.$period)->assertOk()
            ->assertSee('84,06 ₽')->assertSee('14,39 ₽')->assertSee('69,67 ₽');
        $report = $this->downloadCells('/admin/report/xlsx'.$period, 3);
        $this->assertSame('4', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'D'));
        $this->assertSame('1', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'E'));
        $this->assertSame('49.36', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'G'));
        $this->assertSame('12.34', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'H'));
        $this->assertSame('37.02', $this->reportValue($report[1], 'Проверка А', 'Пицца', 'I'));
        $this->assertSame('6', $this->cellForProduct($report[2], 'Пицца', 'B', 'A'));
        $this->assertSame('2', $this->cellForProduct($report[2], 'Булочка', 'B', 'A'));
        $this->assertSame('0.24', $this->cellForProduct($report[3], 'Помидоры', 'C'));
    }

    public function test_empty_date_and_report_export_zeroes_without_leaking_orders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Проверка пустой даты', 'address' => 'Адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирог', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 1250]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 2]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 2], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $service->cancel($store->id, $order->id);

        $date = $quote['date'];
        foreach (['/admin/xlsx?date='.$date, '/admin/report/xlsx?from='.$date.'&to='.$date] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()->assertSee('На эту дату принятых заказов нет.');
        $this->actingAs($admin)->get('/admin/print?date='.$date)->assertOk()->assertDontSee('Проверка пустой даты');
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/print')->assertNotFound();
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/xlsx')->assertNotFound();
        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 2);
        $this->assertContains('На выбранную дату принятых заказов нет.', $batch[1]);
        $this->assertSame('0', $this->cellForProduct($batch[2], 'Всего, шт.', 'B', 'A'));
        $this->assertSame('0.00', $this->cellForProduct($batch[2], 'Итого, ₽', 'B', 'A'));
        $this->actingAs($admin)->get('/admin/report?from='.$date.'&to='.$date)->assertOk()
            ->assertSee('Строк заказов: <strong>0</strong>', false)->assertSee('0,00 ₽');
        $report = $this->downloadCells('/admin/report/xlsx?from='.$date.'&to='.$date, 3);
        $this->assertArrayNotHasKey('A2', $report[1]);
        $this->assertArrayNotHasKey('A2', $report[2]);
    }

    public function test_cancelling_one_duplicate_return_preserves_history_and_updates_every_financial_view(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Точка возврата', 'address' => 'Адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Пирог', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 1000]);
        $admin = User::factory()->create(['name' => 'Администратор возвратов', 'role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $customer = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Verify, 'bakery_id' => $store->id]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 5]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 5], $quote['hash'])['order'];
        $this->assertNotNull($order);
        $url = '/admin/orders/'.$order->id.'/returns';
        $this->actingAs($admin)->post($url, ['product' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($admin)->post($url, ['product' => $product->id, 'quantity' => 1])->assertRedirect();
        $returnIds = array_map(static function (mixed $id): int {
            if (! is_int($id) && ! is_string($id)) {
                throw new \UnexpectedValueException('Некорректный ID возврата.');
            }

            return (int) $id;
        }, DB::table('bakery_returns')->where('order_id', $order->id)->orderBy('id')->pluck('id')->all());
        $this->assertCount(2, $returnIds);
        $cancelUrl = $url.'/'.$returnIds[0].'/cancel';

        $this->actingAs($customer)->post($cancelUrl, ['reason' => 'Дубль'])->assertForbidden();
        $this->actingAs($admin)->post($cancelUrl, ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post($cancelUrl, ['reason' => 'Дублированная запись'])->assertRedirect();
        $this->actingAs($admin)->post($cancelUrl, ['reason' => 'Повтор'])->assertStatus(422);
        $this->assertDatabaseCount('bakery_returns', 2);
        $this->assertDatabaseHas('bakery_returns', ['id' => $returnIds[0], 'status' => 'cancelled', 'cancelled_by_user_id' => $admin->id, 'cancellation_reason' => 'Дублированная запись']);
        $this->assertDatabaseHas('bakery_returns', ['id' => $returnIds[1], 'status' => 'active', 'cancelled_at' => null]);
        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()
            ->assertSee('Дублированная запись')->assertSee('Администратор возвратов')->assertSee('40,00 ₽');
        $date = $quote['date'];
        $this->actingAs($admin)->get('/admin?date='.$date.'&tab=orders')->assertOk()->assertSee('40,00 ₽');
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/print')->assertOk()->assertSee('40,00 ₽')->assertSee('10,00 ₽');
        $this->actingAs($admin)->get('/admin/print?date='.$date)->assertOk()->assertSee('40,00 ₽');
        $single = $this->downloadCells('/admin/orders/'.$order->id.'/xlsx', 2);
        $this->assertSame('1', $this->cellForProduct($single[1], 'Пирог', 'D'));
        $this->assertSame('40.00', $this->cellForProduct($single[1], 'Итого', 'G'));
        $batch = $this->downloadCells('/admin/xlsx?date='.$date, 2);
        $this->assertSame('40.00', $this->cellForProduct($batch[1], 'Итого', 'G'));
        $this->actingAs($admin)->get('/admin/report?from='.$date.'&to='.$date)->assertOk()->assertSee('40,00 ₽');
        $report = $this->downloadCells('/admin/report/xlsx?from='.$date.'&to='.$date, 3);
        $this->assertSame('1', $this->reportValue($report[1], 'Точка возврата', 'Пирог', 'E'));
        $this->assertEqualsWithDelta(40.0, (float) $this->reportValue($report[1], 'Точка возврата', 'Пирог', 'I'), 0.001);

        $service->cancel($store->id, $order->id);
        $this->actingAs($admin)->post($url, ['product' => $product->id, 'quantity' => 1])->assertStatus(422);
    }

    public function test_accepted_orders_list_shows_piece_count_net_total_and_store_comment(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-16 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Точка с комментарием', 'address' => 'Адрес', 'is_active' => true]);
        $first = Product::query()->create(['name' => 'Пирог', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        $second = Product::query()->create(['name' => 'Булочка', 'sort_order' => 2, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $first->id, 'kopecks' => 1000]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $second->id, 'kopecks' => 2000]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$first->id => 5, $second->id => 8]);
        $order = $service->place($store->id, (string) Str::uuid(), [$first->id => 5, $second->id => 8], $quote['hash'], "Первая строка\nВторая строка")['order'];
        $this->assertNotNull($order);
        $order->update(['admin_note' => 'Только для администратора']);
        $url = '/admin?date='.$quote['date'].'&tab=orders';

        $this->actingAs($admin)->get($url)->assertOk()
            ->assertSee('Изделий, шт.')->assertSee('Сумма после возвратов')
            ->assertSee('<td>13</td>', false)->assertSee('210,00 ₽')
            ->assertSee("Первая строка\nВторая строка")
            ->assertDontSee('Только для администратора');

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', [
            'product' => $first->id, 'quantity' => 2, 'reason' => 'Возврат',
        ])->assertRedirect();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('<td>13</td>', false)->assertSee('190,00 ₽');
        $returnId = DB::table('bakery_returns')->where('order_id', $order->id)->value('id');
        $this->assertIsInt($returnId);
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns/'.$returnId.'/cancel', [
            'reason' => 'Ошибочный возврат',
        ])->assertRedirect();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('<td>13</td>', false)->assertSee('210,00 ₽');
    }

    public function test_unordered_available_product_can_be_returned_at_snapshotted_price(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-26 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Магазин возвратов', 'address' => 'Адрес', 'is_active' => true]);
        $sausage = Product::query()->create(['name' => 'Сосиска', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        $cabbage = Product::query()->create(['name' => 'Капуста', 'sort_order' => 2, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $sausage->id, 'kopecks' => 1000]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $cabbage->id, 'kopecks' => 250]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$sausage->id => 1]);
        $order = $service->place($store->id, (string) Str::uuid(), [$sausage->id => 1], $quote['hash'])['order'];
        $this->assertNotNull($order);

        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Капуста');
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/print')->assertOk()->assertDontSee('Возврат, шт.');
        $before = $this->downloadCells('/admin/orders/'.$order->id.'/xlsx', 2);
        $this->assertNotContains('Возврат', $before[1]);
        $this->assertSame('10.00', $this->cellForProduct($before[1], 'Итого', 'E'));

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/returns', [
            'product' => $cabbage->id, 'quantity' => 2, 'reason' => 'Остаток прошлой поставки',
        ])->assertRedirect();
        $this->assertDatabaseHas('bakery_returns', [
            'order_id' => $order->id, 'product_id' => $cabbage->id, 'product_name' => 'Капуста',
            'unit_kopecks' => 250, 'quantity' => 2, 'is_order_item' => false,
        ]);
        Price::query()->where('bakery_id', $store->id)->where('product_id', $cabbage->id)->update(['kopecks' => 500]);
        $this->actingAs($admin)->get('/admin/orders/'.$order->id.'/print')->assertOk()
            ->assertSee('Возврат, шт.')->assertSee('Капуста')->assertSee('5,00 ₽');
        $invoice = $this->downloadCells('/admin/orders/'.$order->id.'/xlsx', 2);
        $this->assertSame('0', $this->cellForProduct($invoice[1], 'Капуста', 'C'));
        $this->assertSame('2', $this->cellForProduct($invoice[1], 'Капуста', 'D'));
        $this->assertSame('2.50', $this->cellForProduct($invoice[1], 'Капуста', 'E'));
        $this->assertSame('-5.00', $this->cellForProduct($invoice[1], 'Капуста', 'G'));
        $this->assertSame('5.00', $this->cellForProduct($invoice[1], 'Итого', 'G'));

        $date = $quote['date'];
        $this->actingAs($admin)->get('/admin/report?from='.$date.'&to='.$date)->assertOk()
            ->assertSee('5,00 ₽');
        $report = $this->downloadCells('/admin/report/xlsx?from='.$date.'&to='.$date, 3);
        $this->assertSame('0', $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'D'));
        $this->assertSame('2', $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'E'));
        $this->assertEqualsWithDelta(2.5, (float) $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'F'), 0.001);
        $this->assertEqualsWithDelta(-5.0, (float) $this->reportValue($report[1], 'Магазин возвратов', 'Капуста', 'I'), 0.001);
        $this->assertSame(1, (int) DB::table('bakery_order_items')->where('order_id', $order->id)->sum('quantity'));
    }

    /** @return array<int, array<string, string>> */
    private function downloadCells(string $url, int $sheetCount): array
    {
        $response = $this->get($url)->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $sheets = [];
        for ($index = 1; $index <= $sheetCount; $index++) {
            $xml = simplexml_load_string($archive['xl/worksheets/sheet'.$index.'.xml']->getContent());
            $this->assertNotFalse($xml);
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];
            $nodes = $xml->xpath('//x:sheetData/x:row/x:c');
            if (! is_array($nodes)) {
                $this->fail('Не удалось прочитать ячейки листа '.$index);
            }
            foreach ($nodes as $cell) {
                $cells[(string) $cell['r']] = isset($cell->is->t) ? (string) $cell->is->t : (string) $cell->v;
                if (isset($cell->f)) {
                    $cells['='.$cell['r']] = (string) $cell->f;
                }
            }
            $sheets[$index] = $cells;
        }
        unset($archive);
        unlink($path);

        return $sheets;
    }

    /** @param array<string, string> $cells */
    private function cellForProduct(array $cells, string $product, string $valueColumn, string $nameColumn = 'B'): string
    {
        foreach ($cells as $ref => $value) {
            if (preg_match('/^'.preg_quote($nameColumn, '/').'([0-9]+)$/', $ref, $match) && $value === $product) {
                return $cells[$valueColumn.$match[1]] ?? '';
            }
        }

        $this->fail('Нет строки '.$product);
    }

    /** @param array<string, string> $cells */
    private function formulaForProduct(array $cells, string $product, string $column): string
    {
        foreach ($cells as $ref => $value) {
            if (preg_match('/^B([0-9]+)$/', $ref, $match) && $value === $product) {
                return $cells['='.$column.$match[1]] ?? '';
            }
        }

        $this->fail('Нет формулы для '.$product);
    }

    /** @param array<string, string> $cells */
    private function reportValue(array $cells, string $store, string $product, string $column): string
    {
        foreach ($cells as $ref => $value) {
            if (preg_match('/^B([0-9]+)$/', $ref, $match) && $value === $store && ($cells['C'.$match[1]] ?? null) === $product) {
                return $cells[$column.$match[1]] ?? '';
            }
        }

        $this->fail('Нет строки '.$store.' / '.$product);
    }
}
