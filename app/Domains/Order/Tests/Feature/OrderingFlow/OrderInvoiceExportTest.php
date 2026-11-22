<?php

namespace Domains\Order\Tests\Feature\OrderingFlow;

use Carbon\Carbon;
use Domains\Order\Application\OrderingService;
use Domains\Order\Tests\Feature\Support\OrderingFlowCase;
use Domains\Price\Domain\Models\Price;
use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Support\Str;
use PharData;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderInvoiceExportTest extends OrderingFlowCase
{
    public function testExcelDownloadContainsOnlyAcceptedInvoiceSnapshots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$firstStore, $storeUser, $product] = $this->shop('Седьмой', 3150);
        [$secondStore] = $this->shop('Восьмой', 3200);
        [$thirdStore] = $this->shop('Девятый', 3400);
        $service = app(OrderingService::class);
        $quote = $service->quote($firstStore->id, [$product->id => 2]);
        $accepted = $service->place($firstStore->id, (string) Str::uuid(), [$product->id => 2], $quote['hash'])['order'];
        $this->assertNotNull($accepted);
        $otherQuote = $service->quote($secondStore->id, [$product->id => 1]);
        $cancelled = $service->place($secondStore->id, (string) Str::uuid(), [$product->id => 1], $otherQuote['hash'])['order'];
        $this->assertNotNull($cancelled);
        $service->cancel($secondStore->id, $cancelled->id);
        $thirdQuote = $service->quote($thirdStore->id, [$product->id => 3]);
        $this->assertNotNull($service->place($thirdStore->id, (string) Str::uuid(), [$product->id => 3], $thirdQuote['hash'])['order']);
        Price::query()->where('bakery_id', $firstStore->id)->where('product_id', $product->id)->update(['kopecks' => 9900]);

        $this->actingAs($storeUser)->get('/admin/xlsx?date='.$quote['date'])->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $response = $this->actingAs($admin)->get('/admin/xlsx?date='.$quote['date'])->assertOk()->assertDownload('nakladnye-'.$quote['date'].'.xlsx');
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $sheet = $archive['xl/worksheets/sheet1.xml']->getContent();
        $secondInvoice = $archive['xl/worksheets/sheet2.xml']->getContent();
        $summary = $archive['xl/worksheets/sheet3.xml']->getContent();
        $workbook = $archive['xl/workbook.xml']->getContent();
        $styles = $archive['xl/styles.xml']->getContent();
        $this->assertStringContainsString('<fills count="4">', $styles);
        $this->assertStringContainsString('fgColor rgb="FFFFFF00"', $styles);
        $this->assertStringContainsString('fgColor rgb="FFE2F0D9"', $styles);
        $this->assertStringContainsString('<borders count="2">', $styles);
        $this->assertStringContainsString('r="A6" s="5"', $sheet);
        $this->assertStringContainsString('r="A7" s="4"', $sheet);
        $this->assertStringContainsString('r="A3" s="5"', $summary);
        $this->assertStringContainsString('Седьмой', $sheet);
        $this->assertStringNotContainsString('Девятый', $sheet);
        $this->assertStringContainsString('Девятый', $secondInvoice);
        $this->assertStringContainsString('Седьмой 1 — Седьмой', $workbook);
        $this->assertStringContainsString('Девятый 1 — Девятый', $workbook);
        $this->assertStringNotContainsString('Накладная '.$accepted->id.'"', $workbook);
        $this->assertStringContainsString('sheetId="3"', $workbook);
        $this->assertStringContainsString('<v>31.50</v>', $sheet);
        $this->assertStringContainsString('<v>34.00</v>', $secondInvoice);
        $this->assertStringNotContainsString('Восьмой', $sheet);
        $this->assertStringContainsString('Заказ на ', $summary);
        $this->assertStringContainsString('Итого, ₽', $summary);
        $this->assertStringContainsString('<v>165.00</v>', $summary);
        $this->assertStringNotContainsString('Восьмой', $summary);
        $this->actingAs($admin)->get('/admin/orders/'.$cancelled->id.'/xlsx')->assertNotFound();
        unset($archive);
        unlink($path);
    }

    public function testExcelInvoiceSheetNamesSanitizeAndDistinguishSameCustomerAddress(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'Europe/Moscow'));
        [$first, , $product] = $this->shop('Точка: [Центр]', 3150);
        [$second] = $this->shop('Точка: [Центр]', 3150);
        foreach ([$first, $second] as $store) {
            $store->update(['address' => 'ул. Мира/5']);
            $quote = app(OrderingService::class)->quote($store->id, [$product->id => 1]);
            app(OrderingService::class)->place($store->id, (string) Str::uuid(), [$product->id => 1], $quote['hash']);
        }

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Verify]);
        $response = $this->actingAs($admin)->get('/admin/xlsx?date=2026-10-07')->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new PharData($path);
        $workbook = $archive['xl/workbook.xml']->getContent();
        $this->assertStringContainsString('name="ул. Мира 5 — Точка Центр"', $workbook);
        $this->assertStringContainsString('name="ул. Мира 5 — Точка Центр (2)"', $workbook);
        preg_match_all('/<sheet name="([^"]+)"/', $workbook, $sheetNames);
        foreach ($sheetNames[1] as $name) {
            $this->assertLessThanOrEqual(31, mb_strlen($name));
        }
        unset($archive);
        unlink($path);
    }
}
