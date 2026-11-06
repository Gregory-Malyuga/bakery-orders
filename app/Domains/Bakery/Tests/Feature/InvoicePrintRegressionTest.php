<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\OrderingService;
use Domains\Bakery\Application\OrderReturnSummary;
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

class InvoicePrintRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0:Order,1:Product,2:User} */
    private function fixture(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'Europe/Moscow'));
        $store = Bakery::query()->create(['name' => 'Печатная точка', 'address' => 'Печатный адрес', 'is_active' => true]);
        $product = Product::query()->create(['name' => 'Проверочная булочка', 'sort_order' => 1, 'minimum_quantity' => 1, 'packaging_mode' => 'single', 'is_active' => true]);
        Price::query()->create(['bakery_id' => $store->id, 'product_id' => $product->id, 'kopecks' => 1234]);
        $service = app(OrderingService::class);
        $quote = $service->quote($store->id, [$product->id => 3]);
        $order = $service->place($store->id, (string) Str::uuid(), [$product->id => 3], $quote['hash'])['order'];
        $this->assertInstanceOf(Order::class, $order);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);

        return [$order, $product, $admin];
    }

    private function addReturn(Order $order, Product $product, User $admin, string $status = 'active', bool $extra = false): void
    {
        DB::table('bakery_returns')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'admin_user_id' => $admin->id,
            'quantity' => 1,
            'reason' => 'Проверка',
            'product_name' => $product->name,
            'unit_kopecks' => 1234,
            'is_order_item' => $extra === false,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    private function html(Order $order): string
    {
        return view('bakery.print', [
            'orders' => collect([$order->load('items')]),
            'returns' => app(OrderReturnSummary::class)->forOrders([$order]),
            'downloadUrl' => '/admin/xlsx',
            'groups' => [],
        ])->render();
    }

    private function xlsx(Order $order): string
    {
        $path = app(MatrixInvoiceWorkbook::class)->create([$order->load('items')], []);

        try {
            $archive = new PharData($path);
            $xml = $archive['xl/worksheets/sheet1.xml']->getContent();
            unset($archive);

            return $xml;
        } finally {
            @unlink($path);
        }
    }

    public function test_without_returns_print_and_xlsx_have_five_columns_and_original_total(): void
    {
        [$order] = $this->fixture();
        $html = $this->html($order);
        $xml = $this->xlsx($order);
        $this->assertStringContainsString('37,02', $html);
        $this->assertStringNotContainsString('Возврат, шт.', $html);
        $this->assertStringNotContainsString('После возврата, ₽', $html);
        $this->assertStringNotContainsString('Возврат</t>', $xml);
        $this->assertStringNotContainsString('После возврата, ₽', $xml);
        $this->assertStringContainsString('37.02', $xml);
        $this->assertStringNotContainsString('<col min="6"', $xml);
    }

    public function test_active_return_adds_seven_columns_and_shows_gross_and_net(): void
    {
        [$order, $product, $admin] = $this->fixture();
        $this->addReturn($order, $product, $admin);
        $html = $this->html($order);
        $xml = $this->xlsx($order);
        $this->assertStringContainsString('Возврат, шт.', $html);
        $this->assertStringContainsString('После возврата, ₽', $html);
        $this->assertStringContainsString('24,68', $html);
        $this->assertStringContainsString('Возврат</t>', $xml);
        $this->assertStringContainsString('После возврата, ₽', $xml);
        $this->assertStringContainsString('24.68', $xml);
        $this->assertStringContainsString('<col min="7"', $xml);
    }

    public function test_cancelled_return_does_not_trigger_columns_or_reduce_total(): void
    {
        [$order, $product, $admin] = $this->fixture();
        $this->addReturn($order, $product, $admin, 'cancelled');
        $html = $this->html($order);
        $xml = $this->xlsx($order);
        $this->assertStringNotContainsString('Возврат, шт.', $html);
        $this->assertStringNotContainsString('Возврат</t>', $xml);
        $this->assertStringContainsString('37,02', $html);
        $this->assertStringContainsString('37.02', $xml);
    }

    public function test_extra_return_is_reflected_in_print_and_xlsx(): void
    {
        [$order, $product, $admin] = $this->fixture();
        $this->addReturn($order, $product, $admin, 'active', true);
        $html = $this->html($order);
        $xml = $this->xlsx($order);
        $this->assertStringContainsString('Возврат, шт.', $html);
        $this->assertStringContainsString('24,68', $html);
        $this->assertStringContainsString('24.68', $xml);
        $this->assertStringContainsString('После возврата, ₽', $xml);
    }

    public function test_print_document_exposes_print_button_download_link_and_store_snapshot(): void
    {
        [$order] = $this->fixture();
        $html = $this->html($order);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('/admin/xlsx', $html);
        $this->assertStringContainsString('Печатная точка', $html);
        $this->assertStringContainsString('Печатный адрес', $html);
        $this->assertStringContainsString('Проверочная булочка', $html);
    }
    public function test_xlsx_without_returns_uses_correct_cell_coordinates_and_formulas(): void
    {
        [$order] = $this->fixture();
        $xml = $this->xlsx($order);

        $this->assertStringContainsString('<c r="D7" s="6"><v>12.34</v></c>', $xml);
        $this->assertStringContainsString('<c r="E7" s="6"><f>C7*D7</f><v>37.02</v></c>', $xml);
        $this->assertStringContainsString('<c r="E8" s="8"><f>SUM(E7:E7)</f><v>37.02</v></c>', $xml);
        $this->assertStringNotContainsString('<c r="F6"', $xml);
        $this->assertStringContainsString('fitToWidth="1"', $xml);
        $this->assertStringContainsString('orientation="portrait"', $xml);
    }

    public function test_xlsx_with_return_preserves_gross_and_net_formulas_in_separate_columns(): void
    {
        [$order, $product, $admin] = $this->fixture();
        $this->addReturn($order, $product, $admin);
        $xml = $this->xlsx($order);

        $this->assertStringContainsString('<c r="D7" s="4"><v>1</v></c>', $xml);
        $this->assertStringContainsString('<c r="F7" s="6"><f>C7*E7</f><v>37.02</v></c>', $xml);
        $this->assertStringContainsString('<c r="G7" s="6"><f>(C7-D7)*E7</f><v>24.68</v></c>', $xml);
        $this->assertStringContainsString('<c r="F8" s="8"><f>SUM(F7:F7)</f><v>37.02</v></c>', $xml);
        $this->assertStringContainsString('<c r="G8" s="8"><f>SUM(G7:G7)</f><v>24.68</v></c>', $xml);
    }

    public function test_generated_xlsx_package_has_valid_xml_and_required_parts(): void
    {
        [$order] = $this->fixture();
        $path = app(MatrixInvoiceWorkbook::class)->create([$order->load('items')], []);

        try {
            $archive = new PharData($path);
            foreach ([
                '[Content_Types].xml',
                '_rels/.rels',
                'xl/workbook.xml',
                'xl/_rels/workbook.xml.rels',
                'xl/styles.xml',
                'xl/worksheets/sheet1.xml',
                'xl/worksheets/sheet2.xml',
            ] as $part) {
                $xml = $archive[$part]->getContent();
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($xml), 'Malformed OOXML part: '.$part);
            }
            $workbook = $archive['xl/workbook.xml']->getContent();
            $this->assertStringContainsString('fullCalcOnLoad="1"', $workbook);
            $this->assertStringContainsString('name="Заказ"', $workbook);
            unset($archive);
        } finally {
            @unlink($path);
        }
    }

    public function test_print_escapes_comment_markup_and_displays_original_order_snapshot(): void
    {
        [$order, $product] = $this->fixture();
        $order->update(['comment' => '<script>alert("test")</script>']);
        Price::query()->where('bakery_id', $order->bakery_id)->where('product_id', $product->id)->update(['kopecks' => 9900]);
        $html = $this->html($order->fresh());

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('12,34', $html);
        $this->assertStringNotContainsString('99,00', $html);
    }

    public function test_empty_export_produces_readable_placeholder_invoice_and_summary(): void
    {
        $path = app(MatrixInvoiceWorkbook::class)->create([], []);

        try {
            $archive = new PharData($path);
            $workbook = $archive['xl/workbook.xml']->getContent();
            $sheet = $archive['xl/worksheets/sheet1.xml']->getContent();
            $this->assertStringContainsString('name="Накладная"', $workbook);
            $this->assertStringContainsString('name="Заказ"', $workbook);
            $this->assertStringContainsString('На выбранную дату принятых заказов нет.', $sheet);
            unset($archive);
        } finally {
            @unlink($path);
        }
    }

}
