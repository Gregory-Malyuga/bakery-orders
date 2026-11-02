<?php

namespace Domains\Bakery\Tests\Feature;

use Domains\Bakery\Application\OrderReturnSummary;
use Domains\Bakery\Infrastructure\Exports\MatrixInvoiceWorkbook;
use Illuminate\Foundation\Testing\TestCase;
use PharData;

/** @phpstan-import-type GroupInvoice from \Domains\Bakery\Application\GroupInvoiceService */
class GroupWorkbookLayoutTest extends TestCase
{
    /**
     * @return GroupInvoice
     */
    private function group(bool $showReturns): array
    {
        return [
            'id' => 1,
            'name' => $showReturns ? 'Обычная группа' : 'Балтийская общая',
            'show_return_columns' => $showReturns,
            'members' => [
                ['name' => 'Первая', 'address' => 'Адрес 1', 'comment' => null, 'ordered' => true, 'quantity' => 2, 'gross' => 10000, 'returns' => 1000, 'net' => 9000],
                ['name' => 'Вторая', 'address' => 'Адрес 2', 'comment' => 'Оставить у входа', 'ordered' => true, 'quantity' => 1, 'gross' => 5000, 'returns' => 0, 'net' => 5000],
            ],
            'products' => [
                ['name' => 'Булочка', 'quantity' => 3, 'returned' => 1, 'unit_kopecks' => 5000, 'gross' => 15000, 'net' => 14000],
            ],
            'quantity' => 3,
            'returned_quantity' => 1,
            'gross' => 15000,
            'returns' => 1000,
            'net' => 14000,
            'has_orders' => true,
        ];
    }

    private function sheet(bool $showReturns): string
    {
        $workbook = new MatrixInvoiceWorkbook(new OrderReturnSummary);
        $path = $workbook->create([], [], [$this->group($showReturns)], '2026-11-02', '02.11.2026 12:00');

        try {
            $archive = new PharData($path);

            return $archive['xl/worksheets/sheet1.xml']->getContent();
        } finally {
            unset($archive);
            @unlink($path);
        }
    }

    public function test_baltic_group_has_labeled_and_styled_member_breakdown_without_return_column(): void
    {
        $xml = $this->sheet(false);

        foreach (['Состав группы на момент формирования', 'Торговая точка', 'Изделий, шт.', 'Заказ, ₽', 'К оплате, ₽', 'Итого по точкам', 'Оставить у входа'] as $label) {
            $this->assertStringContainsString($label, $xml);
        }
        $this->assertStringNotContainsString('Возврат, ₽', $xml);
        $this->assertStringNotContainsString('После возврата, ₽', $xml);
        $this->assertStringContainsString('140.00', $xml);
        $this->assertStringContainsString('s="5"', $xml);
        $this->assertStringContainsString('s="7"', $xml);
        $this->assertStringContainsString('s="8"', $xml);
    }

    public function test_regular_group_keeps_returns_visible_in_member_breakdown(): void
    {
        $xml = $this->sheet(true);

        $this->assertStringContainsString('Возврат, ₽', $xml);
        $this->assertStringContainsString('К оплате, ₽', $xml);
        $this->assertStringContainsString('Итого по точкам', $xml);
        $this->assertStringContainsString('150.00', $xml);
        $this->assertStringContainsString('140.00', $xml);
    }

    public function test_group_xlsx_contains_no_unlabeled_member_amounts(): void
    {
        $xml = $this->sheet(false);

        $this->assertStringContainsString('Заказ, ₽', $xml);
        $this->assertStringContainsString('К оплате, ₽', $xml);
        $this->assertStringContainsString('Первая', $xml);
        $this->assertStringContainsString('Вторая', $xml);
    }
}
